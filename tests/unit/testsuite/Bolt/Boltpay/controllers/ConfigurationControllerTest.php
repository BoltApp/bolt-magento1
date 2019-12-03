<?php

require_once('TestHelper.php');
require_once('StreamHelper.php');
require_once('Bolt/Boltpay/controllers/ConfigurationController.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_ConfigurationController
 */
class Bolt_Boltpay_ConfigurationControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var int Dummy store id */
    const STORE_ID = 1;

    /** @var string Dummy publishable key */
    const PUBLISHABLE_KEY = 'a6fe881cecd3fb7660083aea35cce430';

    /** @var string Message that should be returned when any part of the configuration is invalid */
    const INVALID_CONFIGURATION_MESSAGE = 'Invalid configuration';

    /** @var string Message that should be returned when API key is invalid */
    const INVALID_API_KEY_MESSAGE = 'Api Key is invalid';

    /** @var string Message that should be returned when signing secret is invalid */
    const INVALID_SIGNING_SECRET_MESSAGE = 'Signing Secret is invalid';

    /** @var string Message that should be returned when multi page checkout publishable key is invalid */
    const INVALID_MULTI_PAGE_PUBLISHABLE_KEY = 'Publishable Key - Multi-Page Checkout is invalid';

    /** @var string Message that should be returned when multi page checkout publishable key is invalid */
    const INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE = 'Publishable Key - One Page Checkout is invalid';

    /** @var string Message that should be returned when database schema is invalid */
    const INVALID_SCHEMA = 'Schema is invalid';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_ConfigurationController
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Response_Http Mocked instance of response object
     */
    private $response;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $helperMock;

    /**
     * Unregister helper set from previous tests
     */
    public static function setUpBeforeClass()
    {
        Mage::unregister('_helper/boltpay');
    }


    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        Mage::app('default');

        $this->response = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader', 'setBody',))
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array('transmit', 'notifyException', 'logWarning', 'getApiClient')
            )
            ->getMock();

        Mage::register('_helper/boltpay', $this->helperMock);
    }

    /**
     * Cleanup changes made by tests
     */
    protected function tearDown()
    {
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/core/resource');
    }

    /**
     * @test
     * Check action with all combinations results for check methods
     *
     * @dataProvider checkActionDataProvider
     * @covers ::checkAction
     *
     * @param bool $checkApiKey Mocked result of checkApiKey method call
     * @param bool $checkSigningSecret Mocked result of checkSigningSecret method call
     * @param bool $checkPublishableKeyMultiPage Mocked result of checkPublishableKeyMultiPage method call
     * @param bool $checkPublishableKeyOnePage Mocked result of checkPublishableKeyOnePage method call
     * @param bool $checkSchema Mocked result of checkSchema method
     */
    public function checkAction($checkApiKey, $checkSigningSecret, $checkPublishableKeyMultiPage, $checkPublishableKeyOnePage, $checkSchema)
    {
        $currentMock = $this->getCurrentMock(
            array(
                'checkApiKey',
                'checkSigningSecret',
                'checkPublishableKeyMultiPage',
                'checkPublishableKeyOnePage',
                'checkSchema'
            )
        );
        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));

        $currentMock->expects($this->once())->method('checkApiKey')->willReturn($checkApiKey);
        $currentMock->expects($this->once())->method('checkSigningSecret')->willReturn($checkSigningSecret);
        $currentMock->expects($this->once())->method('checkPublishableKeyMultiPage')
            ->willReturn($checkPublishableKeyMultiPage);
        $currentMock->expects($this->once())->method('checkPublishableKeyOnePage')
            ->willReturn($checkPublishableKeyOnePage);
        $currentMock->expects($this->once())->method('checkSchema')->willReturn($checkSchema);

        $expectedErrorMessages = array();
        if (!$checkApiKey) {
            $expectedErrorMessages[] = self::INVALID_API_KEY_MESSAGE;
        }

        if (!$checkSigningSecret) {
            $expectedErrorMessages[] = self::INVALID_SIGNING_SECRET_MESSAGE;
        }

        if (!$checkPublishableKeyMultiPage) {
            $expectedErrorMessages[] = self::INVALID_MULTI_PAGE_PUBLISHABLE_KEY;
        }

        if (!$checkPublishableKeyOnePage) {
            $expectedErrorMessages[] = self::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE;
        }

        if (!$checkSchema) {
            $expectedErrorMessages[] = self::INVALID_SCHEMA;
        }

        if (empty($expectedErrorMessages)) {
            $this->expectSuccessResponse();
        } else {
            $this->expectErrorResponse($expectedErrorMessages);
        }

        Bolt_Boltpay_StreamHelper::register();
        try {
            $currentMock->checkAction();
        } finally {
            Bolt_Boltpay_StreamHelper::restore();
        }
    }

    /**
     * @test
     * Check action when API key is invalid
     *
     * @covers ::checkAction
     * @covers ::checkApiKey
     */
    public function checkAction_invalidApiKey()
    {
        $currentMock = $this->getCurrentMock();

        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));

        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(false);

        $this->helperMock->expects($this->once())->method('logWarning')->with(self::INVALID_CONFIGURATION_MESSAGE);

        $this->expectErrorResponse(array(self::INVALID_API_KEY_MESSAGE));

        Bolt_Boltpay_StreamHelper::register();
        try {
            $currentMock->checkAction();
        } finally {
            Bolt_Boltpay_StreamHelper::restore();
        }
    }

    /**
     * @test
     * Check action when signing secret is invalid
     *
     * @covers ::checkAction
     */
    public function checkAction_invalidSigningSecret()
    {
        $currentMock = $this->getCurrentMock(array('checkSigningSecret'));

        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));

        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(true);

        $currentMock->expects($this->once())->method('checkSigningSecret')->willReturn(false);

        $this->expectErrorResponse(array(self::INVALID_SIGNING_SECRET_MESSAGE));

        Bolt_Boltpay_StreamHelper::register();
        $currentMock->checkAction();
        Bolt_Boltpay_StreamHelper::restore();
    }

    /**
     * @test
     * Check action when publishable key for multi page checkout is invalid
     *
     * @covers ::checkAction
     */
    public function checkAction_invalidMultiPagePublishableKey()
    {
        $currentMock = $this->getCurrentMock(array('checkPublishableKey'));

        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));

        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(true);

        Mage::app()->getStore(self::STORE_ID)
            ->setConfig('payment/boltpay/publishable_key_multipage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(false);

        $this->expectErrorResponse(array(self::INVALID_MULTI_PAGE_PUBLISHABLE_KEY));

        Bolt_Boltpay_StreamHelper::register();
        $currentMock->checkAction();
        Bolt_Boltpay_StreamHelper::restore();
    }

    /**
     * @test
     * Check action when publishable key for single page checkout is invalid
     *
     * @covers ::checkAction
     */
    public function checkAction_invalidOnePagePublishableKey()
    {
        $currentMock = $this->getCurrentMock(array('checkPublishableKey', 'checkPublishableKeyMultiPage'));
        $currentMock->method('checkPublishableKeyMultiPage')->willReturn(true);

        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));

        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(true);

        Mage::app()->getStore(self::STORE_ID)
            ->setConfig('payment/boltpay/publishable_key_onepage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(false);

        $this->expectErrorResponse(array(self::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE));

        Bolt_Boltpay_StreamHelper::register();
        $currentMock->checkAction();
        Bolt_Boltpay_StreamHelper::restore();
    }

    /**
     * @test
     * Checking API key with positive result
     *
     * @covers ::checkApiKey
     */
    public function checkApiKey_success()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );
        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(true);
        $this->assertTrue(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkApiKey'
            )
        );
    }

    /**
     * @test
     * Checking API key with negative result
     *
     * @covers ::checkApiKey
     */
    public function checkApiKey_fail()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );
        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn(false);
        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkApiKey'
            )
        );
    }

    /**
     * @test
     * Checking API key when helper throws an exception
     *
     * @covers ::checkApiKey
     */
    public function checkApiKey_exception()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );
        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willThrowException(new Exception());
        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkApiKey'
            )
        );
    }

    /**
     * @test
     * Signing secret check - should always return true
     *
     * @covers ::checkSigningSecret
     */
    public function checkSigningSecret()
    {
        $currentMock = $this->getCurrentMock();
        $this->assertTrue(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSigningSecret'
            )
        );
    }

    /**
     * @test
     * Checking multi page publishable key
     *
     * @covers ::checkPublishableKeyMultiPage
     */
    public function checkPublishableKeyMultiPage()
    {
        $currentMock = $this->getCurrentMock(array('checkPublishableKey'));
        Mage::app()->getStore(self::STORE_ID)
            ->setConfig('payment/boltpay/publishable_key_multipage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(true);
        $this->assertTrue(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkPublishableKeyMultiPage'
            )
        );
    }

    /**
     * @test
     * Checking one page publishable key
     *
     * @covers ::checkPublishableKeyOnePage
     */
    public function checkPublishableKeyOnePage()
    {
        $currentMock = $this->getCurrentMock(array('checkPublishableKey'));
        Mage::app()->getStore(self::STORE_ID)
            ->setConfig('payment/boltpay/publishable_key_onepage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(true);
        $this->assertTrue(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkPublishableKeyOnePage'
            )
        );
    }

    /**
     * @test
     * Checking publishable keys via API
     *
     * @covers ::checkPublishableKey
     * @dataProvider responseCodeAndStatusProvider
     *
     * @param $responseStatusCode
     * @param $isSuccessful
     * @throws ReflectionException
     */
    public function checkPublishableKey($responseStatusCode, $isSuccessful)
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );

        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('get', 'getStatusCode'))
            ->getMock();

        $this->helperMock->expects($this->once())->method('getApiClient')->willReturn($apiClientMock);
        $apiClientMock->expects($this->once())->method('get')->with(
            $this->anything(),
            array(
                'X-Publishable-Key' => self::PUBLISHABLE_KEY,
            )
        )->willReturnSelf();
        $apiClientMock->expects($this->once())->method('getStatusCode')->willReturn($responseStatusCode);

        $this->assertEquals(
            $isSuccessful,
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkPublishableKey',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking publishable keys via API and API client throws an exception
     *
     * @covers ::checkPublishableKey
     * @throws ReflectionException
     */
    public function checkPublishableKey_exception()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );

        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('get', 'getStatusCode'))
            ->getMock();

        $this->helperMock->expects($this->once())->method('getApiClient')->willReturn($apiClientMock);
        $apiClientMock->expects($this->once())->method('get')->with(
            $this->anything(),
            array(
                'X-Publishable-Key' => self::PUBLISHABLE_KEY,
            )
        )->willThrowException(new Exception());

        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkPublishableKey',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking database schema when all fields are present
     *
     * @covers ::checkSchema
     */
    public function checkSchema()
    {
        $currentMock = $this->getCurrentMock();

        $this->assertTrue(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );

    }


    /**
     * @test
     * Checking database schema when user_session_id column is missing from sales_flat_quote table
     *
     * @covers ::checkSchema
     */
    public function checkSchema_noQuoteUserSessionId()
    {
        $currentMock = $this->getCurrentMock();
        $connectionMock = $this->registerConnectionMock();
        $connectionMock->expects($this->once())->method('tableColumnExists')
            ->withConsecutive(
                array('sales_flat_quote', 'user_session_id')
            )
            ->willReturn(false);
        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking database schema when parent_quote_id column is missing from sales_flat_quote table
     *
     * @covers ::checkSchema
     */
    public function checkSchema_noQuoteParentQuoteId()
    {
        $currentMock = $this->getCurrentMock();
        $connectionMock = $this->registerConnectionMock();
        $connectionMock->expects($this->exactly(2))->method('tableColumnExists')
            ->withConsecutive(
                array('sales_flat_quote', 'user_session_id'),
                array('sales_flat_quote', 'parent_quote_id')
            )
            ->willReturnOnConsecutiveCalls(true, false);
        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking database schema when bolt_user_id customer attribute is missing
     *
     * @covers ::checkSchema
     */
    public function checkSchema_noCustomerBoltUserId()
    {
        $currentMock = $this->getCurrentMock();
        $connectionMock = $this->registerConnectionMock();
        $connectionMock->expects($this->exactly(2))->method('tableColumnExists')
            ->withConsecutive(
                array('sales_flat_quote', 'user_session_id'),
                array('sales_flat_quote', 'parent_quote_id')
            )
            ->willReturn(true);

        //stub internal calls of getAttribute
        $connectionMock->method('fetchRow')->willReturnOnConsecutiveCalls(
            array('entity_type_id' => 1),
            false
        );

        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking database schema when deferred order status is missing
     *
     * @covers ::checkSchema
     */
    public function checkSchema_noOrderStatusDeferred()
    {
        $currentMock = $this->getCurrentMock();
        $connectionMock = $this->registerConnectionMock();
        $dbStatementMock = $this->getMockBuilder('Varien_Db_Statement_Pdo_Mysql')
            ->disableOriginalConstructor()
            ->setMethods(array('fetchAll'))
            ->getMock();

        $connectionMock->expects($this->exactly(2))->method('tableColumnExists')
            ->withConsecutive(
                array('sales_flat_quote', 'user_session_id'),
                array('sales_flat_quote', 'parent_quote_id')
            )
            ->willReturn(true);

        $connectionMock->method('fetchRow')->willReturnOnConsecutiveCalls(
            array('entity_type_id' => 1),
            true
        );

        $connectionMock->method('query')->willReturn($dbStatementMock);
        $dbStatementMock->method('fetchAll')->willReturn(array());

        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Checking database schema when deferred order state is missing
     *
     * @covers ::checkSchema
     */
    public function checkSchema_noOrderStateDeferred()
    {
        $currentMock = $this->getCurrentMock();
        $connectionMock = $this->registerConnectionMock();
        $dbStatementMock = $this->getMockBuilder('Varien_Db_Statement_Pdo_Mysql')
            ->disableOriginalConstructor()
            ->setMethods(array('fetchAll'))
            ->getMock();

        $connectionMock->expects($this->exactly(2))->method('tableColumnExists')
            ->withConsecutive(
                array('sales_flat_quote', 'user_session_id'),
                array('sales_flat_quote', 'parent_quote_id')
            )
            ->willReturn(true);

        $connectionMock->method('fetchRow')->willReturnOnConsecutiveCalls(
            array('entity_type_id' => 1),
            true
        );

        $connectionMock->method('query')->willReturn($dbStatementMock);
        $dbStatementMock->method('fetchAll')->willReturnOnConsecutiveCalls(array(1), array());

        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSchema',
                array(
                    self::PUBLISHABLE_KEY
                )
            )
        );
    }

    /**
     * @test
     * Setting response with error data
     *
     *
     * @dataProvider errorMessageProvider
     * @param $message
     * @throws ReflectionException
     */
    public function setErrorResponseData($message)
    {
        $responseData = array(
            'result' => true
        );
        TestHelper::callNonPublicFunction(
            $this->getCurrentMock(),
            'setErrorResponseData',
            array(
                &$responseData,
                $message
            )
        );
        $this->assertFalse($responseData['result']);
        $this->assertEquals(
            $responseData['message'],
            array($message)
        );
    }

    /**
     * Data provider for error response data
     *
     * @return array of error messages
     */
    public function errorMessageProvider()
    {
        return array(
            array(self::INVALID_API_KEY_MESSAGE),
            array(self::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE),
            array(self::INVALID_MULTI_PAGE_PUBLISHABLE_KEY),
            array(self::INVALID_SIGNING_SECRET_MESSAGE),
            array(self::INVALID_CONFIGURATION_MESSAGE),
            array(self::INVALID_SCHEMA),
        );
    }

    /**
     * Data provider for http response codes and validation status
     *
     * @return array of http code and validation status pairs
     */
    public function responseCodeAndStatusProvider()
    {
        return array(
            array(200, true),
            array(mt_rand(200, 299), true),
            array(mt_rand(100, 199), false),
            array(mt_rand(400, 499), false),
            array(mt_rand(500, 599), false),
        );
    }

    /**
     * Data provider for @see checkAction
     * Generates all possible combinations with 5 boolean variables
     *
     * @return array of all possible combinations for 5 boolean variables
     */
    public function checkActionDataProvider()
    {
        $result = array();
        for ($i = 0; $i < 32; $i++) {
            $bin = decbin($i);
            $result[] = array_map(
                'boolval',
                str_split(str_repeat(0, 5 - strlen($bin)) . $bin, 1)
            );
        }

        return $result;
    }

    /**
     * Creates a mocked instance of class tested with certain methods mocked
     *
     * @param array $methods array of methods to be mocked
     * @return PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_ConfigurationController
     */
    private function getCurrentMock($methods = array())
    {
        $defaultMethods = array('getResponse');
        $currentMockBuilder = $this->getMockBuilder('Bolt_Boltpay_ConfigurationController')
            ->setMethods(array_merge($defaultMethods, $methods))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning();

        $currentMock = $currentMockBuilder->getMock();
        $currentMock->method('getResponse')->willReturn($this->response);
        return $currentMock;
    }

    /**
     * Configure response object to expect JSON error response with certain messages
     *
     * @param array $messages that should be inside response body
     */
    private function expectErrorResponse($messages = array())
    {
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json');
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($messages) {
                $responseData = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertFalse($responseData['result']);
                foreach ($messages as $message) {
                    $this->assertContains($message, $responseData['message']);
                }
            }
        );
    }

    /**
     * Configure response object to expect JSON success response
     */
    private function expectSuccessResponse()
    {
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json');
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) {
                $responseData = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertTrue($responseData['result']);
                $this->assertArrayNotHasKey('message', $responseData);
            }
        );
    }

    /**
     * Creates a mock instance of database connection and registers it in Magento as default
     *
     * @return PHPUnit_Framework_MockObject_MockObject mocked instance of database connection
     */
    private function registerConnectionMock()
    {
        $resourceMock = $this->getMockBuilder('Mage_Core_Model_Resource')
            ->setMethods(array('getConnection'))
            ->getMock();
        $connectionMock = $this->getMockBuilder('Magento_Db_Adapter_Pdo_Mysql')
            ->disableOriginalConstructor()
            ->setMethods(array('startSetup', 'tableColumnExists', 'quoteInto', 'fetchRow', 'query'))
            ->getMock();
        Mage::unregister('_singleton/core/resource');
        Mage::register('_singleton/core/resource', $resourceMock);
        $resourceMock->method('getConnection')->willReturn($connectionMock);
        return $connectionMock;
    }
}