<?php
class Bolt_Boltpay_Block_Rewrite_Onepage extends Mage_Checkout_Block_Onepage {
    /**
     * Get checkout steps codes
     *
     * @return array
     */
    protected function _getStepCodes() {
        $steps = array('login', 'billing', 'shipping', 'shipping_method');
        if (Mage::getStoreConfig('payment/boltpay/skip_payment') != 1) {
            array_push($steps, 'payment');
        }
        array_push($steps, 'review');
        return $steps;
    }
}
