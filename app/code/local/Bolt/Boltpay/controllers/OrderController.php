<?php

/**
 * Class Bolt_Boltpay_OrderController
 *
 * Saves the order in Magento system after successful Bolt transaction processing.
 */
class Bolt_Boltpay_OrderController extends Mage_Core_Controller_Front_Action {

    /**
     * Frontend save order action. Called from BoltConnect.process success callback.
     * The actual order creation is done in the helper class, for both frontend and backend (API) requests.
     */
    public function saveAction()
    {
        try {

            if (!$this->getRequest()->isAjax()) {
                Mage::throwException("OrderController::saveAction called with a non AJAX call");
            }

            $boltHelper = Mage::helper('boltpay/api');

            $checkout_session = Mage::getSingleton('checkout/session');


            $reference = $this->getRequest()->getPost('reference');

            Mage::helper('boltpay/bugsnag')->addMetaData(
                array(
                    "Save Action reference" => array (
                        "reference" => $reference,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            $session_quote = $checkout_session->getQuote();

            $order = $boltHelper->createOrder($reference, $session_quote->getId());

            $checkout_session->setLastQuoteId($session_quote->getId())
                ->setLastSuccessQuoteId($session_quote->getId())
                ->clearHelperData();

            if ($order) {

                // add order information to the session
                $checkout_session->setLastOrderId($order->getId())
                    ->setRedirectUrl('')
                    ->setLastRealOrderId($order->getIncrementId());
            }

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Creating the Bolt order and returning Bolt.process javascript.
     * Called from the firecheckout page.
     */
    public function firecheckoutcreateAction() {

        try {

            if (!$this->getRequest()->isAjax()) {
                Mage::throwException("OrderController::createAction called with a non AJAX call");
            }

            $checkout = Mage::getSingleton('firecheckout/type_standard');
            $quote = $checkout->getQuote();
            $billing  = $this->getRequest()->getPost('billing', array());

            $checkout->applyShippingMethod($this->getRequest()->getPost('shipping_method', false));

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

            $checkout->registerCustomerIfRequested();

            $quote->collectTotals()->save();

            $block = $this->getLayout()->createBlock('boltpay/checkout_boltpay');

            $result = array();

            $result['cart_data'] = $block->getCartDataJs(false);

            if (!$result['cart_data']) {
                $result['success'] = false;
                $result['error']   = true;
                $result['error_messages'] = "Your shopping cart is empty.  Your session may have expired.";
            }

            $this->getResponse()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }
}