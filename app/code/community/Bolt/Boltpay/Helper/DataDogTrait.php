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

trait Bolt_Boltpay_Helper_DataDogTrait
{
    public static $defaultDataDogKey = '66d80ae8d0278e3ee2d23e65649b7256';
    public static $defaultSeverityConfig = 'error';
    private $_apiKey;
    private $_severityConfig;
    private $_data = array();
    private $_datadog;

    /**
     * Log information
     *
     * @param $message
     * @param array $additionalData
     * @return Boltpay_DataDog_Client
     */
    public function logInfo($message, $additionalData = array())
    {
        return $this->getDataDog()->log($message, Boltpay_DataDog_ErrorTypes::TYPE_INFO, $additionalData);
    }

    /**
     * Log warning
     *
     * @param $message
     * @param array $additionalData
     * @return Boltpay_DataDog_Client
     */
    public function logWarning($message, $additionalData = array())
    {
        return $this->getDataDog()->log($message, Boltpay_DataDog_ErrorTypes::TYPE_WARNING, $additionalData);
    }

    /**
     * Log exception
     *
     * @param Exception $e
     * @param array $additionalData
     * @return Boltpay_DataDog_Client
     */
    public function logException(Exception $e, $additionalData = array())
    {
        $additionalData = array_merge(
            array(
                'error.stack' => $e->getTraceAsString(),
                'error.kind' => get_class($e)
            ),
            $additionalData
        );

        return $this->getDataDog()->log($e->getMessage(), Boltpay_DataDog_ErrorTypes::TYPE_ERROR, $additionalData);
    }

    /**
     * Get DataDog
     *
     * @return Boltpay_DataDog_Client
     */
    private function getDataDog()
    {
        if (!$this->_datadog) {
            $this->init();
            $this->_datadog = new Boltpay_DataDog_Client($this->_apiKey, $this->_severityConfig, $this->_data);
        }

        return $this->_datadog;
    }

    private function init()
    {
        $this->_apiKey = $this->getApiKeyConfig();
        $this->_severityConfig = $this->getSeverityConfig();
        $this->_data['platform-version'] = 'Magento ' . Mage::getVersion();
        $this->_data['bolt-plugin-version'] = static::getPluginVersion();
        $this->_data['store_url'] = Mage::getBaseUrl();
        $this->_data['service'] = 'plugin-magento1';
        if (isset($_SERVER['PHPUNIT_ENVIRONMENT']) && $_SERVER['PHPUNIT_ENVIRONMENT']) {
            $this->_data['env'] = Boltpay_DataDog_Environment::TEST_ENVIRONMENT;
        } else {
            $this->_data['env'] = Mage::getStoreConfig('payment/boltpay/test') ?
                          Boltpay_DataDog_Environment::DEVELOPMENT_ENVIRONMENT :
                          Boltpay_DataDog_Environment::PRODUCTION_ENVIRONMENT;
        }
    }


    /**
     * @return string|null
     */
    protected static function getPluginVersion() {
        $versionElm =  Mage::getConfig()->getModuleConfig("Bolt_Boltpay")->xpath("version");

        if(isset($versionElm[0])) {
            return (string)$versionElm[0];
        }

        return null;
    }
}