<?php

/**
 * Class Bolt_Boltpay_Helper_Data
 *
 * Base Magento Bolt Helper class
 *
 */
class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    static $from_hooks = false;

    /**
     * Determines if the Bolt payment method can be used in the system
     *
     * @param $quote    Magento quote object
     * @return bool     true if Bolt can be used, false otherwise
     */
    public function canUseBolt($quote) {

        /**
         * If called from hooks always return true
         */
        if (self::$from_hooks) return true;

        if(!Mage::getStoreConfig('payment/boltpay/active')) {
            return false;
        }

        if(count($quote->getAllItems()) == 0) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        $quoteData = $quote->getData();
        $grandTotal = $quoteData['grand_total'];

        $min = Mage::getStoreConfig('payment/boltpay/min_order_total');
        $max = Mage::getStoreConfig('payment/boltpay/max_order_total');

        if (!empty($min) && $grandTotal < $min || !empty($max) && $grandTotal > $max) {
            return false;
        }

        if (!$this->canUseForCountry($quote->getBillingAddress()->getCountry())) {
            return false;
        }

        return true;
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country) {
        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        if (Mage::getStoreConfig('payment/boltpay/allowspecific') == 1) {
            $availableCountries =
                explode(',', Mage::getStoreConfig('payment/boltpay/specificcountry'));
            if (!in_array($country, $availableCountries)){
                return false;
            }
        }

        return true;
    }
}
