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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
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
    public function isBoltPayActive()
    {
        return
            Mage::getSingleton("boltpay/featureSwitch")->isSwitchEnabled(Bolt_Boltpay_Model_FeatureSwitch::BOLT_ENABLED_SWITCH_NAME)
            && Mage::getStoreConfigFlag('payment/boltpay/active');
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
     * Returns the primary color customized for Bolt
     *
     * @return string If set, a 6 or 8 digit hexadecimal color value preceded by a '#' character, otherwise an empty string
     */
    public function getBoltPrimaryColor()
    {
        return $this->getExtraConfig('boltPrimaryColor');
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
     * @return mixed
     */
    public function getAutoCreateInvoiceAfterCreatingShipment()
    {
        return Mage::getStoreConfig('payment/boltpay/auto_create_invoice_after_creating_shipment');
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
        $configValue = Mage::getStoreConfig('payment/boltpay/' . $configPath);
        $isAdminCheckout = $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $useJsInAdmin = Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin');

        return $isAdminCheckout && !$useJsInAdmin ? '' : $configValue;
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
     * @param string $country the country to be compared in check for allowing Bolt as a payment method
     *
     * @return bool true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country)
    {
        if (!$this->isBoltPayActive()) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        if (Mage::getStoreConfig('payment/boltpay/allowspecific') == 1) {
            $availableCountries = explode(',', Mage::getStoreConfig('payment/boltpay/specificcountry'));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the value of a Bolt non-publicized or non-emphasized configuration value after passing it through an
     * optionally defined filter method.
     *
     * @param string $configName       The name of the config as defined in the configuration JSON
     * @param array  $filterParameters Optional set of parameters passed to the optionally defined filter method of the config
     *
     * @return mixed The config value. If the config is not defined, an empty string is returned.
     */
    public function getExtraConfig($configName, $filterParameters = array())
    {
        /** @var Bolt_Boltpay_Model_Admin_ExtraConfig $extraConfigModel */
        $extraConfigModel = Mage::getSingleton('boltpay/admin_extraConfig');
        return $extraConfigModel->getExtraConfig($configName, $filterParameters);
    }

    /**
     * @return bool
     */
    public function canUseEverywhere()
    {
        $active = $this->isBoltPayActive();
        $isEverywhere = $this->shouldAddButtonEverywhere();

        return $active && $isEverywhere;
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
        return explode(',', $severityString);
    }

    /**
     * Get Bolt plugin version
     *
     * @return string|null
     */
    public static function getBoltPluginVersion()
    {
        $versionElm = Mage::getConfig()->getModuleConfig("Bolt_Boltpay")->xpath("version");

        if (isset($versionElm[0])) {
            return (string)$versionElm[0];
        }

        return null;
    }

    /**
     * Returns message to be displayed to customer if attempting to checkout with order lower than minimum amount
     *
     * @return string
     */
    public function getMinOrderDescriptionMessage()
    {
        return Mage::getStoreConfig('sales/minimum_order/description')
            ?: Mage::helper('checkout')->__(
                'Minimum order amount is %s',
                $this->getMinOrderAmountInStoreCurrency()
            );
    }

    /**
     * Return minimum order amount in current store currency
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getMinOrderAmountInStoreCurrency($storeId = null)
    {
        $amount = Mage::getStoreConfig('sales/minimum_order/amount', $storeId);
        try {
            return Mage::app()->getLocale()
                ->currency(Mage::app()->getStore($storeId)->getCurrentCurrencyCode())
                ->toCurrency($amount);
        } catch (Exception $e) {
            return $amount;
        }
    }
}
