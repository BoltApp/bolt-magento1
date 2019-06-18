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
trait Bolt_Boltpay_Helper_ConfigTrait {

    /**
     * @return bool
     */
    public function isBoltPayActive()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/active');
    }

    /**
     * Get publishable key used in cart page.
     *
     * @return string
     */
    public function getPublishableKeyMultiPage()
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
    }

    /**
     * Get publishable key used in checkout page.
     *
     * @return string
     */
    public function getPublishableKeyOnePage()
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_onepage');
    }

    /**
     * Get publishable key used in magento admin.
     *
     * @return string
     */
    public function getPublishableKeyBackOffice()
    {
        return Mage::getStoreConfig('payment/boltpay/publishable_key_admin');
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
     *  Returns the primary color customized for Bolt
     *
     * @return string   If set, a 6 or 8 digit hexadecimal color value preceded by a '#' character, otherwise an empty string
     */
    public function getBoltPrimaryColor()
    {
        return $this->getExtraConfig('boltPrimaryColor');
    }

    /**
     *
     * @return string
     */
    public function getAdditionalButtonClasses()
    {
        return Mage::getStoreConfig('payment/boltpay/button_classes');
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/enable_product_page_checkout');
    }

    /**
     * @return string
     */
    public function getProductPageCheckoutSelector()
    {
        return Mage::getStoreConfig('payment/boltpay/product_page_checkout_selector');
    }

    /**
     * Get config value from specific bolt config and depending on checkoutType.
     *
     * @param $configPath
     * @param $checkoutType
     * @return string
     */
    public function getPaymentBoltpayConfig($configPath, $checkoutType)
    {
        /** @var string $configValue */
        $configValue = Mage::getStoreConfig('payment/boltpay/'.$configPath);

        return ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) && !Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin') ? '' : $configValue;
    }

    /**
     * @return array
     */
    public function getAllowedButtonByCustomRoutes()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $config = $this->getPaymentBoltpayConfig('allowed_button_by_custom_routes', $checkoutType);
        // removes all NULL, FALSE and Empty Strings but leaves 0 (zero) values
        $configData = explode(',', $config);
        $result = array_values(array_filter(array_map('trim', $configData), 'strlen'));

        return (empty($result)) ? [] : $result;
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
    public function getExtraConfig($configName, $filterParameters = array() ) {
        /** @var Bolt_Boltpay_Model_Admin_ExtraConfig $extraConfigModel */
        $extraConfigModel = Mage::getSingleton('boltpay/admin_extraConfig');
        return $extraConfigModel->getExtraConfig($configName, $filterParameters);
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

    /**
     * Get Api key configuration
     *
     * @return mixed
     */
    public function getApiKeyConfig()
    {
        return $this->getExtraConfig('datadogKey');
    }

    /**
     * Get severity configuration
     *
     * @return array
     */
    public function getSeverityConfig()
    {
        $severityString = $this->getExtraConfig('datadogKeySeverity');
        $severityString = preg_replace('/\s+/', '', $severityString);
        $severities = explode(',', $severityString);

        return $severities;
    }
}