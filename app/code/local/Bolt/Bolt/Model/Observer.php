<?php

class Bolt_Bolt_Model_Observer extends Amasty_Rules_Model_Observer
{
    /**
     * @param $observer
     * Process quote item validation and discount calculation
     * @return $this
     */
    public function handleValidation($observer)
    {
        $promotions =  Mage::getModel('amrules/promotions');
        $promotions->process($observer);
        return $this;
    }

    /**
     * Adds the user note to the Magento created order
     *
     * event: bolt_boltpay_order_creation_after
     * @param $observer
     */
    public function addOrderNote($observer) {

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $transaction = $observer->getTransaction();

        if (isset($transaction->order->user_note)) {
            Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                'customerordercomments', $transaction->order->user_note
            )->save();
        }

        Mage::getSingleton('core/session')->unsBoltOnePageComments();
    }

    /**
     * Adds the user note to the Bolt order data if it has already been set in the session
     *
     * event: bolt_boltpay_filter_boltOrder
     *
     * @param $observer
     */
    public function addNoteToBoltOrder($observer) {

        $valueWrapper = $observer->getValueWrapper();
        $orderData = $valueWrapper->getValue();
        $comments = Mage::getSingleton('core/session')->getBoltOnePageComments();

        if($comments) {
            $orderData['user_note'] = $comments;
        }

        $valueWrapper->setValue($orderData);
    }
}
