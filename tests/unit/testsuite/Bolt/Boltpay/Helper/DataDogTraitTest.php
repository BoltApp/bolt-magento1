<?php

require_once('TestHelper.php');

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
     */
    public function logInfo_succeedsAndSetsLastResponseStatusToTrue()
    {
        $infoLog = $this->datadogHelper->logInfo(self::INFO_MESSAGE);
        $this->assertTrue($infoLog->getLastResponseStatus());
    }

    /**
     * @test
     * logWarning succeeds
     */
    public function logWarning_succeedsAndSetsLastResponseStatusToTrue()
    {
        $warningLog = $this->datadogHelper->logWarning(self::WARNING_MESSAGE);
        $this->assertTrue($warningLog->getLastResponseStatus());
    }

    /**
     * @test
     * logError succeeds
     */
    public function logError_succeedsAndSetsLastResponseStatusToTrue()
    {
        $exception = new Exception(self::ERROR_MESSAGE);
        $errorLog = $this->datadogHelper->logException($exception);
        $this->assertTrue($errorLog->getLastResponseStatus());
    }

    /**
     * @test
     * init sets env to test if PHPUNIT_ENVIRONMENT is set
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
}
