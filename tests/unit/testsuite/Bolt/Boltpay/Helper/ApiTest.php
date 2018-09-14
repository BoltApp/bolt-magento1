<?php

/**
 * Class Bolt_Boltpay_Helper_ApiTest
 */
class Bolt_Boltpay_Helper_ApiTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Bolt_Boltpay_Helper_Api
     */
    private $currentMock;

    private $testBoltResponse;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        /** @var Mage_Core_Model_Store $appStore */
        $appStore = $this->app->getStore();
        $appStore->resetConfig();

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(['verify_hook_secret', 'verify_hook_api', 'getBoltContextInfo'])
            ->enableOriginalConstructor()
            ->getMock();

        $appStore->setConfig('payment/boltpay/active', 1);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');
        $testBoltResponse->cart = new StdClass();
        $testBoltResponse->external_data = new StdClass();
        $testBoltResponse->cart->order_reference = '69';
        $testBoltResponse->cart->display_id = 100001069;
        $testBoltResponse->cart->currency = [];
        $testBoltResponse->cart->items = [];

        $this->testBoltResponse = (object) $testBoltResponse;
    }

    public function testIsResponseError()
    {
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertFalse($result);
    }

    public function testIsResponseErrorWithErrors()
    {
        $this->testBoltResponse->errors = ['some_error_key' => 'some_error_message'];
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }

    public function testIsResponseErrorWithErrorCode()
    {
        $this->testBoltResponse->error_code = 10603;
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }
}