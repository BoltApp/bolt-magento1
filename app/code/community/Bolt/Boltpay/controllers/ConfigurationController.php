<?php

/**
 * Class Bolt_Boltpay_ConfigurationController
 *
 * Check Configuration for Bolt
 */
class Bolt_Boltpay_ConfigurationController extends Mage_Core_Controller_Front_Action
{

    protected $_storeId = null;

    /**
     * @return mixed
     * @throws Varien_Exception
     */
    public function checkAction()
    {
        // Set storeId
        $requestData = json_decode(file_get_contents('php://input'), true);
        $this->_storeId = @$requestData['store_id'];

        $responseData = array(
            'result' => true
        );

        // Validate for API key
        if (!($this->checkApiKey())) {
            $this->setErrorResponseData($responseData, 'Api Key is invalid');
        }

        // Validate for Signing Secret
        if (!($this->checkSigningSecret())) {
            $this->setErrorResponseData($responseData, 'Signing Secret is invalid');
        }

        // Validate Publishable Key - Multi-Page Checkout / Publishable Key - One Page Checkout
        if (!($this->checkPublishableKeyMultiPage())) {
            $this->setErrorResponseData($responseData, 'Publishable Key - Multi-Page Checkout is invalid');
        }
        if (!($this->checkPublishableKeyOnePage())) {
            $this->setErrorResponseData($responseData, 'Publishable Key - One Page Checkout is invalid');
        }

        // Validate database schema
        if (!($this->checkSchema())) {
            $this->setErrorResponseData($responseData, 'Schema is invalid');
        }

        if (!$responseData['result']){
            Mage::helper('boltpay/bugsnag')->notifyException(new Exception('Invalid configuration'), $responseData);
        }

        $response = Mage::helper('core')->jsonEncode($responseData);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($response);
    }


    /**
     * Transmit sign request to bolt to check whether api key is correct
     * @return bool
     */
    protected function checkApiKey()
    {
        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');

        $signRequest = array(
            'merchant_user_id' => 'USER_ID_TEST_' . time(),
        );

        try {
            $signResponse = $boltHelper->transmit('sign', $signRequest, 'merchant', 'merchant', $this->_storeId);
        } catch (\Exception $e) {
            return false;
        }

        if (!$signResponse) {
            return false;
        }

        return true;
    }


    /**
     * @return bool
     */
    protected function checkSigningSecret()
    {
        // Currently there isn't a way to validate the signing secret since this must come from Bolt.
        return true;
    }

    /**
     * @return bool
     */
    protected function checkPublishableKeyMultiPage()
    {
        $keyMultiplePage = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $this->_storeId);

        return !$keyMultiplePage || $this->curlCheckPublishableKey($keyMultiplePage);
    }

    /**
     * Calls the Bolt API endpoint.
     *
     * @param $key
     * @return mixed thrown if an error is detected in a response
     */
    protected function curlCheckPublishableKey($key)
    {
        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');

        $url = $boltHelper->getApiUrl($this->_storeId) . 'v1/merchant';

        $ch = curl_init($url);

        $headerInfo = array(
            "X-Publishable-Key: $key"
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerInfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        curl_exec($ch);
        $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return (int)($response / 100) == 2;
    }

    /**
     * @return bool
     */
    protected function checkPublishableKeyOnePage()
    {
        $keyOnePage = Mage::getStoreConfig('payment/boltpay/publishable_key_onepage', $this->_storeId);

        return !$keyOnePage || $this->curlCheckPublishableKey($keyOnePage);
    }

    /**
     * user_session_id, parent_quote_id columns exists in sales_flat_quote table
     * bolt_user_id attribute exists in customer attribute (eva_attribute)
     * status deferred data exists in sales_order_status
     * state deferred data exists in sales_order_status_state
     * @return bool
     */
    protected function checkSchema()
    {
        /** @var Mage_Eav_Model_Entity_Setup $setup */
        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
        $setup->startSetup();

        $quoteTable = $setup->getTable('sales_flat_quote');
        $connection = $setup->getConnection();

        if (!$connection->tableColumnExists($quoteTable, 'user_session_id')) {
            return false;
        }
        if (!$connection->tableColumnExists($quoteTable, 'parent_quote_id')) {
            return false;
        }

        $boltUserIdAttr = $setup->getAttribute('customer', 'bolt_user_id');
        if (!$boltUserIdAttr) {
            return false;
        }

        $statusTable = $setup->getTable('sales_order_status');
        $query = $connection->query("SELECT * FROM $statusTable WHERE status = 'deferred'");
        $resultData = $query->fetchAll();
        if (!count($resultData)) {
            return false;
        }

        $statusTable = $setup->getTable('sales_order_status_state');
        $query = $connection->query("SELECT * FROM $statusTable WHERE state = 'deferred'");
        $resultData = $query->fetchAll();
        if (!count($resultData)) {
            return false;
        }

        $setup->endSetup();

        return true;
    }

    /**
     * set error response data
     * @param array $responseData
     * @param $message
     * @return array
     */
    protected function setErrorResponseData(&$responseData, $message)
    {
        $responseData['result'] = false;
        $responseData['message'][] = Mage::helper('boltpay')->__($message);
    }

}
