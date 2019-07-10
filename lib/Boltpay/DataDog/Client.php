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

class DataDog_Client
{
    const URL = 'https://http-intake.logs.datadoghq.com/v1/input/';
    private $_apiKey;
    private $_data = array();
    private $_severityConfig;

    /**
     * DataDog_Client constructor.
     *
     * @param $apiKey
     * @param array $data
     */
    public function __construct($apiKey, $severityConfig, $data = array())
    {
        $this->_apiKey = $apiKey;
        $this->_severityConfig = $severityConfig;
        $this->_data = $data;
        $this->_data['last_response_status'] = null;
    }

    /**
     * @param $message
     * @param string $type
     * @param array $additionalData
     * @return $this
     */
    public function log($message, $type = DataDog_ErrorTypes::TYPE_INFO, $additionalData = array())
    {
        if ($this->_apiKey && !in_array($type, $this->_severityConfig)) {
            return $this->setLastResponseStatus(false);
        };

        if (DataDog_Request::isRequest()) {
            $data = DataDog_Request::getRequestMetaData();
        }

        $data['message'] = addcslashes($message, '{}"');
        $data['status'] = $type;
        $data['service'] = $this->getData('service');
        $data['merchant_platform'] = $this->getData('platform-version');
        $data['bolt-plugin-version'] = $this->getData('bolt-plugin-version');
        $data['kubernetes']['namespace_name'] = $this->getData('env');
        $data['store_url'] = $this->getData('store_url');

        $jsonData = json_encode(array_merge($data, $additionalData));
        $this->postWithGuzzle($jsonData);

        return $this;
    }

    /**
     * Post the given info to DataDog using Guzzle.
     *
     * @param $body
     */
    public function postWithGuzzle($body)
    {
        if ($this->getData('env') == DataDog_Environment::TEST_ENVIRONMENT){
            return $this->setLastResponseStatus(true);
        }

        try {
            $client = new \BoltPay\Guzzle\ApiClient();
            $client->post(self::URL . $this->_apiKey, $body);
            $this->setLastResponseStatus(true);

        }catch (\Exception $exception){
            $this->setLastResponseStatus(false);
        }
    }

    /**
     * Set last response status
     *
     * @param $responseStatus
     * @return DataDog_Client
     */
    public function setLastResponseStatus($responseStatus)
    {
        $this->setData('last_response_status', $responseStatus);

        return $this;
    }

    /**
     * Get last response status
     *
     * @return mixed|string
     */
    public function getLastResponseStatus()
    {
        return $this->getData('last_response_status');
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
}