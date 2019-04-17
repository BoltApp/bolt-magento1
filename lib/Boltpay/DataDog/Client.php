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

    /**
     * DataDog_Client constructor.
     *
     * @param $apiKey
     * @param array $data
     */
    public function __construct($apiKey, $data = array())
    {
        $this->_apiKey = $apiKey;
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
        if (DataDog_Request::isRequest()) {
            $data = DataDog_Request::getRequestMetaData();
        }

        $data['message'] = $message;
        $data['status'] = $type;
        $data['service'] = $this->getData('service');
        $data['merchant_platform'] = $this->getData('platform-version');
        $data['bolt-plugin-version'] = $this->getData('bolt-plugin-version');
        $data['kubernetes']['namespace_name'] = $this->getData('env');
        $data['store_url'] = $this->getData('store_url');

        $jsonData = json_encode(array_merge($data, $additionalData));
        $this->postWithCurl($jsonData);

        return $this;
    }

    /**
     * Log information
     *
     * @param $message
     * @param array $additionalData
     * @return $this
     */
    public function logInfo($message, $additionalData = array())
    {
        $this->log($message, DataDog_ErrorTypes::TYPE_INFO, $additionalData);

        return $this;
    }

    /**
     * Log warning
     *
     * @param $message
     * @param array $additionalData
     * @return $this
     */
    public function logWarning($message, $additionalData = array())
    {
        $this->log($message, DataDog_ErrorTypes::TYPE_WARNING, $additionalData);

        return $this;
    }

    /**
     * Log exception
     *
     * @param Exception $e
     * @param array $additionalData
     * @return $this
     */
    public function logError(Exception $e, $additionalData = array())
    {
        $additionalData = array_merge(
            array(
                'error.stack' => $e->getTraceAsString(),
                'error.kind' => get_class($e)
            ),
            $additionalData
        );
        $this->log($e->getMessage(), DataDog_ErrorTypes::TYPE_ERROR, $additionalData);

        return $this;
    }

    /**
     * Post the given info to DataDog using cURL.
     *
     * @param $body
     */
    public function postWithCurl($body)
    {
        $url = self::URL . $this->_apiKey;
        $http = curl_init($url);
        // Default curl settings
        curl_setopt($http, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($http, CURLOPT_POST, true);
        curl_setopt($http, CURLOPT_POSTFIELDS, $body);
        // Execute the request and fetch the response
        $responseBody = curl_exec($http);
        $statusCode = curl_getinfo($http, CURLINFO_HTTP_CODE);
        if ($statusCode > 200) {
            error_log('DataDog Warning: Couldn\'t notify (' . $responseBody . ')');
            $this->setLastResponseStatus(false);
        }elseif (curl_errno($http)) {
            error_log('DataDog Warning: Couldn\'t notify (' . curl_error($http) . ')');
            $this->setLastResponseStatus(false);
        }else{
            $this->setLastResponseStatus(true);
        }

        curl_close($http);
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