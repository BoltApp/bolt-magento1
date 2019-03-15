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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trait Bolt_Boltpay_Helper_ApiTrait
 *
 * Provides utility methods related to the Bolt and Merchant side API's including:
 *
 * 1. Fetching the transaction info by calling the Fetch Bolt API endpoint.
 * 2. Verifying Hook Requests.
 * 3. Making the calls towards Bolt API.
 * 4. Generates Bolt order submission data.
 *
 */
trait Bolt_Boltpay_Helper_ApiTrait {

    use Bolt_Boltpay_Helper_UrlTrait;

    protected $curlHeaders;
    protected $curlBody;

    /**
     * A call to Fetch Bolt API endpoint. Gets the transaction info.
     *
     * @param string $reference        Bolt transaction reference
     *
     * @throws Exception  thrown if a call fails
     * @return bool|mixed Transaction info
     */
    public function fetchTransaction($reference)
    {
        return $this->transmit($reference, null);
    }

    /**
     * Verifying Hook Requests using pre-exchanged signing secret key.
     *
     * @param $payload
     * @param $hmacHeader
     * @return bool
     */
    private function verify_hook_secret($payload, $hmacHeader)
    {
        $signingSecret = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/signing_key'));
        $computedHmac  = trim(base64_encode(hash_hmac('sha256', $payload, $signingSecret, true)));

        return $hmacHeader == $computedHmac;
    }

    /**
     * Verifying Hook Requests via API call.
     *
     * @param $payload
     * @param $hmacHeader
     * @return bool if signature is verified
     */
    private function verify_hook_api($payload, $hmacHeader)
    {
        try {
            $url = $this->getApiUrl() . "/v1/merchant/verify_signature";

            $key = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/api_key'));

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $httpHeader = array(
                "X-Api-Key: $key",
                "X-Bolt-Hmac-Sha256: $hmacHeader",
                "Content-type: application/json",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $this->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-header'=>$httpHeader)),true);
            $this->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-data'=>$payload)),true);
            $result = curl_exec($ch);

            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->setCurlResultWithHeader($ch, $result);

            $resultJSON = $this->getCurlJSONBody();
            $this->addMetaData(array('BOLT API RESPONSE' => array('verify-hook-api-response'=>$resultJSON)),true);

            return $response == 200;
        } catch (Exception $e) {
            $this->notifyException($e);
            return false;
        }

    }

    /**
     * Verifying Hook Requests. If signing secret is not defined fallback to api call.
     *
     * @param $payload
     * @param $hmacHeader
     * @return bool
     */
    public function verify_hook($payload, $hmacHeader)
    {
        return $this->verify_hook_secret($payload, $hmacHeader) || $this->verify_hook_api($payload, $hmacHeader);
    }

    /**
     * Calls the Bolt API endpoint.
     *
     * @param string $command  The endpoint to be called
     * @param string $data     an object to be encoded to JSON as the value passed to the endpoint
     * @param string $object   defines part of endpoint url which is normally/always??? set to merchant
     * @param string $type     Defines the endpoint type (i.e. order|transactions|sign) that is used as part of the url
     * @param null $storeId
     * @throws  Mage_Core_Exception  thrown if an error is detected in a response
     * @return mixed           Object derived from Json got as a response
     */
    public function transmit($command, $data, $object='merchant', $type='transactions', $storeId = null)
    {
        $url = $this->getApiUrl($storeId) . 'v1/';

        if($command == 'sign' || $command == 'orders') {
            $url .= $object . '/' . $command;
        } elseif ($command == null || $command == '') {
            $url .= $object;
        } else {
            $url .= $object . '/' . $type . '/' . $command;
        }

        //Mage::log(sprintf("Making an API call to %s", $url), null, 'bolt.log');

        $ch = curl_init($url);
        $params = "";
        if ($data != null) {
            $params = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        if ($command == '' && $type == '' && $object == 'merchant') {
            $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $storeId);
        } else {
            $key = Mage::getStoreConfig('payment/boltpay/api_key', $storeId);
        }

        $contextInfo = $this->getContextInfo();
        $headerInfo = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'X-Api-Key: ' . Mage::helper('core')->decrypt($key),
            'X-Nonce: ' . rand(100000000, 999999999),
            'User-Agent: BoltPay/Magento-' . $contextInfo["Magento-Version"] . '/' . $contextInfo["Bolt-Plugin-Version"],
            'X-Bolt-Plugin-Version: ' . $contextInfo["Bolt-Plugin-Version"]
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerInfo);
        $this->addMetaData(array('BOLT API REQUEST' => array('header'=>$headerInfo)));
        $this->addMetaData(array('BOLT API REQUEST' => array('data'=>$data)),true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            $curlInfo = var_export(curl_getinfo($ch), true);
            curl_close($ch);

            $message ="Curl info: " . $curlInfo;

            Mage::throwException($message);
        }

        $this->setCurlResultWithHeader($ch, $result);

        $resultJSON = $this->getCurlJSONBody();
        $this->addMetaData(array('BOLT API RESPONSE' => array('BOLT-RESPONSE'=>$resultJSON)),true);
        $jsonError = $this->handleJSONParseError();
        if ($jsonError != null) {
            curl_close($ch);
            $message ="JSON Parse Type: " . $jsonError . " Response: " . $result;
            Mage::throwException($message);
        }

        curl_close($ch);
        Mage::getModel('boltpay/payment')->debugData($resultJSON);

        return $this->_handleErrorResponse($resultJSON, $url, $params);
    }

