<?php

class Bolt_Boltpay_Model_Observer {
    public function saveOrderAfter($observer) {
        Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Started", null, 'bolt.log');
        $quote = $observer->getEvent()->getQuote();
        $session = Mage::getSingleton('customer/session');
        $order = $observer->getEvent()->getOrder();

        try {
            $customer = $quote->getCustomer();
            $boltUserId = $session->getBoltUserId();

            if ($customer != null && $boltUserId != null) {
                if ($customer->getBoltUserId() == null || $customer->getBoltUserId() == 0) {
                    Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Adding bolt_user_id to the customer from the quote", null, 'bolt.log');
                    $customer->setBoltUserId($boltUserId);
                    $customer->save();
                }
            }

            $method = $quote->getPayment()->getMethod();
            if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
                Mage::getModel('boltpay/payment')->handleOrderUpdate($order);
            }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            throw $e;
        } finally {
            $session->unsBoltUserId();
        }

        Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Completed", null, 'bolt.log');
    }

    public function saveOrderBefore($observer) {
        Mage::log("Bolt_Boltpay_Model_Observer.saveOrderBefore: Started", null, 'bolt.log');
        $boltHelper = Mage::helper('boltpay/api');
        $quote = $observer->getEvent()->getQuote();
        $payment = $quote->getPayment();
        $items = Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
        $method = $payment->getMethod();
        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            if (Mage::getStoreConfig('payment/boltpay/auto_capture') == Bolt_Boltpay_Block_Checkout_Boltpay::AUTO_CAPTURE_ENABLED) {
                $authCapture = true;
            } else {
                $authCapture = false;
            }

            $reference = $payment->getAdditionalInformation('bolt_reference');
            $cart_request = $boltHelper->buildCart($quote, $items);
            $complete_authorize_request = array(
                'cart' => $cart_request,
                'reference' => $reference,
                'auto_capture' => $authCapture
            );
            if (Mage::getStoreConfig('payment/boltpay/disable_complete_authorize'))  {
               Mage::log("Bolt_Boltpay_Model_Observer.saveOrderBefore: Skipping complete authorize", null, 'bolt.log');
               return;
            }
            $boltHelper->handleErrorResponse($boltHelper->transmit('complete_authorize', $complete_authorize_request));
        }

        Mage::log("Bolt_Boltpay_Model_Observer.saveOrderBefore: Completed", null, 'bolt.log');
    }
}
