<?php
require_once('TestHelper.php');
require_once('StreamHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_BugsnagTrait
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
     * Restore default stream wrapper
     */
    protected function tearDown()
    {
        Bolt_Boltpay_StreamHelper::restore();
    }

    /**
     * @test
     * that addBreadcrumb adds breadcrumbs to internal metaData property
     *
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::addBreadcrumb
     * @dataProvider addBreadcrumb_withEmptyMetadata_addsBreadcrumbToInternalPropertyProvider
     *
     * @param array $breadCrumbs to be added to metadata
     * @throws ReflectionException if trait doesn't have metaData property
     */
    public function addBreadcrumb_withEmptyMetadata_addsBreadcrumbToInternalProperty($breadCrumbs)
    {
        $this->currentMock->addBreadcrumb($breadCrumbs);
        $value = Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'metaData');
        $this->assertEquals(
            array('breadcrumbs_' => $breadCrumbs),
            $value
        );
    }

    /**
     * Data provider for {@see addBreadcrumb_withEmptyMetadata_addsBreadcrumbToInternalProperty}
     *
     * @return array of breadcrumbs
     */
    public function addBreadcrumb_withEmptyMetadata_addsBreadcrumbToInternalPropertyProvider()
    {
        return array(
            array(array('test breadcrumb'))
        );
    }

    /**
     * @test
     * that test method invokes internal methods {@see Bolt_Boltpay_Helper_BugsnagTrait::notifyError}}
     * and {@see Bolt_Boltpay_Helper_BugsnagTrait::notifyException}
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::test
     */
    public function test_always_willInvokeInternalMethods()
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
     * that getBugsnag method returns mocked instance of bugsnag client configured in setup
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBugsnag
     *
     * @throws ReflectionException if trait doesn't have getBugsnag method
     */
    public function getBugsnag_alreadyConfiguredInSetup_willReturnFromInternalProperty()
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
     * that getBugsnag in PHPUNIT environment will create bugsnag client with test as release stage
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBugsnag
     *
     * @throws ReflectionException if trait doesn't have getBugsnag method or bugsnag property
     */
    public function getBugsnag_inPHPUnitEnvironment_willUseTestAsReleaseStage()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'bugsnag',
            null
        );

        $previousPhpUnitEnvironment = $_SERVER['PHPUNIT_ENVIRONMENT'];
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

        $_SERVER['PHPUNIT_ENVIRONMENT'] = $previousPhpUnitEnvironment;
    }

    /**
     * @test
     * Creating Bugsnag client object with releaseStage property dependant on Bolt test configuration
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBugsnag
     *
     * @dataProvider getBugsnag_withTestConfiguration_willUseReleaseStageDependingOnTestConfigurationProvider
     *
     * @param bool   $isTest configured in Bolt settings
     * @param string $releaseStage expected release stage of Bugsnag client
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if trait doesn't have bugsnag property
     */
    public function getBugsnag_withTestConfiguration_willUseReleaseStageDependingOnTestConfiguration($isTest, $releaseStage)
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'bugsnag',
            null
        );

        $previousPhpUnitEnvironment = $_SERVER['PHPUNIT_ENVIRONMENT'];
        $_SERVER['PHPUNIT_ENVIRONMENT'] = false;

        $wasTest = Mage::getStoreConfig('payment/boltpay/test');

        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/test', $isTest);

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

        $_SERVER['PHPUNIT_ENVIRONMENT'] = $previousPhpUnitEnvironment;
        $store->setConfig('payment/boltpay/test', $wasTest);
    }

    /**
     * Data provider for {@see getBugsnag_withTestConfiguration_willUseReleaseStageDependingOnTestConfiguration}
     *
     * @return array of test config value and expected release stage code
     */
    public function getBugsnag_withTestConfiguration_willUseReleaseStageDependingOnTestConfigurationProvider()
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
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::beforeNotifyFunction
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::addDefaultMetaData
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::addTraceIdMetaData
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::addStoreUrlMetaData
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBoltTraceId
     */
    public function beforeNotifyFunction_withEmptyMetadata_willCollectDefaultMetadataAndSetItOnProvidedError()
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
     * that notify exception method of Bugsnag client is invoked correctly
     *
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::notifyException
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo
     *
     * @dataProvider notifyException_withVariousExceptions_shouldDelegateToBugsnagClientProvider
     *
     * @param string $expectedMessage contained in exception provided to Bugsnag client
     * @param array  $expectedMetadata provided as argument to Bugsnag client
     * @param int    $expectedSeverity provided as argument to Bugsnag client
     * @param string $expectedHost set to $_SERVER["HTTP_HOST"]
     * @param string $expectedUri set to $_SERVER['REQUEST_URI']
     * @param string $expectedRequestBody set as request body
     * @param string $expectedRequestMethod set as request method
     */
    public function notifyException_withVariousExceptions_shouldDelegateToBugsnagClient($expectedMessage, $expectedMetadata, $expectedSeverity, $expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod)
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

        $this->prepareContextInfo($expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod);

        $this->currentMock->notifyException($throwable, $expectedMetadata, $expectedSeverity);
    }

    /**
     * Exceptions provider for {@see notifyException_withVariousExceptions_shouldDelegateToBugsnagClient}
     *
     * @return array of exception message, metadata, severity, host, uri, request body and method
     */
    public function notifyException_withVariousExceptions_shouldDelegateToBugsnagClientProvider()
    {
        return array(
            'Simple exception message'               => array(
                'message'  => 'Test message',
                'metadata' => array(),
                'severity' => 10,
                'host'     => 'www.example.com',
                'uri'      => 'test',
                'body'     => '{}',
                'method'   => 'GET'
            ),
            'Simple exception message with metadata' => array(
                'message'  => 'Test message',
                'metadata' => array('test' => 'test'),
                'severity' => 1,
                'host'     => 'www.example.com',
                'uri'      => 'test',
                'body'     => '{}',
                'method'   => 'POST'
            )
        );
    }

    /**
     * @test
     * Notify error method of Bugsnag client is invoked correctly
     *
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::notifyError
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo
     *
     * @dataProvider notifyError_withVariousErrors_willDelegateToBugsnagClientProvider
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
    public function notifyError_withVariousErrors_willDelegateToBugsnagClient($expectedName, $expectedMessage, $expectedMetadata, $expectedSeverity, $expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod)
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

        $this->prepareContextInfo($expectedHost, $expectedUri, $expectedRequestBody, $expectedRequestMethod);

        $this->currentMock->notifyError($expectedName, $expectedMessage, $expectedMetadata, $expectedSeverity);
    }

    /**
     * Data provider for {@see notifyError_withVariousErrors_willDelegateToBugsnagClient}
     *
     * @return array of error name, message, metadata, severity, host, uri, request body and method
     */
    public function notifyError_withVariousErrors_willDelegateToBugsnagClientProvider()
    {
        return array(
            'Simple error message'               => array(
                'name'     => 'Error',
                'message'  => 'Test message',
                'metadata' => array(),
                'severity' => 10,
                'host'     => 'www.example.com',
                'uri'      => 'test',
                'body'     => '{}',
                'method'   => 'GET'
            ),
            'Simple error message with metadata' => array(
                'name'     => 'Error',
                'message'  => 'Test message',
                'metadata' => array('test' => 'test'),
                'severity' => 1,
                'host'     => 'www.example.com',
                'uri'      => 'test',
                'body'     => '{}',
                'method'   => 'POST'
            )
        );
    }

    /**
     * @test
     * Retrieving Bolt version when it is unavailable in Magento config
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBoltPluginVersion
     *
     * @throws ReflectionException if Mage::_config doesn't have _xml property
     */
    public function getBoltPluginVersion_withoutConfigValueSet_returnsNull()
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
     * Retrieving Bolt version when it is set in Magento config
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::getBoltPluginVersion
     *
     * @throws ReflectionException if Mage::_config doesn't have _xml property
     */
    public function getBoltPluginVersion_withConfigValueSet_returnsSetValue()
    {
        $config = Mage::getConfig();
        $configXml = Bolt_Boltpay_TestHelper::getNonPublicProperty($config, '_xml');
        $version = (string)$configXml->modules->Bolt_Boltpay->version;

        $this->assertNotEmpty($version);
        $this->assertEquals(
            $version, Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getBoltPluginVersion')
        );
    }

    /**
     * @test
     * that getRequestHeaders returns headers from $_SERVER (they begin with HTTP_) formatted properly
     *
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::getRequestHeaders
     *
     * @dataProvider getRequestHeaders_withVariousHeaders_willFilterAndReturnProvider
     *
     * @param array $headers to replace $_SERVER
     * @param array $expectedResult of headers that should be returned
     * @throws ReflectionException if current trait doesn't have getRequestHeaders method
     */
    public function getRequestHeaders_withVariousHeaders_willFilterAndReturn($headers, $expectedResult)
    {
        $_SERVER = $headers;

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getRequestHeaders'
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see getRequestHeaders_withVariousHeaders_willFilterAndReturn}
     *
     * @return array of $_SERVER items and expected headers result
     */
    public function getRequestHeaders_withVariousHeaders_willFilterAndReturnProvider()
    {
        return array(
            array(array('PATH' => '/usr/local/bin', 'SHELL' => '/bin/bash'), array()),
            array(array('HTTP_x-requested-with' => 'XMLHttpRequest'), array('X-requested-with' => 'XMLHttpRequest')),
            array(array('HTTP_Accept' => 'text/html'), array('Accept' => 'text/html')),
        );
    }

    /**
     * @test
     * that setBoltTraceId updates internal boltTraceId property
     *
     * @covers Bolt_Boltpay_Helper_BugsnagTrait::setBoltTraceId
     *
     * @throws ReflectionException if trait doesn't have boltTraceId property
     */
    public function setBoltTraceId_always_setsInternalProperty()
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
     * that addMetaData delegates call to bugsnag client
     *
     * @covers       Bolt_Boltpay_Helper_BugsnagTrait::addMetaData
     *
     * @dataProvider addMetaData_withBugsnagClientConfiguredInSetup_willDelegateToBugsnagClientProvider
     *
     * @param array $metaData provided to bugsnag client
     * @param bool  $merge flag provided to bugsnag client
     */
    public function addMetaData_withBugsnagClientConfiguredInSetup_willDelegateToBugsnagClient($metaData, $merge)
    {
        $this->bugsnagClientMock->expects($this->once())->method('setMetaData')->with($metaData, $merge);

        $this->currentMock->addMetaData($metaData, $merge);
    }

    /**
     * Data provider for {@see addMetadata}
     *
     * @return array of metadata and merge flag
     */
    public function addMetaData_withBugsnagClientConfiguredInSetup_willDelegateToBugsnagClientProvider()
    {
        return array(
            'Metadata without merge' => array(
                'metaData' => array('test' => 'test'),
                'merge'    => false
            ),
            'Metadata with merge'    => array(
                'metaData' => array('test' => 'test'),
                'merge'    => true
            )
        );
    }

    /**
     * Populate $_SERVER and php://input stream with data to be collected by
     * @see Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo
     *
     * @param string $host name of host server
     * @param string $uri of the current page
     * @param string $requestMethod HTTP request type
     * @param string $requestBody HTTP request body
     * @param array  $requestHeaders additional headers to set
     */
    private function prepareContextInfo($host, $uri, $requestBody = '', $requestMethod = 'POST', $requestHeaders = array())
    {
        Bolt_Boltpay_StreamHelper::register();
        $_SERVER["HTTP_HOST"] = $host;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $requestMethod;
        Bolt_Boltpay_StreamHelper::setData($requestBody);
        foreach ($requestHeaders as $key => $value) {
            $_SERVER[$key] = $value;
        }
    }
}