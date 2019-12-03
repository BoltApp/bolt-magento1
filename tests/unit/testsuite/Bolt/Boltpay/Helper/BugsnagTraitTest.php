<?php
require_once('TestHelper.php');
require_once('StreamHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_BugsnagTrait
 * @backupGlobals enabled
 */
class Bolt_Boltpay_Helper_BugsnagTraitTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_BugsnagTrait Mocked instance of trait tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Boltpay_Bugsnag_Client Mocked instance of Bugsnag Client
     */
    private $bugsnagClientMock;

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_BugsnagTrait')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMockForTrait();

        $this->bugsnagClientMock = $this->getMockBuilder('Boltpay_Bugsnag_Client')
            ->disableOriginalConstructor()
            ->setMethods(array('notifyError', 'notifyException', 'setMetaData'))
            ->getMock();

        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'bugsnag',
            $this->bugsnagClientMock
        );
    }

    /**
     * @test
     * Adding breadcrumb by checking internal metaData property
     *
     * @covers ::addBreadcrumb
     */
    public function addBreadcrumb()
    {
        $breadCrumbs = array('test breadcrumb');
        $this->currentMock->addBreadcrumb($breadCrumbs);
        $value = Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'metaData');
        $this->assertEquals(
            array('breadcrumbs_' => $breadCrumbs),
            $value
        );
    }

    /**
     * @test
     * Verify that test method invokes internal methods notifyError and notifyException
     *
     * @covers ::test
     */
    public function test()
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_BugsnagTrait $currentMock */
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_BugsnagTrait')
            ->setMethods(array('notifyError', 'notifyException'))
            ->getMockForTrait();
        $currentMock->expects($this->once())->method('notifyError')->with('ErrorType', 'Test Error');
        $currentMock->expects($this->once())->method('notifyException')->with(new Exception("Test Exception"));
        $currentMock->test();
    }

    /**
     * @test
     * Method returns mocked instance of bugsnag client configured in setup
     *
     * @covers ::getBugsnag
     */
    public function getBugsnag()
    {
        $bugsnag = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getBugsnag'
        );

        $this->assertEquals(
            $this->bugsnagClientMock,
            $bugsnag
        );
    }

    /**
     * @test
     * Creating Bugsnag client in PHPUNIT environment
     *
     * @covers ::getBugsnag
     */
    public function getBugsnag_phpunitEnv()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'bugsnag',
            null
        );

        $_SERVER['PHPUNIT_ENVIRONMENT'] = true;

        /** @var Boltpay_Bugsnag_Client $bugsnag */
        $bugsnag = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getBugsnag'
        );

        /** @var Boltpay_Bugsnag_Configuration $bugsnagConfig */
        $bugsnagConfig = Bolt_Boltpay_TestHelper::getNonPublicProperty($bugsnag, 'config');

        $this->assertEquals(
            $bugsnagConfig->releaseStage,
            'test'
        );
    }

    /**
     * @test
     * Creating Bugsnag client object with releaseStage property dependant on Bolt test configuration
     *
     * @dataProvider boltTestConfigDataProvider
     * @param bool   $isTest configured in Bolt settings
     * @param string $releaseStage expected release stage of Bugsnag client
     */
    public function getBugsnag_releaseStageConfig($isTest, $releaseStage)
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'bugsnag',
            null
        );

        Mage::app()->getStore()->setConfig('payment/boltpay/test', $isTest);

        /** @var Boltpay_Bugsnag_Client $bugsnag */
        $bugsnag = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getBugsnag'
        );

        /** @var Boltpay_Bugsnag_Configuration $bugsnagConfig */
        $bugsnagConfig = Bolt_Boltpay_TestHelper::getNonPublicProperty($bugsnag, 'config');

        $this->assertEquals(
            $bugsnagConfig->releaseStage,
            $releaseStage
        );
    }

    /**
     * Data provider for @see getBugsnag_releaseStageConfig
     *
     * @return array of test config value and expected release stage code
     */
    public function boltTestConfigDataProvider()
    {
        return array(
            array(true, 'development'),
            array(false, 'production')
        );
    }

    /**
     * @test
     * Callback function that is invoked by Bugsnag client before adding an error to the queue
     *
     * @covers ::beforeNotifyFunction
     * @covers ::addDefaultMetaData
     * @covers ::addTraceIdMetaData
     * @covers ::addStoreUrlMetaData
     * @covers ::getBoltTraceId
     */
    public function beforeNotifyFunction()
    {
        $error = $this->getMockBuilder('Boltpay_Bugsnag_Error')
            ->setMethods(array('setMetadata'))
            ->disableOriginalConstructor()
            ->getMock();
        $boltTraceId = sha1('bolt');
        $_SERVER['HTTP_X_BOLT_TRACE_ID'] = $boltTraceId;
        $error->expects($this->once())->method('setMetadata')->with(
            array(
                'breadcrumbs_' => array(
                    'store_url'     => Mage::getBaseUrl(),
                    'bolt_trace_id' => $boltTraceId
                )
            )
        );
        $this->currentMock->addMetaData(
            array(
                'bolt_trace_id' => $boltTraceId,
                'store_url'     => Mage::getBaseUrl()
            )
        );
        $this->currentMock->beforeNotifyFunction($error);
    }

    /**
     * @test
     * Notify exception method of Bugsnag client is invoked correctly
     *
     * @dataProvider exceptionDataProvider
     * @covers ::notifyException
     * @covers ::getContextInfo
     *
     * @param string $expectedMessage contained in exception provided to Bugsnag client
     * @param array  $expectedMetadata provided as argument to Bugsnag client
     * @param int    $expectedSeverity provided as argument to Bugsnag client
     * @param string $expectedHost set to $_SERVER["HTTP_HOST"]
     * @param string $expectedUri set to $_SERVER['REQUEST_URI']
     * @param string $expectedRequestBody set as request body
     * @param string $expectedRequestMethod set as request method
     */
    public function notifyException($expectedMessage, $expectedMetadata, $expectedSeverity, $expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod)
    {

        $throwable = new Exception($expectedMessage);

        $this->bugsnagClientMock->expects($this->once())->method('notifyException')->willReturnCallback(
            function ($exception, $metaData, $severity) use ($expectedRequestBody, $expectedRequestMethod, $expectedUri, $expectedHost, $expectedMessage, $expectedMetadata, $expectedSeverity) {
                list($message, $contextInfoJSON) = explode("\n", $exception->getMessage());
                $contextInfo = json_decode($contextInfoJSON, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertEquals($expectedMessage, $message);
                $this->assertEquals($expectedMetadata, $metaData);
                $this->assertEquals($expectedSeverity, $severity);
                $this->assertEquals($expectedHost . $expectedUri, $contextInfo['Requested-URL']);
                $this->assertEquals(Mage::getVersion(), $contextInfo['Magento-Version']);
                $this->assertEquals($expectedRequestMethod, $contextInfo['Request-Method']);
                $this->assertEquals($expectedRequestBody, $contextInfo['Request-Body']);
            }
        );

        Bolt_Boltpay_StreamHelper::register();
        $this->prepareContextInfo($expectedHost, $expectedUri, $expectedRequestBody);
        try {
            $this->currentMock->notifyException($throwable, $expectedMetadata, $expectedSeverity);
        } catch (Exception $e) {
            Bolt_Boltpay_StreamHelper::restore();
            throw $e;
        }

        Bolt_Boltpay_StreamHelper::restore();
    }

    /**
     * Data provider for @see notifyException
     *
     * @return array of exception message, metadata, severity, host, uri, request body and method
     */
    public function exceptionDataProvider()
    {
        return array(
            array('Test message', array(), 10, 'www.example.com', 'test', 'body', 'POST')
        );
    }


    /**
     * @test
     * Notify error method of Bugsnag client is invoked correctly
     *
     * @dataProvider errorDataProvider
     * @covers ::notifyError
     * @covers ::getContextInfo
     *
     * @param string $expectedName provided as argument to Bugsnag client
     * @param string $expectedMessage provided as argument to Bugsnag client
     * @param array  $expectedMetadata provided as argument to Bugsnag client
     * @param int    $expectedSeverity provided as argument to Bugsnag client
     * @param string $expectedHost set to $_SERVER["HTTP_HOST"]
     * @param string $expectedUri set to $_SERVER['REQUEST_URI']
     * @param string $expectedRequestBody set as request body
     * @param string $expectedRequestMethod set as request method
     */
    public function notifyError($expectedName, $expectedMessage, $expectedMetadata, $expectedSeverity, $expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod)
    {

        $this->bugsnagClientMock->expects($this->once())->method('notifyError')->willReturnCallback(
            function ($name, $message, $metaData, $severity) use ($expectedName, $expectedRequestBody, $expectedRequestMethod, $expectedUri, $expectedHost, $expectedMessage, $expectedMetadata, $expectedSeverity) {
                $this->assertEquals($expectedName, $name);
                list($message, $contextInfoJSON) = explode("\n", $message);
                $contextInfo = json_decode($contextInfoJSON, true);
                $this->assertEquals($expectedMessage, $message);
                $this->assertEquals($expectedMetadata, $metaData);
                $this->assertEquals($expectedSeverity, $severity);
                $this->assertEquals($expectedHost . $expectedUri, $contextInfo['Requested-URL']);
                $this->assertEquals(Mage::getVersion(), $contextInfo['Magento-Version']);
                $this->assertEquals($expectedRequestMethod, $contextInfo['Request-Method']);
                $this->assertEquals($expectedRequestBody, $contextInfo['Request-Body']);
            }
        );

        Bolt_Boltpay_StreamHelper::register();
        $this->prepareContextInfo($expectedHost, $expectedUri, $expectedRequestBody);
        try {
            $this->currentMock->notifyError($expectedName, $expectedMessage, $expectedMetadata, $expectedSeverity);
        } catch (Exception $e) {
            Bolt_Boltpay_StreamHelper::restore();
            throw $e;
        }

        Bolt_Boltpay_StreamHelper::restore();
    }


    /**
     * Data provider for @see notifyError
     *
     * @return array of error name, message, metadata, severity, host, uri, request body and method
     */
    public function errorDataProvider()
    {
        return array(
            array('Error', 'Test message', array(), 10, 'www.example.com', 'test', 'body', 'POST')
        );
    }

    /**
     * @test
     * Retrieving Bolt version when it is unavailable in Magento config
     *
     * @covers ::getBoltPluginVersion
     */
    public function getBoltPluginVersion_null()
    {
        $config = Mage::getConfig();

        $oldXml = Bolt_Boltpay_TestHelper::getNonPublicProperty($config, '_xml');
        $newXml = new Varien_Simplexml_Element(/** @lang XML */ '<modules/>');
        $newXml->setNode('modules/Bolt_Boltpay', null);
        Bolt_Boltpay_TestHelper::setNonPublicProperty($config, '_xml', $newXml);

        $this->assertNull(
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getBoltPluginVersion')
        );

        Bolt_Boltpay_TestHelper::setNonPublicProperty($config, '_xml', $oldXml);
    }

    /**
     * @test
     * Retrieving request headers from $_SERVER
     *
     * @dataProvider requestHeadersDataProvider
     * @covers ::getRequestHeaders
     *
     * @param array $headers to replace $_SERVER
     * @param array $expectedResult of headers that should be returned
     */
    public function getRequestHeaders($headers, $expectedResult)
    {
        $_SERVER = $headers;

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getRequestHeaders'
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for @see getRequestHeaders
     *
     * @return array of headers and expected result
     */
    public function requestHeadersDataProvider()
    {
        return array(
            array(array('PATH' => '/usr/local/bin', 'SHELL' => '/bin/bash'), array()),
            array(array('HTTP_x-requested-with' => 'XMLHttpRequest'), array('X-requested-with' => 'XMLHttpRequest')),
            array(array('HTTP_Accept' => 'text/html'), array('Accept' => 'text/html')),
        );
    }

    /**
     * @test
     * Setting Bolt Trace Id
     *
     * @covers ::setBoltTraceId
     */
    public function setBoltTraceId()
    {
        $boltTraceId = sha1('bolt');
        $this->currentMock->setBoltTraceId($boltTraceId);
        $this->assertEquals(
            $boltTraceId,
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'boltTraceId')
        );
    }

    /**
     * @test
     * Adding metadata to bugsnag client
     *
     * @dataProvider addMetadataProvider
     * @covers ::addMetaData
     *
     * @param array $metaData provided to bugsnag client
     * @param bool $merge flag provided to bugsnag client
     */
    public function addMetaData($metaData, $merge)
    {
        $this->bugsnagClientMock->expects($this->once())->method('setMetaData')->with($metaData, $merge);

        $this->currentMock->addMetaData($metaData, $merge);
    }

    /**
     * Data provider for @see addMetadata
     *
     * @return array of metadata and merge flag
     */
    public function addMetadataProvider()
    {
        return array(
            array(array('test'), false),
            array(array('test'), true)
        );
    }

    /**
     * Set $_SERVER and php://input stream data to be collected by @see Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo
     *
     * @param string $host name of host server
     * @param string $uri of the current page
     * @param string $requestMethod HTTP request type
     * @param string $requestBody HTTP request body
     * @param array  $requestHeaders additional headers to set
     */
    private function prepareContextInfo($host, $uri, $requestBody = '', $requestMethod = 'POST', $requestHeaders = array())
    {
        $_SERVER["HTTP_HOST"] = $host;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $requestMethod;
        Bolt_Boltpay_StreamHelper::setData($requestBody);
        foreach ($requestHeaders as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }
}