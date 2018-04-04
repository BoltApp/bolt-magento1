<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_Shippingtaxrateprovider
 *
 * The Magento model that provides utility methods for the following operations:
 *
 * 1. Getting tax rates
 */
class Bolt_Boltpay_Model_Shippingtaxrateprovider extends Mage_Core_Model_Abstract {
    /**
     * A call to Fetch Current Tax Rate
     *
     * @param Mage_Sales_Model_Quote $quote Quote to base tax rate on
     *
     * @return float Tax rate for quote
     */
    public function getTaxRate($quote) {
        $shippingTaxRateProvider = $this->getShippingTaxRateProvider($quote);

        try {
            return $shippingTaxRateProvider->getRate();
        } catch(Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);

            if(get_class($shippingTaxRateProvider) != 'Bolt_Boltpay_Model_Shippingtaxrateprovider_Default') {
                $shippingTaxRateProvider = Mage::getModel('boltpay/shippingtaxrateprovider_default')->setQuote($quote);
                return $shippingTaxRateProvider->getRate();
            }
        }
    }

    /**
     * Gets the appropriate tax rate calculation model
     *
     * @return Bolt_Boltpay_Model_Shippingtaxrateprovider_Abstract
     */
    protected function getShippingTaxRateProvider($quote) {
        $shippingAddress = $quote->getShippingAddress();

        foreach ($shippingAddress->getTotalCollector()->getCollectors() as $totalCollector) {
            switch(get_class($totalCollector)) {
                case 'OnePica_AvaTax_Model_Sales_Quote_Address_Total_Tax':
                    return Mage::getModel('boltpay/shippingtaxrateprovider_avatax')->setQuote($quote);
                case 'Taxjar_SalesTax_Model_Sales_Total_Quote_Tax':
                    return Mage::getModel('boltpay/shippingtaxrateprovider_taxjar')->setQuote($quote);
            }
        }

        return $this->getDefaultShippingTaxRateProvider($quote);
    }

    /**
     * Gets the default tax rate calculation model
     *
     * @return Bolt_Boltpay_Model_Shippingtaxrateprovider_Default
     */
    protected function getDefaultShippingTaxRateProvider($quote) {
        return Mage::getModel('boltpay/shippingtaxrateprovider_default')->setQuote($quote);
    }
}
