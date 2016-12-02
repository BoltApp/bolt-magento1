<?php

class Bolt_Boltpay_Model_Observer
{
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
}
