<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trait Bolt_Boltpay_Helper_ConfigTrait
 *
 * Defines convenience functions used to access Bolt configuration values
 */
trait Bolt_Boltpay_Helper_ConfigTrait
{

    /**
     * @return bool
     */
    public function isBoltPayActive($storeId = null)
    {
        return Mage::getStoreConfigFlag('payment/boltpay/active', $storeId);
    }

    /**
     * Get publishable key used in cart page.
     *
     * @return string
     */
    public function getPublishableKeyMultiPage($storeId = null)
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $storeId);
    }

    /**
     * Get publishable key used in checkout page.
     *
     * @return string
     */
    public function getPublishableKeyOnePage($storeId = null)
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_onepage', $storeId);
    }

    /**
     * Get publishable key used in magento admin.
     *
     * @return string
     */
    public function getPublishableKeyBackOffice($storeId = null)
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_admin', $storeId);
    }

    /**
     * Get config value
     *
     * @return bool
     */
    public function shouldAddButtonEverywhere($storeId = null)
    {
        return Mage::getStoreConfigFlag('payment/boltpay/add_button_everywhere', $storeId);
    }

    /**
     *  Returns the primary color customized for Bolt
     *
     * @return string   If set, a 6 or 8 digit hexadecimal color value preceded by a '#' character, otherwise an empty string
     */
    public function getBoltPrimaryColor($storeId = null)
    {
        return $this->getExtraConfig('boltPrimaryColor', [], $storeId);
    }

    /**
     *
     * @return string
     */
    public function getAdditionalButtonClasses($storeId = null)
    {
        return Mage::getStoreConfig('payment/boltpay/button_classes', $storeId);
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout($storeId = null)
    {
        return Mage::getStoreConfigFlag('payment/boltpay/enable_product_page_checkout', $storeId);
    }

    /**
     * @return string
     */
    public function getProductPageCheckoutSelector($storeId = null)
    {
        return Mage::getStoreConfig('payment/boltpay/product_page_checkout_selector', $storeId);
    }

    /**
     * Get config value from specific bolt config and depending on checkoutType.
     *
     * @param $configPath
     * @param $checkoutType
     * @return string
     */
    public function getPaymentBoltpayConfig($configPath, $checkoutType, $storeId = null)
    {
        /** @var string $configValue */
        $configValue = Mage::getStoreConfig('payment/boltpay/'.$configPath, $storeId);

        return ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN)
            && !Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin', $storeId)
            ? '' : $configValue;
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country, $storeId = null)
    {

        if(!$this->isBoltPayActive($storeId)) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment', $storeId) == 1) {
            return true;
        }

        if (Mage::getStoreConfig('payment/boltpay/allowspecific', $storeId) == 1) {
            $availableCountries =
                explode(',', Mage::getStoreConfig('payment/boltpay/specificcountry', $storeId));
            if (!in_array($country, $availableCountries)){
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the value of a Bolt non-publicized or non-emphasized
     * configuration value after passing it through an optionally
     * defined filter method.
     *
     * @param string $configName        The name of the config as defined
     *                                  the configuration JSON
     * @param array $filterParameters   Optional set of parameters passed to
     *                                  the optionally defined filter method
     *                                  of the config
     *
     * @return mixed    Typically a string representing the config value, but
     *                  is not limited to this type.  If the config is not defined,
     *                  an empty string is returned
     */
    public function getExtraConfig($configName, $filterParameters = array(), $storeId = null) {
        /** @var Bolt_Boltpay_Model_Admin_ExtraConfig $extraConfigModel */
        $extraConfigModel = Mage::getSingleton('boltpay/admin_extraConfig');
        return $extraConfigModel->getExtraConfig($configName, $filterParameters, $storeId);
    }

    /**
     * Checking the config
     *
     * @return bool
     */
    public function canUseEverywhere($storeId = null)
    {
        $active = $this->isBoltPayActive();
        $isEverywhere = $this->shouldAddButtonEverywhere($storeId);

        return ($active && $isEverywhere);
    }

    /**
     * Get Api key configuration
     *
     * @return mixed
     */
    public function getApiKeyConfig($storeId = null)
    {
        return $this->getExtraConfig('datadogKey', [], $storeId);
    }

    /**
     * Get severity configuration
     *
     * @return array
     */
    public function getSeverityConfig($storeId = null)
    {
        $severityString = $this->getExtraConfig('datadogKeySeverity', $storeId);
        $severityString = preg_replace('/\s+/', '', $severityString);
        $severities = explode(',', $severityString);

        return $severities;
    }
}