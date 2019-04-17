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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_OrderController
 *
 * Saves the order in Magento system after successful Bolt transaction processing.
 */
class Bolt_Boltpay_OrderController extends Mage_Core_Controller_Front_Action
{
    use Bolt_Boltpay_Controller_Traits_OrderControllerTrait;

    /**
     * Frontend save order action. Called from BoltCheckout.configure success callback.
     * The actual order creation is done in the helper class, for both frontend and backend (API) requests.
     */
    public function saveAction()
    {
        try {

            if (!$this->getRequest()->isAjax()) {
                Mage::throwException($this->boltHelper()->__("Bolt_Boltpay_OrderController::saveAction called with a non AJAX call"));
            }

            $checkoutSession = Mage::getSingleton('checkout/session');

            $reference = $this->getRequest()->getPost('reference');
            $transaction = $this->boltHelper()->fetchTransaction($reference);

            $this->boltHelper()->addBreadcrumb(
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
            /** @var  Bolt_Boltpay_Model_Order $orderModel */
            $orderModel = Mage::getModel('boltpay/order');
            $order = $orderModel->getOrderByQuoteId($this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction));

            if ($order->isObjectNew()) {
                $sessionQuoteId = ($this->getRequest()->getParam('checkoutType') == Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE) ? null : $checkoutSession->getQuoteId();
                $orderModel->createOrder($reference,$sessionQuoteId, true, $transaction);
            }

        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logError($e);
            throw $e;
        }
    }

    /**
     * Creating the Bolt order and returning Bolt.process javascript.
     * Called from the firecheckout page.
     */
    public function firecheckoutcreateAction()
    {
        try {
            if (!$this->getRequest()->isAjax()) {
                Mage::throwException($this->boltHelper()->__("OrderController::firecheckoutcreateAction called with a non AJAX call"));
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
                    $result['error_messages'] = $result['message'];
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

            $this->boltHelper()->collectTotals($quote)->save();

            $result = array();
            $result['cart_data'] = $this->getCartData($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE);

            if (@$result['cart_data']['error']) {
                $result['success'] = false;
                $result['error']   = true;
                $result['error_messages'] = $result['cart_data']['error'];
            }

            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logError($e);
            throw $e;
        }
    }

    /**
     * API to expose order details
     */
    public function viewAction()
    {
        try {
            $hmacHeader = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            if (!$this->boltHelper()->verify_hook("{}", $hmacHeader)) {
                Mage::throwException($this->boltHelper()->__("Failed HMAC Authentication"));
            }

            $reference = $this->getRequest()->getParam('reference');

            if (!$reference) {
                Mage::throwException($this->boltHelper()->__("Transaction parameter is required"));
            }

            /** @var Bolt_Boltpay_Model_Order_Detail $boltOrder */
            $boltOrder = Mage::getModel('boltpay/order_detail');
            $boltOrder->init($reference);

            $transaction = $boltOrder->generateOrderDetail();

            $response = Mage::helper('core')->jsonEncode($transaction);
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody($response);
        } catch (Exception $e) {
            if (
                strpos($e->getMessage(), $this->boltHelper()->__('No order found')) !== 0 ||
                strpos($e->getMessage(), $this->boltHelper()->__('No payment found')) !== 0
            ) {
                $this->getResponse()->setHttpResponseCode(404)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $e->getMessage()))));
            } else {
                $this->getResponse()->setHttpResponseCode(409)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $e->getMessage()))));

                $this->boltHelper()->notifyException($e);
            }
        }
    }

}