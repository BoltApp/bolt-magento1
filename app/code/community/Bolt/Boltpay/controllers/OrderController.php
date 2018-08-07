<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_OrderController
 *
 * Saves the order in Magento system after successful Bolt transaction processing.
 */
class Bolt_Boltpay_OrderController extends Mage_Core_Controller_Front_Action
{

    /**
     * Frontend save order action. Called from BoltCheckout.configure success callback.
     * The actual order creation is done in the helper class, for both frontend and backend (API) requests.
     */
    public function saveAction()
    {
        try {

            if (!$this->getRequest()->isAjax()) {
                Mage::throwException(Mage::helper('boltpay')->__("OrderController::saveAction called with a non AJAX call"));
            }

            /** @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');

            $checkoutSession = Mage::getSingleton('checkout/session');

            $reference = $this->getRequest()->getPost('reference');
            $transaction = $boltHelper->fetchTransaction($reference);

            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    "Save Action reference" => array (
                        "reference" => $reference,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            //////////////////////////////////////////////////////////
            // Check for existing order by order reference in
            // case webhooks beat this to the punch.  If the webhooks
            // have already created the order, we don't need to do anything
            // besides returning 200 OK, which happens automatically
            /////////////////////////////////////////////////////////
            $order = $boltHelper->getOrderByQuoteId($boltHelper->getImmutableQuoteIdFromTransaction($transaction));

            if ($order->isObjectNew()) {
                $sessionQuote = $checkoutSession->getQuote();
                $boltHelper->createOrder($reference, $sessionQuote->getId(), true, $transaction);
            }

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Locks and unlocks cart.  This is to be called by the Bolt server corresponding to the
     * opening and the closing of the Bolt modal.
     */
    public function lockcartAction()
    {
        ////////////////////////////////////////////////////////////////////////
        // To be uncommented once the lock cart endpoint has been implemented
        ////////////////////////////////////////////////////////////////////////
        /*
        $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

        $request_json = file_get_contents('php://input');
        $request_data = json_decode($request_json);

        $boltHelper = Mage::helper('boltpay/api');
        if (!$boltHelper->verify_hook($request_json, $hmac_header)) exit;
        */

        $quote = Mage::getModel('sales/quote')
            ->loadByIdWithoutStore(Mage::app()->getRequest()->getParam('quote_id'));

        $quote->setIsActive((bool)Mage::app()->getRequest()->getParam('unlock', false));
        $quote->save();

        Mage::getSingleton('checkout/session')->setQuoteId($quote->getId());
    }

    /**
     * Creating the Bolt order and returning Bolt.process javascript.
     * Called from the firecheckout page.
     */
    public function firecheckoutcreateAction()
    {

        try {
            if (!$this->getRequest()->isAjax()) {
                Mage::throwException(Mage::helper('boltpay')->__("OrderController::createAction called with a non AJAX call"));
            }


            $checkout = Mage::getSingleton('firecheckout/type_standard');
            $quote = $checkout->getQuote();
            $billing  = $this->getRequest()->getPost('billing', array());

            $this->getResponse()->setHeader('Content-type', 'application/json', true);

            $result = $checkout->saveBilling(
                $billing,
                $this->getRequest()->getPost('billing_address_id', false)
            );

            if ($result) {
                $result['success'] = false;
                $result['error']   = true;
                if ($result['message'] === $checkout->getCustomerEmailExistsMessage()) {
                    unset($result['message']);
                    $result['body'] = array(
                        'id'      => 'emailexists',
                        'modal'   => 1,
                        'window'  => array(
                            'triggers' => array(),
                            'destroy'  => 1,
                            'size'     => array(
                                'maxWidth' => 400
                            )
                        ),
                        'content' => $this->getLayout()->createBlock('core/template')
                            ->setTemplate('tm/firecheckout/emailexists.phtml')
                            ->toHtml()
                    );
                } else {
                    $result['error_messages'] = $result['message'];
                    $result['onecolumn_step'] = 'step-address';
                }

                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                return;
            }

            if ((!isset($billing['use_for_shipping']) || !$billing['use_for_shipping'])
                && !$quote->isVirtual()) {
                $result = $checkout->saveShipping(
                    $this->getRequest()->getPost('shipping', array()),
                    $this->getRequest()->getPost('shipping_address_id', false)
                );
                if ($result) {
                    $result['success'] = false;
                    $result['error']   = true;
                    $result['error_messages'] = $result['message'];
                    $result['onecolumn_step'] = 'step-address';
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }

            $checkout->applyShippingMethod($this->getRequest()->getPost('shipping_method', false));  # FireCheckout Logic for adding shipping method
            $quote->getShippingAddress()->setShippingMethod($this->getRequest()->getPost('shipping_method', false));  # Bolt logic for adding shipping method

            $checkout->registerCustomerIfRequested();

            Mage::helper('boltpay')->collectTotals($quote)->save();

            $block = $this->getLayout()->createBlock('boltpay/checkout_boltpay');

            $result = array();

            $result['cart_data'] = $block->getCartDataJs('one-page');

            if (!$result['cart_data']) {
                $result['success'] = false;
                $result['error']   = true;
                $result['error_messages'] = Mage::helper('boltpay')->__("Your shopping cart is empty.  Your session may have expired.");
            }

            $this->getResponse()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }
}
