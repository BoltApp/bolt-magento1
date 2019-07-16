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
     * event: bolt_boltpay_order_creation_after
     * @param $observer
     */
    public function addOrderNote($observer) {

        $order = $observer->getOrder();
        $transaction = $observer->getTransaction();

        if (isset($transaction->order->user_note)) {
            Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                'customerordercomments', $transaction->order->user_note
            )->save();
        }
    }

    /**
     * event:
     * @param $observer
     */
    public function addNoteToBoltOrder($observer) {

        if ($order->getId()) {

            if(Mage::getSingleton('core/session')->getBoltOnePageComments()) {
                Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                    'customerordercomments', Mage::getSingleton('core/session')->getBoltOnePageComments()
                )->save();
                Mage::getSingleton('core/session')->unsBoltOnePageComments();
            }
        }
    }
}
