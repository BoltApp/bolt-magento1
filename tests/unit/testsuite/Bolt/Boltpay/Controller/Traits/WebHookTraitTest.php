<?php

require_once('TestHelper.php');
require_once('StreamHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

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
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Controller_Traits_WebHookTrait')
            ->setMethods(array('getRequest', 'getLayout', 'getResponse'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMockForTrait();

        $this->request = $this->getMockBuilder('Mage_Core_Controller_Request_Http')
            ->setMethods(array('isAjax', 'getParam'))
            ->getMock();

        $this->layout = $this->getMockBuilder('Mage_Core_Model_Layout')
            ->setMethods(array('setDirectOutput'))
            ->getMock();

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
                    'sendResponse'
                )
            )
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array('setResponseContextHeaders', 'verify_hook', 'notifyException')
            )
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
    }

    /**
     * @test
     * Pre-dispatch method without validating signature
     *
     * @covers ::preDispatch
     */
    public function preDispatch_unsigned()
    {
        $this->response->expects($this->once())->method('clearAllHeaders')->willReturnSelf();
        $this->response->expects($this->once())->method('clearBody')->willReturnSelf();
        $this->helperMock->expects($this->once())->method('setResponseContextHeaders');
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);
        $this->layout->expects($this->once())->method('setDirectOutput')->with(true)
            ->willThrowException(new Exception('Avoid calling parent::preDispatch'));
        TestHelper::setNonPublicProperty(
            $this->currentMock,
            'requestMustBeSigned',
            false
        );

        //this is expected because we are mocking a trait
        $this->setExpectedException(Exception::class, 'Avoid calling parent::preDispatch');

        //cancel out ob_start() called inside predispatch
        ob_end_clean();

        $this->currentMock->preDispatch();
    }

    /**
     * @test
     * Pre-dispatch method with validating signature
     *
     * @covers ::verifyBoltSignature
     * @covers ::preDispatch
     * @dataProvider payloadProvider
     *
     * @param string $payload Webhook payload in JSON format
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     */
    public function preDispatch_signed($payload)
    {
        $this->response->expects($this->once())->method('clearAllHeaders')->willReturnSelf();
        $this->response->expects($this->once())->method('clearBody')->willReturnSelf();
        $this->helperMock->expects($this->once())->method('setResponseContextHeaders');
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);
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
            ->willThrowException(new Exception('Avoid calling parent::preDispatch'));

        //this is expected because we are mocking a trait
        $this->setExpectedException(Exception::class, 'Avoid calling parent::preDispatch');

        //cancel out ob_start() called inside predispatch
        ob_end_clean();

        Bolt_Boltpay_StreamHelper::register();
        try {
            $this->currentMock->preDispatch();
        } finally {
            Bolt_Boltpay_StreamHelper::restore();
        }
    }

    /**
     * @test
     * Failing signature validation
     *
     * @covers ::verifyBoltSignature
     */
    public function verifyBoltSignature_invalid()
    {
        $exception = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
        );
        $this->helperMock->expects($this->once())->method('verify_hook')
            ->with(self::TEST_PAYLOAD, self::TEST_HMAC)->willReturn(false);
        $this->response->expects($this->once())->method('setHttpResponseCode')->with($exception->getHttpCode())
            ->willReturnSelf();
        $this->response->expects($this->once())->method('setBody')->with($exception->getJson())->willReturnSelf();
        $this->response->expects($this->once())->method('setException')->with($exception)->willReturnSelf();
        $this->response->expects($this->once())->method('sendResponse');

        $this->helperMock->expects($this->once())->method('notifyException')->with($exception, array(), 'warning')
            ->willThrowException(new Exception('Expected exception before exit call'));

        $this->setExpectedException(Exception::class, 'Expected exception before exit call');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'verifyBoltSignature',
            array(
                self::TEST_PAYLOAD,
                self::TEST_HMAC
            )
        );
    }

    /**
     * @test
     * Sending response with various http codes
     * Cannot check data output due to using echo and flush in the sendResponse method
     *
     * @covers       Bolt_Boltpay_Controller_Traits_WebHookTrait::sendResponse
     * @dataProvider responseCodeProvider
     *
     * @param int $httpResponseCode HTTP response code
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     */
    public function sendResponse($httpResponseCode)
    {
        $this->response->expects($this->once())->method('setHttpResponseCode')->with($httpResponseCode)
            ->willReturnSelf();
        $this->response->expects($this->once())->method('sendHeaders');
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'sendResponse',
            array(
                $httpResponseCode,
                '',
                false
            )
        );
        //reset implicit_flush value to default, changed inside sendResponse
        ini_set("implicit_flush", 0);

        //cancel out ob_end_clean() called inside sendResponse
        ob_start();
    }

    /**
     * Data provider for HTTP response codes
     * used by @see sendResponse
     *
     * @return array of HTTP response codes
     */
    public function responseCodeProvider()
    {
        return array(
            array(200),
            array(422),
        );
    }

    /**
     * @test
     * Calling fastcgi_finish_request function (specific to Ngnix/PHP-FPM)
     *
     * @covers ::sendResponse
     */
    public function sendResponse_fpm()
    {
        $httpResponseCode = 200;
        $this->response->expects($this->once())->method('setHttpResponseCode')->with($httpResponseCode)
            ->willReturnSelf();
        $this->response->expects($this->once())->method('sendHeaders');

        if (!function_exists('fastcgi_finish_request')) {
            function fastcgi_finish_request()
            {
                Bolt_Boltpay_Controller_Traits_WebHookTraitTest::$_fastcgiFinishRequestCalled = true;
            }
        }

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'sendResponse',
            array(
                $httpResponseCode,
                array(),
                false
            )
        );
        $this->assertTrue(self::$_fastcgiFinishRequestCalled);

        //reset implicit_flush value to default, changed inside sendResponse
        ini_set("implicit_flush", 0);

        //cancel out ob_end_clean() called inside sendResponse
        ob_start();
    }

    /**
     * @test
     * Getting request data from payload property
     *
     * @covers ::getRequestData
     * @dataProvider payloadProvider
     *
     * @param string $payload in JSON format
     * @throws ReflectionException from TestHelper if a specified object, class or property does not exist.
     */
    public function getRequestData($payload)
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
            array(/** @lang JSON */ self::TEST_PAYLOAD),
            array(/** @lang JSON */ '{"cart":{"display_id": "100001|61", "shipping_address": {}}}')
        );
    }
}