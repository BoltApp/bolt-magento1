<?php

/**
 * Class Bolt_Boltpay_Block_Rewrite_Onepage
 *
 * Defines onpage checkout steps depending on the boltpay skip payment configuration.
 * If the skip payment is set, which means Bolt is the only payment method, then skip the payment select step.
 */
class Bolt_Boltpay_Block_Rewrite_Onepage extends Mage_Checkout_Block_Onepage {
    /**
     * Get checkout steps codes
     *
     * @return array
     */
    protected function _getStepCodes() {
        $steps = array('login', 'billing', 'shipping', 'shipping_method');
        if (!Mage::getStoreConfig('payment/boltpay/active') || !Mage::getStoreConfig('payment/boltpay/skip_payment')) {
            array_push($steps, 'payment');
        }
        array_push($steps, 'review');
        return $steps;
    }
}
