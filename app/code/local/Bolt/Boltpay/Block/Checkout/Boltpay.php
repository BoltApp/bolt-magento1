<?php

class Bolt_Boltpay_Block_Checkout_Boltpay
    extends Mage_Checkout_Block_Onepage_Review_Info {

    const JS_URL_TEST = 'https://cdn-connect-staging.boltapp.com/connect.js';
    const JS_URL_PROD = 'https://connect.boltapp.com/connect.js';
    const AUTO_CAPTURE_ENABLED = 1;

    public function _construct() {
        parent::_construct();
        $this->_jsUrl = Mage::getStoreConfig('payment/boltpay/test') ?
            self::JS_URL_TEST:
            self::JS_URL_PROD;
    }

    public function createOrder($quote) {
        $boltHelper = Mage::helper('boltpay/api');
        $order_request = $boltHelper->buildOrder($quote, $this->getItems());
        return $boltHelper->handleErrorResponse($boltHelper->transmit('orders', $order_request));
    }

    public function getCartDataJs() {
        $customerSession = Mage::getSingleton('customer/session');
        $session = Mage::getSingleton('checkout/session');
        $onepage = Mage::getSingleton('checkout/type_onepage');
        $boltHelper = Mage::helper('boltpay/api');
        $hint_data = array();
        $quote = $session->getQuote();

        $quote->reserveOrderId()->save();
        $reservedUserId = $this->getReservedUserId($quote, $customerSession, $onepage);
        $signResponse = null;

        if ($reservedUserId != null) {
            $signRequest = array(
                'merchant_user_id' => $reservedUserId,
            );
            $signResponse = $boltHelper->handleErrorResponse($boltHelper->transmit('sign', $signRequest));
        }

        if (Mage::getStoreConfig('payment/boltpay/auto_capture') == self::AUTO_CAPTURE_ENABLED) {
            $authCapture = 'true';
        } else {
            $authCapture = 'false';
        }

        $orderCreationResponse = $this->createOrder($quote);
        $cart_data = array(
            'authcapture' => $authCapture,
            'orderToken' => $orderCreationResponse->token,
        );

        if ($signResponse != null) {
            $hint_data['signed_merchant_user_id'] = array(
                "merchant_user_id" => $signResponse->merchant_user_id,
                "signature" => $signResponse->signature,
                "nonce" => $signResponse->nonce,
            );
        }

        $key = $this->getUrl(
            'checkout/onepage/saveOrder',
            array('form_key' => Mage::getSingleton('core/session')->getFormKey())
        );
        $json_cart = json_encode($cart_data);
        $json_hints = '{}';
        if (sizeof($hint_data) != 0) {
            $json_hints = json_encode($hint_data);
        }
        $url = $this->getUrl('checkout/onepage/success');
        return (
            "var boltreview = new Review(
                '$key',
                '$url',
                $('checkout-agreements'),
                false
            );
            BoltConnect.process(
                $json_cart,
                $json_hints,
                {
                    close: function() {
                        boltreview.redirect();
                    },
                    success: function(transaction, callback) {
                        $('p_method_boltpay_reference').value = transaction.reference;
                        $('p_method_boltpay_transaction_status').value = transaction.status;
                        boltreview.save(callback);
                    }
                }
            );"
        );
    }

    public function asMoney($amount) {
        return Mage::helper('core')->currency($amount, true, false);
    }

    function getReservedUserId($quote, $session, $onepage) {
        $checkoutMethod = $onepage->getCheckoutMethod();

        if ($session->isLoggedIn()) {
            $customer = Mage::getModel('customer/customer')->load($session->getId());

            if ($customer->getBoltUserId() == 0 || $customer->getBoltUserId() == null) {
                Mage::log("Creating new user id for logged in user", null, 'bolt.log');
                $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
                $customer->setBoltUserId($custId);
                $customer->save();
            }

            Mage::log(sprintf("Using Bolt User Id: %s", $customer->getBoltUserId()), null, 'bolt.log');
            return $customer->getBoltUserId();

        } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            Mage::log("Creating new user id for Register checkout", null, 'bolt.log');
            $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
            $session->setBoltUserId($custId);
            return $custId;
        }

        return null;
    }
}
