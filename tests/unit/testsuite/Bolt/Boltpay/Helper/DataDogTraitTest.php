<?php

use Bolt_Boltpay_TestHelper as TestHelper;

class Bolt_Boltpay_Helper_DataDogTraitTest extends PHPUnit_Framework_TestCase
{
    const INFO_MESSAGE = 'Datadog UnitTest Info';
    const WARNING_MESSAGE = 'Datadog UnitTest Warning';
    const ERROR_MESSAGE = 'Datadog UnitTest Error';

    /**
     * @var Bolt_Boltpay_Helper_Data
     */
    private $datadogHelper;

    public function setUp()
    {
        $datadogOptions = array(
            'datadogKey' => Bolt_Boltpay_Helper_DataDogTrait::$defaultDataDogKey,
            'datadogKeySeverity' => 'error,info,warning'
        );
        Mage::app()->getStore()->setConfig('payment/boltpay/extra_options', json_encode($datadogOptions));
        $this->datadogHelper = Mage::helper('boltpay');
    }

    /**
     * @test
     * logInfo succeeds
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::logInfo
     */
    public function logInfo_succeedsAndSetsLastResponseStatusToTrue()
    {
        $infoLog = $this->datadogHelper->logInfo(self::INFO_MESSAGE);
        $this->assertTrue($infoLog->getLastResponseStatus());
    }

    /**
     * @test
     * logWarning succeeds
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::logWarning
     */
    public function logWarning_succeedsAndSetsLastResponseStatusToTrue()
    {
        $warningLog = $this->datadogHelper->logWarning(self::WARNING_MESSAGE);
        $this->assertTrue($warningLog->getLastResponseStatus());
    }

    /**
     * @test
     * logException succeeds
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::logException
     */
    public function logException_always_succeedsAndSetsLastResponseStatusToTrue()
    {
        $exception = new Exception(self::ERROR_MESSAGE);
        $errorLog = $this->datadogHelper->logException($exception);
        $this->assertTrue($errorLog->getLastResponseStatus());
    }

    /**
     * @test
     * init sets env to test if PHPUNIT_ENVIRONMENT is set
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::init
     *
     * @throws ReflectionException
     */
    public function init_ifPHPUNIT_ENVIRONMENTIsSet_setsEnvToTest()
    {
        $_SERVER['PHPUNIT_ENVIRONMENT'] = 'test';
        TestHelper::callNonPublicFunction($this->datadogHelper, 'init');
        $expectedEnv = Boltpay_DataDog_Environment::TEST_ENVIRONMENT;
        $actualEnv = TestHelper::getNonPublicProperty($this->datadogHelper, '_data')['env'];
        $this->assertEquals($expectedEnv, $actualEnv);
        unset($_SERVER['PHPUNIT_ENVIRONMENT']);
    }

    /**
     * @test
     * init sets env to development if PHPUNIT_ENVIRONMENT is not set and payment/boltpay/test is true
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::init
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function init_ifPHPUNIT_ENVIRONMENTIsNotSetAndTestIsTrue_setsEnvToDevelopment()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', true);
        TestHelper::callNonPublicFunction($this->datadogHelper, 'init');
        $expectedEnv = Boltpay_DataDog_Environment::DEVELOPMENT_ENVIRONMENT;
        $actualEnv = TestHelper::getNonPublicProperty($this->datadogHelper, '_data')['env'];
        $this->assertEquals($expectedEnv, $actualEnv);
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * init sets env to production if PHPUNIT_ENVIRONMENT is not set and payment/boltpay/test is not true
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::init
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function init_ifPHPUNIT_ENVIRONMENTIsNotSetAndTestIsNotTrue_setsEnvToProduction()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', false);
        TestHelper::callNonPublicFunction($this->datadogHelper, 'init');
        $expectedEnv = Boltpay_DataDog_Environment::PRODUCTION_ENVIRONMENT;
        $actualEnv = TestHelper::getNonPublicProperty($this->datadogHelper, '_data')['env'];
        $this->assertEquals($expectedEnv, $actualEnv);
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that getDataDog should return the {@see Bolt_Boltpay_Helper_DataDogTrait::_datadog}
     *
     * @covers Bolt_Boltpay_Helper_DataDogTrait::getDataDog
     */
    public function getDataDog_whenDataDogIsSet_returnsDataDog()
    {
        $dataDogMock = $this->getMockBuilder('Boltpay_DataDog_Client')->disableOriginalConstructor()->getMock();
        TestHelper::setNonPublicProperty($this->datadogHelper, '_datadog', $dataDogMock);
        $actualDataDog = TestHelper::callNonPublicFunction($this->datadogHelper, 'getDataDog');
        $this->assertEquals($dataDogMock, $actualDataDog);
    }

    /**
     * @test
     * that getDataDog should call init method and set the {@see Bolt_Boltpay_Helper_DataDogTrait::_datadog}
     * 
     * @covers Bolt_Boltpay_Helper_DataDogTrait::getDataDog
     */
    public function getDataDog_whenDataDogIsNotSet_callsInitAndSetsDataDog()
    {
        $apiKey = '3dadasdasdas';
        $this->datadogHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_DataDogTrait')
            ->setMethods(array('getApiKeyConfig', 'getSeverityConfig'))
            ->getMockForTrait();
        $this->datadogHelper->expects($this->once())->method('getApiKeyConfig')->willReturn($apiKey);
        $this->datadogHelper->expects($this->once())->method('getSeverityConfig')->willReturn(array());
        TestHelper::setNonPublicProperty($this->datadogHelper, '_datadog', null);
        $actualDataDog = TestHelper::callNonPublicFunction($this->datadogHelper, 'getDataDog');

        $this->assertEquals($apiKey, TestHelper::getNonPublicProperty($this->datadogHelper, '_apiKey'));
        $this->assertAttributeEquals($actualDataDog, '_datadog', $this->datadogHelper);
    }
}
