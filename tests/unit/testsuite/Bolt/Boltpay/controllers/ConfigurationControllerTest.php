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
        Bolt_Boltpay_StreamHelper::restore();
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/core/resource');
    }

    /**
     * Populates input stream with JSON containing store id
     */
    private function checkActionSetUp()
    {
        Bolt_Boltpay_StreamHelper::register();
        Bolt_Boltpay_StreamHelper::setData(json_encode(array('store_id' => self::STORE_ID)));
    }

    /**
     * @param $checkApiKey
     * @param $checkSigningSecret
     * @param $checkPublishableKeyMultiPage
     * @param $checkPublishableKeyOnePage
     * @param $checkSchema
     * @return Bolt_Boltpay_ConfigurationController|PHPUnit_Framework_MockObject_MockObject
     */
    private function checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponseSetUp($checkApiKey, $checkSigningSecret, $checkPublishableKeyMultiPage, $checkPublishableKeyOnePage, $checkSchema)
    {
        $this->currentMock = $this->getCurrentMock(
            array(
                'checkApiKey',
                'checkSigningSecret',
                'checkPublishableKeyMultiPage',
                'checkPublishableKeyOnePage',
                'checkSchema'
            )
        );

        $this->currentMock->expects($this->once())->method('checkApiKey')->willReturn($checkApiKey);
        $this->currentMock->expects($this->once())->method('checkSigningSecret')->willReturn($checkSigningSecret);
        $this->currentMock->expects($this->once())->method('checkPublishableKeyMultiPage')
            ->willReturn($checkPublishableKeyMultiPage);
        $this->currentMock->expects($this->once())->method('checkPublishableKeyOnePage')
            ->willReturn($checkPublishableKeyOnePage);
        $this->currentMock->expects($this->once())->method('checkSchema')->willReturn($checkSchema);
    }

    /**
     * @test
     * Check action with all combinations results for check methods
     *
     * @dataProvider checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponseProvider
     * @covers ::checkAction
     *
     * @param bool $checkApiKey Stubbed result of checkApiKey method call
     * @param bool $checkSigningSecret Stubbed result of checkSigningSecret method call
     * @param bool $checkPublishableKeyMultiPage Stubbed result of checkPublishableKeyMultiPage method call
     * @param bool $checkPublishableKeyOnePage Stubbed result of checkPublishableKeyOnePage method call
     * @param bool $checkSchema Stubbed result of checkSchema method
     * @throws Varien_Exception from method tested
     */
    public function checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponse($checkApiKey, $checkSigningSecret, $checkPublishableKeyMultiPage, $checkPublishableKeyOnePage, $checkSchema)
    {
        $this->checkActionSetUp();
        $this->checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponseSetUp(
            $checkApiKey,
            $checkSigningSecret,
            $checkPublishableKeyMultiPage,
            $checkPublishableKeyOnePage,
            $checkSchema
        );

        $expectedErrorMessages = array();
        if (!$checkApiKey) {
            $expectedErrorMessages[] = Bolt_Boltpay_ConfigurationController::INVALID_API_KEY_MESSAGE;
        }

        if (!$checkSigningSecret) {
            $expectedErrorMessages[] = Bolt_Boltpay_ConfigurationController::INVALID_SIGNING_SECRET_MESSAGE;
        }

        if (!$checkPublishableKeyMultiPage) {
            $expectedErrorMessages[] = Bolt_Boltpay_ConfigurationController::INVALID_MULTI_PAGE_PUBLISHABLE_KEY_MESSAGE;
        }

        if (!$checkPublishableKeyOnePage) {
            $expectedErrorMessages[] = Bolt_Boltpay_ConfigurationController::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE;
        }

        if (!$checkSchema) {
            $expectedErrorMessages[] = Bolt_Boltpay_ConfigurationController::INVALID_SCHEMA_MESSAGE;
        }

        if (empty($expectedErrorMessages)) {
            $this->expectsSuccessResponse();
        } else {
            $this->expectsErrorResponse($expectedErrorMessages);
        }

        $this->currentMock->checkAction();
    }

    /**
     * Data provider for {@see checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponse}
     * To generate all combination of bool values for 5 parameters
     * we convert integers 0-31 (pow(2, 5) - 1) to binary representation ['00000', '00001', '00010'...]
     * then split each representation into array of bits [[0,0,0,0,0], [0,0,0,0,1], [0,0,0,1,0]]
     * which are then casted into booleans
     *
     * [
     *     [false, false, false, false, false],
     *     [false, false, false, false, true],
     *     [false, false, false, true, false],
     *     ...
     * ]
     *
     * @return array of all possible combinations for 5 boolean variables
     */
    public function checkAction_withStubbedCheckMethodResults_returnsSuccessOrErrorResponseProvider()
    {
        $length = 5;
        $numberOfIntegers = pow(2, $length);
        $result = array();
        for ($i = 0; $i < $numberOfIntegers; $i++) {
            //get binary representation of the number
            $binaryRepresentation = decbin($i);
            //pad binary representation to length of 5 by adding 0 at the beginning
            $binaryRepresentation = str_repeat(0, $length - strlen($binaryRepresentation)) . $binaryRepresentation;
            //split each bit of the binary representation, cast to boolean and add to output
            $result[] = array_map('boolval', str_split($binaryRepresentation, 1));
        }

        return $result;
    }

    /**
     * @test
     * Check action when API key is invalid
     *
     * @covers ::checkAction
     * @covers ::checkApiKey
     * @throws Varien_Exception from tested method
     */
    public function checkAction_whenAPIKeyIsInvalid_returnsErrorResponse()
    {
        $this->checkActionSetUp();
        $currentMock = $this->getCurrentMock(
            array(
                'checkSigningSecret',
                'checkPublishableKeyMultiPage',
                'checkPublishableKeyOnePage',
                'checkSchema'
            )
        );
        $currentMock->expects($this->once())->method('checkSigningSecret')->willReturn(true);
        $currentMock->expects($this->once())->method('checkPublishableKeyMultiPage')->willReturn(true);
        $currentMock->expects($this->once())->method('checkPublishableKeyOnePage')->willReturn(true);
        $currentMock->expects($this->once())->method('checkSchema')->willReturn(true);

        $this->expectsHelperTransmit(false);

        $this->helperMock->expects($this->once())->method('logWarning')
            ->with(Bolt_Boltpay_ConfigurationController::INVALID_CONFIGURATION_MESSAGE);

        $this->expectsErrorResponse(array(Bolt_Boltpay_ConfigurationController::INVALID_API_KEY_MESSAGE));

        $currentMock->checkAction();
    }

    /**
     * @test
     * Check action when publishable key for multi page checkout is invalid
     *
     * @covers ::checkAction
     * @throws Varien_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function checkAction_whenMultiPagePublishableKeyIsInvalid_returnsErrorResponse()
    {
        $this->checkActionSetUp();
        $currentMock = $this->getCurrentMock(array('checkPublishableKey', 'checkPublishableKeyOnePage'));

        $this->expectsHelperTransmit(true);

        $store = Mage::app()->getStore(self::STORE_ID);
        $previousPublishableKeyMultipage = $store->getConfig('payment/boltpay/publishable_key_multipage');

        $store->setConfig('payment/boltpay/publishable_key_multipage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(false);

        $this->expectsErrorResponse(
            array(Bolt_Boltpay_ConfigurationController::INVALID_MULTI_PAGE_PUBLISHABLE_KEY_MESSAGE)
        );

        $currentMock->checkAction();
        $store->setConfig('payment/boltpay/publishable_key_multipage', $previousPublishableKeyMultipage);
    }

    /**
     * @test
     * Check action when publishable key for single page checkout is invalid
     *
     * @covers ::checkAction
     */
    public function checkAction_whenOnePagePublishableKeyIsInvalid_returnsErrorResponse()
    {
        $this->checkActionSetUp();
        $currentMock = $this->getCurrentMock(array('checkPublishableKey', 'checkPublishableKeyMultiPage'));
        $currentMock->method('checkPublishableKeyMultiPage')->willReturn(true);

        $this->expectsHelperTransmit(true);

        $store = Mage::app()->getStore(self::STORE_ID);
        $previousPublishableKeyOnepage = $store->getConfig('payment/boltpay/publishable_key_onepage');
        $store->setConfig('payment/boltpay/publishable_key_onepage', self::PUBLISHABLE_KEY);

        $currentMock->expects($this->once())->method('checkPublishableKey')->with(self::PUBLISHABLE_KEY)
            ->willReturn(false);

        $this->expectsErrorResponse(
            array(Bolt_Boltpay_ConfigurationController::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE)
        );

        $currentMock->checkAction();
        $store->setConfig('payment/boltpay/publishable_key_onepage', $previousPublishableKeyOnepage);
    }

    /**
     * @test
     * Checking API key with positive result
     *
     * @covers ::checkApiKey
     */
    public function checkApiKey_helperSuccessfullyExecutesApiRequest_returnsTrue()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );
        $this->expectsHelperTransmit(true);
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
    public function checkApiKey_helperReturnsEmptyResult_returnsFalse()
    {
        $currentMock = $this->getCurrentMock();
        TestHelper::setNonPublicProperty(
            $currentMock,
            '_storeId',
            self::STORE_ID
        );
        $this->expectsHelperTransmit(false);
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
    public function checkApiKey_helperThrowsException_returnsFalse()
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
     * that checkSigningSecret validates that signing key is set
     *
     * @dataProvider checkSigningSecret_withVariousSigningKey_returnsTrueIfSetInConfigProvider
     * @covers ::checkSigningSecret
     *
     * @param string $signingKey set in Magento configuration
     * @param bool $expectedResult of the check method call
     * @throws Mage_Core_Model_Store_Exception if the store doesn't exist
     * @throws ReflectionException if the controller doesn't have checkSigningSecret method
     */
    public function checkSigningSecret_withVariousSigningKeys_returnsTrueIfSetInConfig($signingKey, $expectedResult)
    {
        $currentMock = $this->getCurrentMock();
        $store = Mage::app()->getStore(self::STORE_ID);
        $previousSigningKey = $store->getConfig('payment/boltpay/signing_key');
        $store->setConfig('payment/boltpay/signing_key', $signingKey);
        $this->assertEquals(
            $expectedResult,
            TestHelper::callNonPublicFunction(
                $currentMock,
                'checkSigningSecret'
            )
        );
        $store->setConfig('payment/boltpay/signing_key', $previousSigningKey);
    }

    /**
     * Data provider for {@see checkSigningSecret_withVariousSigningKeys_returnsTrueIfSetInConfig}
     *
     * @return array containing signing key and expected result of the check
     */
    public function checkSigningSecret_withVariousSigningKey_returnsTrueIfSetInConfigProvider()
    {
        return array(
            'Valid key' => array('signingKey' => md5('bolt'), 'expectedResult' => true),
            'Empty key' => array('signingKey' => '', 'expectedResult' => false),
        );
    }

    /**
     * @test
     * Checking multi page publishable key
     *
     * @covers ::checkPublishableKeyMultiPage
     */
    public function checkPublishableKeyMultiPage_withValidPublishableKey_returnsTrue()
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
    public function checkPublishableKeyOnePage_withValidPublishableKey_returnsTrue()
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
     * Checking publishable keys via API by validating response codes
     * Should accept only 2XX status codes
     *
     * @covers ::checkPublishableKey
     * @dataProvider checkPublishableKey_withVariousResponseStatusCodes_returnValueShouldEqualToProvidedParamProvider
     *
     * @param int  $responseStatusCode returned by Api Client
     * @param bool $isSuccessful whether or not the status code should be considered successful
     * @throws ReflectionException if class tested doesn't have _storeId property or checkPublishableKey method
     */
    public function checkPublishableKey_withVariousResponseStatusCodes_returnValueShouldEqualToProvidedParam($responseStatusCode, $isSuccessful)
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
     * Data provider for http response codes and validation status
     * Used by {@see checkPublishableKey_withVariousResponseStatusCodes_returnValueShouldEqualToProvidedParam}
     *
     * @return array of http code and validation status pairs
     */
    public function checkPublishableKey_withVariousResponseStatusCodes_returnValueShouldEqualToProvidedParamProvider()
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
     * @test
     * Checking publishable keys via API and API client throws an exception
     *
     * @covers ::checkPublishableKey
     *
     * @throws ReflectionException if class tested doesn't have _storeId property or checkPublishableKey method
     */
    public function checkPublishableKey_apiClientThrowsException_returnsFalse()
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
    public function checkSchema_whenEverythingIsInstalledCorrectly_returnsTrue()
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
    public function checkSchema_whenUserSessionIdIsMissingFromQuote_returnsFalse()
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
    public function checkSchema_whenParentQuoteIdIsMissingFromQuote_returnsFalse()
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
    public function checkSchema_whenBoltUserIdIsMissingFromCustomer_returnsFalse()
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
    public function checkSchema_whenDeferredOrderStatusIsMissing_returnsFalse()
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
    public function checkSchema_whenDeferredOrderStateIsMissing_returnsFalse()
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
     * @dataProvider setErrorResponseData_withSpecificMessage_altersFirstParameterProvider
     *
     * @param string $message to be added to response messages
     * @throws ReflectionException if method tested doesn't exist
     */
    public function setErrorResponseData_withSpecificMessage_altersFirstParameter($message)
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
    public function setErrorResponseData_withSpecificMessage_altersFirstParameterProvider()
    {
        return array(
            array(Bolt_Boltpay_ConfigurationController::INVALID_API_KEY_MESSAGE),
            array(Bolt_Boltpay_ConfigurationController::INVALID_ONE_PAGE_PUBLISHABLE_KEY_MESSAGE),
            array(Bolt_Boltpay_ConfigurationController::INVALID_MULTI_PAGE_PUBLISHABLE_KEY_MESSAGE),
            array(Bolt_Boltpay_ConfigurationController::INVALID_SIGNING_SECRET_MESSAGE),
            array(Bolt_Boltpay_ConfigurationController::INVALID_CONFIGURATION_MESSAGE),
            array(Bolt_Boltpay_ConfigurationController::INVALID_SCHEMA_MESSAGE),
        );
    }

    /**
     * Creates a mocked instance of class tested with certain methods mocked
     *
     * @param array $methods to be mocked
     * @param array $defaultMethods that are mocked by default
     * @return PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_ConfigurationController
     */
    private function getCurrentMock($methods = array(), $defaultMethods = array('getResponse'))
    {
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
    private function expectsErrorResponse($messages = array())
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
    private function expectsSuccessResponse()
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
     * @throws Mage_Core_Exception from registry if key is already set
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

    /**
     * Sets the expectation that the Bolt Helper's transmit function will be called with
     * a sign directive
     *
     * @param bool $returnValue  true if the call is to be considered valid, otherwise false
     */
    private function expectsHelperTransmit($returnValue)
    {
        $this->helperMock->expects($this->once())->method('transmit')
            ->with('sign', $this->anything(), 'merchant', 'merchant', self::STORE_ID)
            ->willReturn($returnValue);
    }
}