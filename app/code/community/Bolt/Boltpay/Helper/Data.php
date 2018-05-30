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
     * @var string The Bolt sandbox url for the javascript
     */
    const JS_URL_TEST = 'https://connect-sandbox.bolt.com';

    /**
     * @var string The Bolt production url for the javascript
     */
    const JS_URL_PROD = 'https://connect.bolt.com';

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

        return Mage::getStoreConfigFlag('payment/boltpay/active')
            && (!$checkCountry || ($checkCountry && $this->canUseForCountry($quote->getBillingAddress()->getCountry())))
            && (Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD')
            && (Mage::app()->getStore()->getBaseCurrencyCode() == 'USD');
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country) 
    {

        if(!Mage::getStoreConfig('payment/boltpay/active')) {
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
     * Get config value
     *
     * @return bool
     */
    public function isNeedAddButtonToMiniCart()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/add_button_to_minicart');
    }

    /**
     * @param bool $decrypt
     * @return string
     */
    public function getPublishableKeyMultiPageKey($decrypt = false)
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        if ($decrypt) {
            return Mage::helper('core')->decrypt($key);
        }

        return $key;
    }

    /**
     * @return array
     */
    public function getReplacementButtonSelectorsInMiniCart()
    {
        if (!$this->isNeedAddButtonToMiniCart()) {
            return array();
        }

        $selectors = Mage::getStoreConfig('payment/boltpay/replace_minicart_button_selectors');

        $data = array_map('trim', explode(',', $selectors));

        return $data;
    }

    /**
     * @return string
     */
    public function getConnectJsUrl()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/test') ?
            self::JS_URL_TEST . "/connect.js":
            self::JS_URL_PROD . "/connect.js";
    }

    /**
     * @return bool
     */
    public function isRequireWrapperTagForTemplate()
    {
        $isRequireWrapper = Mage::getStoreConfigFlag('payment/boltpay/is_require_wrapper_tag');

        return ($this->isNeedAddButtonToMiniCart() && $isRequireWrapper);
    }

    /**
     * Returns Extra CSS from configuration.
     * @return string
     */
    function getMiniCartExtraCSS()
    {
        $configValue = trim(Mage::getStoreConfig('payment/boltpay/minicart_extra_css'));

        return ($this->isNeedAddButtonToMiniCart() && $configValue) ? $configValue : '';
    }
}
