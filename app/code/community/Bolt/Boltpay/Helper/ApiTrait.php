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
trait Bolt_Boltpay_Helper_ApiTrait
{
    use Bolt_Boltpay_Helper_UrlTrait;

    protected $apiClient;

    /**
     * Function get Api Client
     *
     * @return Boltpay_Guzzle_ApiClient
     */
    public function getApiClient()
    {
        if (!$this->apiClient) {
            $this->apiClient = new Boltpay_Guzzle_ApiClient();
        }

        return $this->apiClient;
    }

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

            $httpHeader =array(
                'X-Api-Key'=> $key,
                'X-Bolt-Hmac-Sha256'=> $hmacHeader,
                'X-Nonce'=> rand(100000000, 999999999)
            );

            $this->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-header'=>$httpHeader)),true);
            $this->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-data'=>$payload)),true);
            $apiClient = $this->getApiClient()->post($url,$payload,$httpHeader);
            $this->addMetaData(array('BOLT API RESPONSE' => array('verify-hook-api-response'=>(string)$apiClient->getBody())),true);

            return $apiClient->getStatusCode() == 200;
        } catch (Exception $e) {
            $this->notifyException($e);
            $this->logException($e);
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
     * @return mixed           Object derived from Json got as a response
     * @throws \GuzzleHttp\Exception\GuzzleException thrown if an error is detected in a response
     */
    public function transmit($command, $data, $object='merchant', $type='transactions', $storeId = null)
    {
        try {
            $url = $this->getApiUrl($storeId) . 'v1/';

            if($command == 'sign' || $command == 'orders') {
                $url .= $object . '/' . $command;
            } elseif ($command == null || $command == '') {
                $url .= $object;
            } else {
                $url .= $object . '/' . $type . '/' . $command;
            }

            $params = "";
            if ($data != null) {
                $params = json_encode($data);
            }

            if ($command == '' && $type == '' && $object == 'merchant') {
                $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $storeId);
            } else {
                $key = Mage::getStoreConfig('payment/boltpay/api_key', $storeId);
            }

            $contextInfo = $this->getContextInfo();


            $headerInfo = array(
                'User-Agent' => 'BoltPay/Magento-' . $contextInfo["Magento-Version"] . '/' . $contextInfo["Bolt-Plugin-Version"],
                'Content-Length'=>strlen($params),
                'X-Nonce'=> rand(100000000, 999999999),
                'X-Bolt-Plugin-Version'=>$contextInfo["Bolt-Plugin-Version"],
                'X-Api-Key' => Mage::helper('core')->decrypt($key),
            );

            $this->addMetaData(array('BOLT API REQUEST' => array('header'=>$headerInfo)));
            $this->addMetaData(array('BOLT API REQUEST' => array('data'=>$data)),true);
            if($params){
                $response =  (string)$this->getApiClient()->post($url,$params,$headerInfo)->getBody();
            }else{
                $response =  (string)$this->getApiClient()->get($url,$headerInfo)->getBody();
            }

            $resultJSON = json_decode($response);

            $this->addMetaData(array('BOLT API RESPONSE' => array('verify-hook-api-response'=>$resultJSON)),true);
            Mage::getModel('boltpay/payment')->debugData($resultJSON);

            return $resultJSON;
        }catch (\Exception $e){
            $this->notifyException($e);
            $this->logException($e);
            throw $e;
        }
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