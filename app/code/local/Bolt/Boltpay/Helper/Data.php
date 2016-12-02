<?php

class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract {

    public function canUseBolt() {
        if(!Mage::getStoreConfig('payment/boltpay/active')) {
            return false;
        }

        if(Mage::helper('checkout/cart')->getItemsCount() == 0) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        $quote = Mage::getModel('checkout/session')->getQuote();
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
