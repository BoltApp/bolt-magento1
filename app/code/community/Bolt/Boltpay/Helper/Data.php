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
 * Class Bolt_Boltpay_Helper_Data
 *
 * Base Magento Bolt Helper class
 *
 */
class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    static $fromHooks = false;

    /**
     * Determines if the Bolt payment method can be used in the system
     *
     * @param Mage_Sales_Model_Quote $quote         Magento quote object
     * @param bool                   $checkCountry Set to true if the billing country should be checked, otherwise false
     *
     * @return bool     true if Bolt can be used, false otherwise
     *
     * TODO: consider store base currency and possibly add conversion logic
     * @throws Mage_Core_Model_Store_Exception
     */
    public function canUseBolt($quote, $checkCountry = true)
    {
        /**
         * If called from hooks always return true
         */
        if (self::$fromHooks) return true;

        return $this->isBoltPayActive()
            && (!$checkCountry || ($checkCountry && $this->canUseForCountry($quote->getBillingAddress()->getCountry())))
            && (Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD')
            && (Mage::app()->getStore()->getBaseCurrencyCode() == 'USD');
    }

    /**
     * @return bool
     */
    public function isBoltPayActive()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/active');
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country)
    {

        if(!$this->isBoltPayActive()) {
            return false;
        }

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

    /**
     * Resets rounding deltas before calling collect totals which fixes bug in collectTotals that causes rounding errors
     * when a percentage discount is applied to a quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param bool                   $clearTotalsCollectedFlag
     * @return Mage_Sales_Model_Quote
     */
    public function collectTotals($quote, $clearTotalsCollectedFlag = false)
    {
        Mage::getSingleton('salesrule/validator')->resetRoundingDeltas();

        if($clearTotalsCollectedFlag) {
            $quote->setTotalsCollectedFlag(false);
        }

        $quote->collectTotals();

        return $quote;
    }

    /**
     * Get config value
     *
     * @return bool
     */
    public function shouldAddButtonEverywhere()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/add_button_everywhere');
    }

    /**
     * Decrypt key
     *
     * @param $key
     * @return string
     */
    private function decryptKey($key)
    {
        return Mage::helper('core')->decrypt($key);
    }

    /**
     * Get publishable key used in cart page.
     *
     * @return string
     */
    public function getPublishableKeyMultiPage()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        return $this->decryptKey($key);
    }

    /**
     * Get publishable key used in checkout page.
     *
     * @return string
     */
    public function getPublishableKeyOnePage()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_onepage');
        return $this->decryptKey($key);
    }

    /**
     * Get publishable key used in magento admin.
     *
     * @return string
     */
    public function getPublishableKeyBackOffice()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_admin');
        return $this->decryptKey($key);
    }

    /**
     * Gets the connect.js url depending on the sandbox state of the application
     *
     * @return string  Sandbox connect.js URL for Sanbox mode, otherwise production
     */
    public function getConnectJsUrl()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/test') ?
            Bolt_Boltpay_Block_Checkout_Boltpay::JS_URL_TEST . "/connect.js":
            Bolt_Boltpay_Block_Checkout_Boltpay::JS_URL_PROD . "/connect.js";
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($checkoutType = 'multi-page')
    {
        return Mage::app()->getLayout()->createBlock('boltpay/checkout_boltpay')->getCartDataJs($checkoutType);
    }

    /**
     * Checking the config
     *
     * @return bool
     */
    public function canUseEverywhere()
    {
        $active = $this->isBoltPayActive();
        $isEverywhere = $this->shouldAddButtonEverywhere();

        return ($active && $isEverywhere);
    }
}
