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
}