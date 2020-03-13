<?php

require_once('TestHelper.php');
require_once('StreamHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @coversDefaultClass Bolt_Boltpay_Controller_Traits_WebHookTrait
 */
class Bolt_Boltpay_Controller_Traits_WebHookTraitTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string Dummy HMAC of test payload
     * calculated as trim(base64_encode(hash_hmac('sha256', TEST_PAYLOAD, TEST_SIGNING_SECRET, true)))
     */
    const TEST_HMAC = 'fdd6zQftGT36/tGRItDZ0oB48VSptxj6TpZImLy4aZ4=';

    /** @var string Dummy webhook payload */
    const TEST_PAYLOAD = /** @lang JSON */ '{"test":"test"}';

    /** @var string Dummy signing secret, same as md5('bolt') */
    const TEST_SIGNING_SECRET = 'a6fe881cecd3fb7660083aea35cce430';

    /**
     * @var bool Used to check if fastcgi_finish_request is called, set to true by stubbed function
     */
    public static $_fastcgiFinishRequestCalled = false;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Controller_Traits_WebHookTrait Mocked instance of the trait being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Request_Http Mocked instance of request object
     */
    private $request;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Response_Http Mocked instance of response object
     */
    private $response;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Response_Http Proxy instance of response object
     */
    private $proxyResponse;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Layout Mocked instance of layout object
     */
    private $layout;

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
    public function setUp()
    {
        Mage::app('default');

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Controller_Traits_WebHookTraitMockObject')
            ->setMethods(array('getRequest', 'getLayout', 'getResponse', 'setFlag'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->request = $this->getMockBuilder('Mage_Core_Controller_Request_Http')
            ->setMethods(array('isAjax', 'getParam'))
            ->getMock();

        $this->layout = $this->getMockBuilder('Mage_Core_Model_Layout')
            ->setMethods(array('setDirectOutput'))
            ->getMock();

        $this->proxyResponse = new Mage_Core_Controller_Response_Http();
        $this->response = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(
                array(
                    'setHeader',
                    'setBody',
                    'setException',
                    'clearAllHeaders',
                    'clearBody',
                    'setHttpResponseCode',
                    'sendHeaders',
                    'sendResponse',
                    'sendHeadersAndExit'
                )
            )
            ->getMock();

        $this->response->method('sendResponse')->willReturnCallback(
            function() {
                $this->proxyResponse->sendResponse();
                return $this->response;
            }
        );

        $this->response->method('sendHeadersAndExit')->willThrowException(
            new Exception("Simulated early exit for test")
        );

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('setResponseContextHeaders', 'verify_hook', 'notifyException'))
            ->getMock();

        Mage::register('_helper/boltpay', $this->helperMock);

        Mage::getModel('boltpay/observer')->initializeBenchmarkProfiler();

        $this->currentMock->method('getLayout')->willReturn($this->layout);
        $this->currentMock->method('getRequest')->willReturn($this->request);
        $this->currentMock->method('getResponse')->willReturn($this->response);
    }

    /**
     * Cleanup changes made by tests
     */
    protected function tearDown()
    {
        Mage::unregister('_helper/boltpay');
        self::$_fastcgiFinishRequestCalled = false;
        unset($_SERVER['HTTP_X_BOLT_HMAC_SHA256']);
        Bolt_Boltpay_StreamHelper::restore();
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
    }

    /**
     * @test
     * Pre-dispatch method without validating signature
     *
     * @covers Bolt_Boltpay_Controller_Traits_WebHookTrait::preDispatch
     */
    public function preDispatch_withRequestSignatureValidationDisabled_shouldNotTryToVerifyHook()
    {
        $this->response->expects($this->once())->method('clearAllHeaders')->willReturnSelf();
        $this->response->expects($this->once())->method('clearBody')->willReturnSelf();
        $this->helperMock->expects($this->once())->method('setResponseContextHeaders');
        $this->response->method('setHeader')->withConsecutive(
            array('Content-type', 'application/json', true),
            array($this->anything(), $this->anything(), $this->anything())
        );
        $this->layout->expects($this->once())->method('setDirectOutput')->with(true);
        $this->helperMock->expects($this->never())->method('verify_hook');

        TestHelper::setNonPublicProperty(
            $this->currentMock,
            'requestMustBeSigned',
            false
        );

        // check to disable output buffering since ob_start() is called inside preDispatch
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->currentMock->preDispatch();
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$fromHooks);
    }

    /**
     * @test
     * Pre-dispatch method with validating signature
     *
     * @covers       Bolt_Boltpay_Controller_Traits_WebHookTrait::verifyBoltSignature
     * @covers       Bolt_Boltpay_Controller_Traits_WebHookTrait::preDispatch
     * @dataProvider payloadProvider
     *
     * @param string $payload Webhook payload in JSON format
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     * @throws GuzzleException
     */
    public function preDispatch_withRequestSignatureValidationEnabled_shouldTryToVerifyHook($payload)
    {
        $this->response->expects($this->once())->method('clearAllHeaders')->willReturnSelf();
        $this->response->expects($this->once())->method('clearBody')->willReturnSelf();
        $this->helperMock->expects($this->once())->method('setResponseContextHeaders');
        $this->response->method('setHeader')->withConsecutive(
            array('Content-type', 'application/json', true),
            array($this->anything(), $this->anything(), $this->anything())
        );
        $this->layout->expects($this->once())->method('setDirectOutput')->with(true);
        TestHelper::setNonPublicProperty(
            $this->currentMock,
            'requestMustBeSigned',
            true
        );

        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;
        //populate php://input stream
        Bolt_Boltpay_StreamHelper::setData($payload);

        $this->helperMock->expects($this->once())->method('verify_hook')->with($payload, self::TEST_HMAC)
            ->willReturn(true);

        // check to disable output buffering since ob_start() is called inside preDispatch
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        Bolt_Boltpay_StreamHelper::register();
        $this->currentMock->preDispatch();
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$fromHooks);
    }

    /**
     * @test
     * Getting request data from payload property
     *
     * @covers       Bolt_Boltpay_Controller_Traits_WebHookTrait::getRequestData
     * @dataProvider payloadProvider
     *
     * @param string $payload in JSON format
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     */
    public function getRequestData_withVariousPayload_shouldReturnPayloadAsObjectByDecodingJSON($payload)
    {
        TestHelper::setNonPublicProperty(
            $this->currentMock,
            'payload',
            $payload
        );
        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getRequestData'
        );
        $this->assertEquals(
            json_decode($payload),
            $result
        );
    }

    /**
     * Data provider for payload data in JSON format
     *
     * @return array containing payload data in JSON format
     */
    public function payloadProvider()
    {
        return array(
            'Simple test payload'     => array('payload' => /** @lang JSON */ self::TEST_PAYLOAD),
            'Webhook example payload' => array(
                'payload' => /** @lang JSON */ '{"cart":{"display_id": "100001|61", "shipping_address": {}}}'
            )
        );
    }

    /**
     * Captures the output buffering values before the test, creates its own output buffer to capture echoed
     * test output and set response expectations
     *
     * @param $responseCode     The response code that we wish to return in the test
     * @return array    Data used by the corresponding Teardown for the sendResponse test
     */
    private function responseSetUp($responseCode)
    {
        $initialImplicitFlushValue = ini_get("implicit_flush");
        $bufferingLevelBeforeTest = ob_get_level();

        $this->response->expects($this->once())->method('setHttpResponseCode')->with($responseCode)
            ->willReturnSelf();

        return array(
            'initialImplicitFlushValue' => $initialImplicitFlushValue,
            'bufferingLevelBeforeTest' => $bufferingLevelBeforeTest
        );
    }

    /**
     * @test
     * Failing signature validation
     *
     * @covers Bolt_Boltpay_Controller_Traits_WebHookTrait::verifyBoltSignature
     */
    public function verifyBoltSignature_withRequestSignatureValidationEnabledAndInvalidSignature_shouldSendErrorResponse()
    {
        $exception = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
        );
        $this->helperMock->expects($this->once())->method('verify_hook')
            ->with(self::TEST_PAYLOAD, self::TEST_HMAC)->willReturn(false);

        ob_start();
        $tearDownData = $this->responseSetUp($exception->getHttpCode());
        $this->response->expects($this->once())->method('setBody')->with($exception->getJson())
            ->willReturnCallback(
                function($content) {
                    $this->proxyResponse->setBody($content);
                    return $this->response;
                }
            )
        ;

        $this->response->expects($this->once())->method('setException')->with($exception)->willReturnSelf();

        $this->currentMock->expects($this->once())->method('setFlag')->with(
            '',
            Mage_Core_Controller_Varien_Action::FLAG_NO_POST_DISPATCH,
            1
        );

        try {
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'verifyBoltSignature',
                array(
                    self::TEST_PAYLOAD,
                    self::TEST_HMAC
                )
            );
        } catch ( Exception $e ) {
            $this->assertEquals("Simulated early exit for test", $e->getMessage());
        } finally {
            $this->responseTearDown($tearDownData);
            ob_end_clean();
        }
    }

    /**
     * Captures the output buffering values before the test, creates its own output buffer to capture echoed
     * test output and set response expectations
     *
     * @param $responseCode     The response code that we wish to return in the test
     * @return array    Data used by the corresponding Teardown for the sendResponse test
     */
    private function sendResponseSetUp($responseCode)
    {
        $this->response->expects($this->once())->method('sendHeaders')
            ->willReturnCallback(
                function () {
                    // create buffer to capture our test's output
                    ob_start();
                    return $this->response;
                }
            )
        ;

        return $this->responseSetUp($responseCode);
    }

    /**
     * @test
     * that when using various http codes and bodies, the correct code is set and the output of the response is
     * as specified (i.e. the same string input if it was a string, or json if the input was an array)
     *
     * @covers       Bolt_Boltpay_Controller_Traits_WebHookTrait::sendResponse
     * @dataProvider sendResponse_withVariousResponseCodesAndData_shouldOutputItDirectlyProvider
     *
     * @param int          $responseCode HTTP response code
     * @param string|array $responseData HTTP response body
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     */
    public function sendResponse_withVariousResponseCodesAndData_shouldOutputItDirectly($responseCode, $responseData)
    {
        $tearDownData = $this->sendResponseSetup($responseCode);
        $expectedResponse = is_string($responseData) ? $responseData : json_encode($responseData);
        $this->response->expects($this->once())->method('setBody')->with($expectedResponse)
            ->willReturnCallback(
                function($content) {
                    $this->proxyResponse->setBody($content);
                    return $this->response;
                }
            )
        ;

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'sendResponse',
            array(
                $responseCode,
                $responseData,
                false
            )
        );

        $actualResponse = ob_get_clean(); # get the calls response and remove our buffer

        $this->assertEquals($expectedResponse, $actualResponse);

        $this->responseTearDown($tearDownData);
    }

    /**
     * Data provider for HTTP response codes and data
     * Used by {@see sendResponse}
     *
     * @return array of HTTP response codes and response data
     */
    public function sendResponse_withVariousResponseCodesAndData_shouldOutputItDirectlyProvider()
    {
        return array(
            'Success request'                               => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_OK,
                'responseData' => '200: OK'
            ),
            'Success request with empty array'              => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_OK,
                'responseData' => array()
            ),
            'Success request with array should return json' => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_OK,
                'responseData' => array('success' => 'true')
            ),
            'Bad request'                                   => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_BAD_REQUEST,
                'responseData' => '400: Bad request'
            ),
            'Unauthorized'                                  => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_UNAUTHORIZED,
                'responseData' => '401: Unauthorized'
            ),
            'Not found'                                     => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_NOT_FOUND,
                'responseData' => '404: Not Found'
            ),
            'Gone'                                          => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_GONE,
                'responseData' => '410: Gone'
            ),
            'Unprocessable entity'                          => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_UNPROCESSABLE_ENTITY,
                'responseData' => '422: Unprocessable entity'
            ),
            'Internal server error'                         => array(
                'responseCode' => \Bolt_Boltpay_Controller_Interface::HTTP_INTERNAL_SERVER_ERROR,
                'responseData' => '500: Internal server error'
            ),
        );
    }

    /**
     * @test
     * Calling fastcgi_finish_request function (specific to Ngnix/PHP-FPM)
     *
     * @covers Bolt_Boltpay_Controller_Traits_WebHookTrait::sendResponse
     */
    public function sendResponse_whenUsedOnFPM_shouldCallPlatformSpecificMethod()
    {
        $expectedResponse = json_encode(array("something" => "that can be verified"));
        $this->response->expects($this->once())->method('setBody')->with($expectedResponse)
            ->willReturnCallback(
                function($content) {
                    $this->proxyResponse->setBody($content);
                    return $this->response;
                }
            )
        ;

        if (function_exists('fastcgi_finish_request')) {
            $this->markTestSkipped('Test not available with the Ngnix/PHP-FPM environment');
        } else {
            function fastcgi_finish_request()
            {
                Bolt_Boltpay_Controller_Traits_WebHookTraitTest::$_fastcgiFinishRequestCalled = true;
            }
        }


        $httpResponseCode = 200;
        $tearDownData = $this->sendResponseSetUp($httpResponseCode);

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'sendResponse',
            array(
                $httpResponseCode,
                $expectedResponse,
                false
            )
        );

        $actualResponse = ob_get_clean(); # get the calls response and remove our buffer

        $this->assertTrue(self::$_fastcgiFinishRequestCalled);
        $this->assertEquals($expectedResponse, $actualResponse);

        $this->responseTearDown($tearDownData);
    }

    /**
     * @test
     * sendResponse when $exitImmediately is true
     *
     * @covers Bolt_Boltpay_Controller_Traits_WebHookTrait::sendResponse
     */
    public function sendResponse_whenExitImmediatelyIsTrue_callsDispatchEvent()
    {
        try {
            $previousApp = Mage::app('default');
            $httpResponseCode = 200;
            $tearDownData = $this->sendResponseSetUp($httpResponseCode);

            $appMock = $this->getMockBuilder('Mage_Core_Model_App')
                ->setMethods(array('dispatchEvent'))
                ->getMock();

            $appMock->expects($this->exactly(2))->method('dispatchEvent')
                ->withConsecutive('controller_front_send_response_after', 'controller_front_send_response_after');

            TestHelper::setNonPublicProperty('Mage', '_app', $appMock);

            $responseArray = array('useful' => 'test response');
            $this->response->expects($this->once())->method('setBody')->with(json_encode($responseArray))
                ->willReturnCallback(
                    function($content) {
                        ob_start();  # buffer directly outputed text
                        $this->proxyResponse->setBody($content);
                        return $this->response;
                    }
                )
            ;

            $this->currentMock->expects($this->once())->method('setFlag')->with(
                '',
                Mage_Core_Controller_Varien_Action::FLAG_NO_POST_DISPATCH,
                1
            );

            try {
                TestHelper::callNonPublicFunction(
                    $this->currentMock,
                    'sendResponse',
                    array(
                        $httpResponseCode,
                        $responseArray,
                        true
                    )
                );
            } catch ( Exception $e ) {
                $this->assertEquals("Simulated early exit for test", $e->getMessage());
            }

            $this->responseTearDown($tearDownData);
        } finally {
            TestHelper::setNonPublicProperty('Mage', '_app', $previousApp);
            ob_end_clean();
        }
    }

    /**
     * Restores the output buffer setting to that which existed before the test
     *
     * @param array $outputBufferDataSettings Contains the initial output buffer settings before the test
     */
    private function responseTearDown($outputBufferDataSettings)
    {
        //reset implicit_flush value to default, changed inside sendResponse
        ini_set("implicit_flush", $outputBufferDataSettings['initialImplicitFlushValue']);

        // check to re-enable output buffering since ob_end_clean() is called inside sendResponse
        while (ob_get_level() < $outputBufferDataSettings['bufferingLevelBeforeTest']) {
            ob_start();
        }
    }
}

/**
 * Internal class instantiation of trait that allows for calling of parent methods
 */
class Bolt_Boltpay_Controller_Traits_WebHookTraitMockObject extends Mage_Core_Controller_Front_Action
{
    use Bolt_Boltpay_Controller_Traits_WebHookTrait;

    /**
     * Override of signed request setting
     */
    protected function _construct()
    {
        $this->requestMustBeSigned = false;
    }
}