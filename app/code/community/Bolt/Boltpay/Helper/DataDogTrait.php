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

require_once(Mage::getBaseDir('lib') . DS . 'Boltpay/DataDog/Autoload.php');

trait Bolt_Boltpay_Helper_DataDogTrait
{
    public static $boltDataDogKey = '66d80ae8d0278e3ee2d23e65649b7256';
    private $_apiKey;
    private $_severityConfig;
    private $_data = array();
    private $_datadog;

    /**
     * Log information
     *
     * @param $message
     * @param array $additionalData
     * @return DataDog_Client
     */
    public function logInfo($message, $additionalData = array())
    {
        if ($this->_apiKey && !in_array(DataDog_ErrorTypes::TYPE_INFO, $this->_severityConfig)) {
            return $this->getDataDog()->setLastResponseStatus(false);
        }

        return $this->getDataDog()->logInfo($message, $additionalData);
    }

    /**
     * Log warning
     *
     * @param $message
     * @param array $additionalData
     * @return DataDog_Client
     */
    public function logWarning($message, $additionalData = array())
    {
        if ($this->_apiKey && !in_array(DataDog_ErrorTypes::TYPE_WARNING, $this->_severityConfig)) {
            return $this->getDataDog()->setLastResponseStatus(false);
        }

        return $this->getDataDog()->logWarning($message, $additionalData);
    }

    /**
     * Log exception
     *
     * @param Exception $e
     * @param array $additionalData
     * @return DataDog_Client
     */
    public function logError(Exception $e, $additionalData = array())
    {
        if ($this->_apiKey && !in_array(DataDog_ErrorTypes::TYPE_ERROR, $this->_severityConfig)) {
            return $this->getDataDog()->setLastResponseStatus(false);
        }

        return $this->getDataDog()->logError($e, $additionalData);
    }

    /**
     * Get DataDog
     *
     * @return DataDog_Client
     */
    private function getDataDog()
    {
        if (!$this->_datadog) {
            $this->init();
            $this->_datadog = new DataDog_Client($this->_apiKey, $this->_data);
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
        if (isset($_SERVER['PHPUNIT_ENVIRONMENT']) && $_SERVER['PHPUNIT_ENVIRONMENT']) {
            $this->_data['env'] = DataDog_Environment::TEST_ENVIRONMENT;
        } else {
            $this->_data['env'] = Mage::getStoreConfig('payment/boltpay/test') ?
                          DataDog_Environment::DEVELOPMENT_ENVIRONMENT :
                          DataDog_Environment::PRODUCTION_ENVIRONMENT;
        }
    }

    /**
     * Set log information
     *
     * @param $attribute
     * @param $value
     * @return $this
     */
    public function setData($attribute, $value)
    {
        $this->_data[$attribute] = $value;

        return $this;
    }

    /**
     * Get log information
     *
     * @param $attribute
     * @return mixed|string
     */
    public function getData($attribute)
    {
        return isset($this->_data[$attribute]) ? $this->_data[$attribute] : '';
    }

    /**
     * Enable all severity configs
     * @return $this
     */
    public function enableAllSeverityConfigs(){
        $this->_severityConfig= [
            DataDog_ErrorTypes::TYPE_INFO,
            DataDog_ErrorTypes::TYPE_WARNING,
            DataDog_ErrorTypes::TYPE_ERROR
        ];

        return $this;
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