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
 * Class Bolt_Boltpay_ConfigurationController
 *
 * Check Configuration for Bolt
 */
class Bolt_Boltpay_ConfigurationController
    extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /** @var string Message that is returned when any part of the configuration is invalid */
    const INVALID_CONFIGURATION_MESSAGE = 'Invalid configuration';

    /** @var string Message that is returned when API key is invalid */
    const INVALID_API_KEY_MESSAGE = 'Api Key is invalid';

    /** @var string Message that is returned when signing secret is invalid */
    const INVALID_SIGNING_SECRET_MESSAGE = 'Signing Secret is invalid';

    /** @var string Message that is returned when multi page checkout publishable key is invalid */
    const INVALID_MULTI_PAGE_PUBLISHABLE_KEY_MESSAGE = 'Publishable Key - Multi-Page Checkout is invalid';

    /** @var string Message that is returned when multi page checkout publishable key is invalid */
    const INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE = 'Publishable Key - One Page Checkout is invalid';

    /** @var string Message that is returned when database schema is invalid */
    const INVALID_SCHEMA_MESSAGE = 'Schema is invalid';

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
            $this->setErrorResponseData($responseData, $this->boltHelper()->__(self::INVALID_API_KEY_MESSAGE));
        }

        // Validate for Signing Secret
        if (!($this->checkSigningSecret())) {
            $this->setErrorResponseData($responseData, $this->boltHelper()->__(self::INVALID_SIGNING_SECRET_MESSAGE));
        }

        // Validate Publishable Key - Multi-Page Checkout / Publishable Key - One Page Checkout
        if (!($this->checkPublishableKeyMultiPage())) {
            $this->setErrorResponseData($responseData, $this->boltHelper()->__(self::INVALID_MULTI_PAGE_PUBLISHABLE_KEY_MESSAGE));
        }
        if (!($this->checkPublishableKeyOnePage())) {
            $this->setErrorResponseData($responseData, $this->boltHelper()->__(self::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE));
        }

        // Validate database schema
        if (!($this->checkSchema())) {
            $this->setErrorResponseData($responseData, $this->boltHelper()->__(self::INVALID_SCHEMA_MESSAGE));
        }

        if (!$responseData['result']){
            $msg = $this->boltHelper()->__(self::INVALID_CONFIGURATION_MESSAGE);
            $this->boltHelper()->notifyException(new Exception($msg), $responseData);
            $this->boltHelper()->logWarning($msg);
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
        $signRequest = array(
            'merchant_user_id' => 'USER_ID_TEST_' . time(),
        );

        try {
            $signResponse = $this->boltHelper()->transmit('sign', $signRequest, 'merchant', 'merchant', $this->_storeId);
        } catch (\Exception $e) {
            return false;
        }

        if (!$signResponse) {
            return false;
        }

        return true;
    }

    /**
     * Checks that a signing secret has at least been set
     * Currently there isn't a way to validate the signing secret since this must come from Bolt.
     *
     * @return bool true if a signing secret was set in the admin, otherwise false
     */
    protected function checkSigningSecret()
    {
        return (bool)(Mage::getStoreConfig('payment/boltpay/signing_key', $this->_storeId));
    }

    /**
     * @return bool
     */
    protected function checkPublishableKeyMultiPage()
    {
        $keyMultiplePage = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $this->_storeId);

        return !$keyMultiplePage || $this->checkPublishableKey($keyMultiplePage);
    }

    /**
     * Calls the Bolt API endpoint.
     * @param $key
     * @return bool|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function checkPublishableKey($key)
    {
        $url = $this->boltHelper()->getApiUrl($this->_storeId) . 'v1/merchant';

        $headerInfo = array(
            'X-Publishable-Key'=> $key,
        );

        try{
            $client = $this->boltHelper()->getApiClient();
            $response = $client->get($url,$headerInfo)->getStatusCode();
            return (int)($response / 100) == 2;
        }catch (\Exception $exception){
            $this->boltHelper()->notifyException($exception);
            $this->boltHelper()->logException($exception);

            return false;
        }
    }

    /**
     * @return bool
     */
    protected function checkPublishableKeyOnePage()
    {
        $keyOnePage = Mage::getStoreConfig('payment/boltpay/publishable_key_onepage', $this->_storeId);

        return !$keyOnePage || $this->checkPublishableKey($keyOnePage);
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
        $query = $connection->query("SELECT * FROM $statusTable WHERE status = 'deferred'");
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
        $responseData['message'][] = $this->boltHelper()->__($message);
    }

}