<?php

/**
 * Class Bolt_Boltpay_Model_Observer
 *
 * This class implements order event behavior
 */
class Bolt_Boltpay_Model_Observer {

    const READY_FOR_SHIPMENT = 'ready_for_shipment';

    /**
     * Event handler called after a save event.
     * Adds the Bolt User Id to the newly registered customer.
     *
     * @param $observer
     * @throws Exception
     */
    public function setBoltUserId($observer) {

        $quote = $observer->getEvent()->getQuote();
        $session = Mage::getSingleton('customer/session');

        try {
            $customer = $quote->getCustomer();
            $boltUserId = $session->getBoltUserId();

            if ($customer != null && $boltUserId != null) {
                if ($customer->getBoltUserId() == null || $customer->getBoltUserId() == 0) {
                    //Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Adding bolt_user_id to the customer from the quote", null, 'bolt.log');
                    $customer->setBoltUserId($boltUserId);
                    $customer->save();
                }
            }
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }

        $session->unsBoltUserId();
    }

    /**
     * Event handler called after a save event.
     * Calls the complete authorize Bolt end point to confirm the order is valid.
     * If the order has been changed between the creation on Bolt end and the save in Magento
     * an order info message is recorded to inform the merchant and a bugsnag notification sent to Bolt.
     *
     * @param $observer
     * @throws Exception
     */
    public function verifyOrderContents($observer) {

        $boltHelper = Mage::helper('boltpay/api');
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

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

            try {
                if (!Mage::getStoreConfig('payment/boltpay/disable_complete_authorize'))  {
                    $boltHelper->transmit('complete_authorize', $complete_authorize_request);
                }
            } catch (Exception $e) {
                $message = "THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>PLEASE COMPARE THE ORDER DETAILS WITH THAT RECORD IN YOUR BOLT MERCHANT ACCOUNT AT: ";
                $message .= Mage::getStoreConfig('payment/boltpay/test') ? "https://merchant-sandbox.bolt.com" : "https://merchant.bolt.com";
                $message .= "/transaction/$reference";
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $message)->save();

                $metaData = array(
                    'endpoint'   => "complete_authorize",
                    'reference'  => $reference,
                    'quote_id'   => $quote->getId(),
                    'display_id' => $quote->getReservedOrderId(),
                );
                Mage::helper('boltpay/bugsnag')->notifyException($e, $metaData);
            }

            $order->sendNewOrderEmail()
                ->addStatusHistoryComment('Email sent for order ' . $order->getIncrementId())
                ->setIsCustomerNotified(true)
                ->save();
        }
    }
}