    /**
     * Sets Plugin information in the response headers to callers of the API
     */
    public function setResponseContextHeaders()
    {
        $contextInfo = $this->getContextInfo();

        Mage::app()->getResponse()
            ->setHeader('User-Agent', 'BoltPay/Magento-' . $contextInfo["Magento-Version"] . '/' . $contextInfo["Bolt-Plugin-Version"], true)
            ->setHeader('X-Bolt-Plugin-Version', $contextInfo["Bolt-Plugin-Version"], true);
    }

    protected function setCurlResultWithHeader($curlResource, $result)
    {
        $curlHeaderSize = curl_getinfo($curlResource, CURLINFO_HEADER_SIZE);

        $this->curlHeaders = substr($result, 0, $curlHeaderSize);
        $this->curlBody = substr($result, $curlHeaderSize);
    }

    protected function getCurlJSONBody()
    {
        return json_decode($this->curlBody);
    }

    /**
     * Bolt Api call response wrapper method that checks for potential error responses.
     *
     * @param mixed $response   A response received from calling a Bolt endpoint
     * @param $url
     *
     * @throws  Mage_Core_Exception  thrown if an error is detected in a response
     * @return mixed  If there is no error then the response is returned unaltered.
     */
    private function _handleErrorResponse($response, $url, $request)
    {
        if (is_null($response)) {
            $message = $this->__("BoltPay Gateway error: No response from Bolt. Please re-try again");
            Mage::throwException($message);
        } elseif (self::isResponseError($response)) {
            if (property_exists($response, 'errors')) {
                Mage::unregister("bolt_api_error");
                Mage::register("bolt_api_error", $response->errors[0]->message);
            }

            $message = $this->__("BoltPay Gateway error for %s: Request: %s, Response: %s", $url, $request, json_encode($response, true));

            $this->notifyException(new Exception($message));
            Mage::throwException($message);
        }

        return $response;
    }

    /**
     * A helper methond for checking errors in JSON object.
     *
     * @return null|string
     */
    public function handleJSONParseError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return null;

            case JSON_ERROR_DEPTH:
                return $this->__('Maximum stack depth exceeded');

            case JSON_ERROR_STATE_MISMATCH:
                return $this->__('Underflow or the modes mismatch');

            case JSON_ERROR_CTRL_CHAR:
                return $this->__('Unexpected control character found');

            case JSON_ERROR_SYNTAX:
                return $this->__('Syntax error, malformed JSON');

            case JSON_ERROR_UTF8:
                return $this->__('Malformed UTF-8 characters, possibly incorrectly encoded');

            default:
                return $this->__('Unknown error');
        }
    }

    /**
     * Checks if the Bolt API response indicates an error.
     *
     * @param $response     Bolt API response
     * @return bool         true if there is an error, false otherwise
     */
    private function isResponseError($response)
    {
        return property_exists($response, 'errors') || property_exists($response, 'error_code');
    }

}