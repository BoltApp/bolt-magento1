<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_FeatureSwitch
 */
class Bolt_Boltpay_Model_FeatureSwitchTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var MockObject|Bolt_Boltpay_Model_FeatureSwitch Mocked instance of tested class
     */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Mage_Core_Model_Config
     */
    private $configMock;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_FeatureSwitch';

    /**
     * @var MockObject|Mage_Core_Model_Cookie
     */
    private $cookieMock;

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper'))
            ->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('getFeatureSwitches'))
            ->getMock();
    }

    /**
     * Adjust current mock
     *
     * @param array $methods methods we need to stub (except of boltHelper, we ever stub it)
     * @throws Exception
     */
    private function adjustCurrentMock($methods = array())
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array_merge(array('boltHelper'), $methods))
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
    }

    /**
     * Return simple config value with one feature switch
     *
     * @return array
     */
    private function generateConfigValue()
    {
        return array(
            'M1_BOLT_ENABLED' => array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 100
            )
        );
    }

    /**
     * Return default switches values
     * This method should be updated when we add new feature switch to plugin
     *
     * @return array
     */
    private function generateDefaultSwitchesValue()
    {
        return array(
            'M1_BOLT_ENABLED' => array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 100
            ),
        );
    }

    /**
     * @test
     * check that constructor set default features properly
     *
     * @covers ::__construct
     */
    public function __construct_always_shouldSetDefaultFeaturesProperly()
    {
        $this->assertAttributeEquals($this->generateDefaultSwitchesValue(), 'defaultSwitches', $this->currentMock);
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * tests
     *
     * @throws ReflectionException  if there is a problem in stubbing model
     */
    private function updateFeatureSwitchesSetUp()
    {
        $this->adjustCurrentMock();
        $this->configMock = $this->getMockBuilder('Mage_Core_Model_Config')
            ->setMethods(array('saveConfig', 'cleanCache'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubModel('core/config', $this->configMock);
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', null);
    }

    /**
     * @test
     * When GraphQL call returns switches they should be store as config
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_withSwitches_savesSwitchesAsConfig()
    {
        $this->updateFeatureSwitchesSetUp();

        $boltAnswer = (object)array('data' => (object)array('plugin' => (object)array('features' =>
            array(
                (object)array(
                    'name' => 'M1_BOLT_ENABLED',
                    'value' => true,
                    'defaultValue' => false,
                    'rolloutPercentage' => 100
                )
            )
        )));
        $expectedConfigValue = $this->generateConfigValue();

        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willReturn($boltAnswer);
        $this->configMock->expects($this->once())->method('saveConfig')
            ->with('payment/boltpay/featureSwitches', json_encode($expectedConfigValue));
        $this->configMock->expects($this->once())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();
        $this->assertEquals(
            $expectedConfigValue,
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'switches')
        );

        $this->updateFeatureSwitchesTearDown();
    }

    /**
     * @test
     * When GraphQL call returns empty answer we should not save it
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_withNoSwitches_returnsWithoutSaving()
    {
        $this->updateFeatureSwitchesSetUp();

        $boltAnswer = '';
        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willReturn($boltAnswer);

        $this->configMock->expects($this->never())->method('saveConfig');
        $this->configMock->expects($this->never())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();
        $this->assertNull(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'switches')
        );

        $this->updateFeatureSwitchesTearDown();
    }

    /**
     * @test
     * When GraphQL call throws exception we should not change feature switches
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_whenException_returnsWithoutSaving()
    {
        $this->updateFeatureSwitchesSetUp();

        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willThrowException(new Exception('Any exception'));

        $this->configMock->expects($this->never())->method('saveConfig');
        $this->configMock->expects($this->never())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();
        $this->assertNull(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'switches')
        );

        $this->updateFeatureSwitchesTearDown();
    }

    /**
     * Removes model stub of "core/config" after {@see Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * tests
     */
    private function updateFeatureSwitchesTearDown()
    {
        Bolt_Boltpay_TestHelper::restoreModel('core/config');
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::readSwitches}
     * tests
     *
     * @throws ReflectionException  if there is a problem in stubbing model
     * @throws Mage_Core_Model_Store_Exception
     */
    private function readSwitchesSetUp($propertySwitchesValue)
    {
        $this->adjustCurrentMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', $propertySwitchesValue);
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/featureSwitches', json_encode($this->generateConfigValue()));
        Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'readSwitches');
    }

    /**
     * @test
     * When call readSwitches feature switches is stored into switches class property if it was empty
     *
     * @covers ::readSwitches
     */
    public function readSwitches_whenSwitchesPropertyIsEmpty_readSwitchesFromConfig()
    {
        $this->readSwitchesSetUp(null);
        $this->assertEquals(
            $this->generateConfigValue(),
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'switches')
        );
        $this->readSwitchesTearDown();
    }

    /**
     * @test
     * When call getSwitches and switches class property isnot empty do nothing
     *
     * @covers ::readSwitches
     */
    public function readSwitches_whenSwitchesPropertyIsNotEmpty_doesNothing()
    {
        $this->readSwitchesSetUp('test_value');
        $this->assertEquals(
            'test_value',
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'switches')
        );
        $this->readSwitchesTearDown();
    }

    /**
     * Removes model stub of config "payment/boltpay/featureSwitches" after {@see Bolt_Boltpay_Model_FeatureSwitch::getSwitches}
     * tests
     */
    private function readSwitchesTearDown()
    {
        Bolt_Boltpay_TestHelper::restoreConfigValue('payment/boltpay/featureSwitches');
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::getUniqueUserId}
     * tests
     *
     * @throws Mage_Core_Exception
     */
    private function getUniqueUserIdSetUp()
    {
        $this->adjustCurrentMock();
        $this->cookieMock = $this->getMockBuilder('Mage_Core_Model_Cookie')
            ->setMethods(array('get', 'set'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('core/cookie', $this->cookieMock);
    }

    /**
     * @test
     * When call getUniqueUserId and cookie isn't set should set cookie and return value
     *
     * @covers ::getUniqueUserId
     */
    public function getUniqueUserId_whenCookieIsNotSet_SetCookieAndReturn()
    {
        $this->getUniqueUserIdSetUp();
        $newCookieValue = '';
        $this->cookieMock->expects($this->once())->method('get')->with('BoltFeatureSwitchId')->willReturn(null);
        $this->cookieMock->expects($this->once())->method('set')
            ->with('BoltFeatureSwitchId', $this->isType('string'), true)
            ->will($this->returnCallback(
                function ($name, $value, $period) use (&$newCookieValue) {
                    $newCookieValue = $value;
                })
            );
        $userId = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getUniqueUserId');
        $this->assertEquals($newCookieValue, $userId);
        $this->getUniqueUserIdTearDown();
    }

    /**
     * @test
     * When call getUniqueUserId and cookie isn't set should set cookie and return value
     *
     * @covers ::getUniqueUserId
     */
    public function getUniqueUserId_whenCookieIsSet_ReturnCookieValue()
    {
        $this->getUniqueUserIdSetUp();
        $cookieValue = 'BFS5e64d49838a265.69564459';
        $this->cookieMock->expects($this->once())->method('get')->with('BoltFeatureSwitchId')->willReturn($cookieValue);
        $this->cookieMock->expects($this->never())->method('set');
        $this->assertEquals(
            $cookieValue,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getUniqueUserId')
        );
        $this->getUniqueUserIdTearDown();
    }

    /**
     * Restore 'core/cookie' singleton after {@see Bolt_Boltpay_Model_FeatureSwitch::getUniqueUserId}
     * tests
     */
    private function getUniqueUserIdTearDown()
    {
        Bolt_Boltpay_TestHelper::restoreSingleton('core/cookie');
    }

    /**
     * @test
     * When call isMarkedForRollout with different values should return expected, precalculated result
     *
     * @covers ::isMarkedForRollout
     * @dataProvider isMarkedForRolloutProvider
     * @param $switchName string switch name
     * @param $boltFeatureSwitchId string feature switch id (stubbed cookie value)
     * @param $rolloutPercentage int rollout percentage
     * @param $expectedResult bool expected result
     * @throws ReflectionException
     */
    public function isMarkedForRollout_withDifferentParameters_returnResultAsExpected($switchName, $boltFeatureSwitchId, $rolloutPercentage, $expectedResult)
    {
        $this->adjustCurrentMock(array('getUniqueUserId'));
        $this->currentMock->expects($this->once())->method('getUniqueUserId')
            ->willReturn($boltFeatureSwitchId);
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isMarkedForRollout',
                array($switchName, $rolloutPercentage)
            )
        );
    }

    /**
     * Data provider for {@see isMarkedForRollout_withDifferentParameters_returnResultAsExpected}
     *
     * @return array of feature name, cookie value, rollout percentage and expected value
     */
    public function isMarkedForRolloutProvider()
    {
        return array(
            array('M1_BOLT_ENABLED', 'BFS5e650cf94e1892.48522620', 45, true),
            array('M1_BOLT_ENABLED', 'BFS5e650cf94e1892.48522620', 44, false)
        );
    }

    /**
     * @test
     * When call isMarkedForRollout many times we should have expected distribution
     *
     * Algorithm:
     * - make 10 attempt, 5000 calls in one attempt
     * - calculate tolerance for each attempt
     * - compare the smallest and the biggest tolerance with expected values
     */
    public function isMarkedForRollout_whenCalledManyTimes_shouldReturnExpectedDistribution()
    {
        $this->adjustCurrentMock();
        $isMarkedForRolloutMethod = Bolt_Boltpay_TestHelper::getReflectedClass($this->currentMock)->getMethod('isMarkedForRollout');
        $isMarkedForRolloutMethod->setAccessible(true);

        $callsInAttempt = 5000;
        $numAttempts = 10;
        $error_message = 'This test works with pseudo-random numbers and probabilities so in very rare cases it can fail';
        $switchName = "M1_BOLT_ENABLED";

        $rolloutPercentage = rand(1, 99);
        $tolerance = array();
        for ($attemptCounter = 0; $attemptCounter < $numAttempts; $attemptCounter++) {
            $numPositive = 0;
            for ($i = 0; $i < $callsInAttempt; $i++) {
                $numPositive += (int)$isMarkedForRolloutMethod->invokeArgs($this->currentMock, array($switchName, $rolloutPercentage));
            }
            $expectedNumPositive = $callsInAttempt * $rolloutPercentage / 100;
            $tolerance[$attemptCounter] = abs($numPositive - $expectedNumPositive) / $callsInAttempt;
        }
        sort($tolerance);

        $this->assertLessThan(0.007, $tolerance[0], $error_message);
        $this->assertLessThan(0.04, $tolerance[$numAttempts - 1], $error_message);

        $isMarkedForRolloutMethod->setAccessible(false);
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::getFeatureSwitch}
     * tests
     *
     * @throws Exception
     */
    private function getFeatureSwitchValueByNameSetUp()
    {
        $this->adjustCurrentMock(array('readSwitches'));
        $this->currentMock->expects($this->once())->method('readSwitches');
    }

    /**
     * @test
     * When call getFeatureSwitchValueByName with unknown feature should throw exception
     *
     * @covers ::getFeatureSwitchValueByName
     */
    public function getFeatureSwitchValueByName_whenUnknownFeatureSwitch_shouldThrowException()
    {
        $this->getFeatureSwitchValueByNameSetUp();
        $catchException = false;
        try {
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getFeatureSwitchValueByName',
                array('unknownFeatureName')
            );
        } catch (Exception $e) {
            if ($e->getMessage() == 'Unknown feature switch') {
                $catchException = true;
            }
        }
        $this->assertTrue($catchException);
    }

    /**
     * @test
     * When call getFeatureSwitchValueByName when feature isn't set should return default value
     *
     * @covers ::getFeatureSwitchValueByName
     */
    public function getFeatureSwitchValueByName_whenFeatureSwitchIsNoSet_shouldReturnDefaultValue()
    {
        $this->getFeatureSwitchValueByNameSetUp();
        $configValue = $this->generateDefaultSwitchesValue();
        unset($configValue['M1_BOLT_ENABLED']);
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', $configValue);

        $this->assertEquals(
            array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 100),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getFeatureSwitchValueByName',
                array('M1_BOLT_ENABLED')
            )
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', null);
    }

    /**
     * @test
     * When call getFeatureSwitchValueByName when feature is set should return feature value
     *
     * @covers ::getFeatureSwitchValueByName
     */
    public function getFeatureSwitchValueByName_whenFeatureSwitchIsSet_shouldReturnFeatureValue()
    {
        $configValue = $this->generateConfigValue();
        $configValue['M1_BOLT_ENABLED']['rolloutPercentage'] = 57;
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', $configValue);

        $this->assertEquals(
            array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 57),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getFeatureSwitchValueByName',
                array('M1_BOLT_ENABLED')
            )
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', null);
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is 0 should return default value
     *
     * @covers ::isSwitchEnabled
     * @dataProvider isSwitchEnabledProvider
     * @param bool $expectedValue
     * @throws ReflectionException
     */
    public function isSwitchEnabled_whenRolloutPercentageIs0_shouldReturnDefaultValue($expectedValue)
    {
        $this->adjustCurrentMock(array('getFeatureSwitchValueByName'));
        $feature = array(
            'value' => true,
            'defaultValue' => $expectedValue,
            'rolloutPercentage' => 0
        );
        $this->currentMock->expects($this->once())->method('getFeatureSwitchValueByName')
            ->with('TEST_SWITCH')->willReturn($feature);
        $this->assertEquals(
            $expectedValue,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('TEST_SWITCH')
            )
        );
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is 100 should return feature value
     *
     * @covers ::isSwitchEnabled
     * @dataProvider isSwitchEnabledProvider
     * @param bool $expectedValue
     * @throws ReflectionException
     */
    public function isSwitchEnabled_whenRolloutPercentageIs100_shouldReturnValue($expectedValue)
    {
        $this->adjustCurrentMock(array('getFeatureSwitchValueByName'));
        $feature = array(
            'value' => $expectedValue,
            'defaultValue' => false,
            'rolloutPercentage' => 100
        );
        $this->currentMock->expects($this->once())->method('getFeatureSwitchValueByName')
            ->with('TEST_SWITCH')->willReturn($feature);
        $this->assertEquals(
            $expectedValue,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('TEST_SWITCH')
            )
        );
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is betwen 0 and 100 should
     * - call isMarkedForRollout() method
     * - return its value
     *
     * @covers ::isSwitchEnabled
     * @dataProvider isSwitchEnabledProvider
     * @param bool $expectedValue
     * @throws ReflectionException
     */
    public function isSwitchEnabled_whenRolloutPercentageBetween0And100_shouldCallIsMarkedForRolloutAndReturnItsResult($expectedValue)
    {
        $this->adjustCurrentMock(array('getFeatureSwitchValueByName', 'isMarkedForRollout'));
        $feature = array(
            'value' => true,
            'defaultValue' => false,
            'rolloutPercentage' => 37
        );
        $this->currentMock->expects($this->once())->method('getFeatureSwitchValueByName')
            ->with('TEST_SWITCH')->willReturn($feature);
        $this->currentMock->expects($this->once())->method('isMarkedForRollout')
            ->with('TEST_SWITCH', 37)->willReturn($expectedValue);

        $this->assertEquals(
            $expectedValue,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('TEST_SWITCH')
            )
        );
    }

    /**
     * Data provider for isSwitchEnabled tests
     *
     * @return array
     */
    public function isSwitchEnabledProvider()
    {
        return array(
            array('expectedValue' => true),
            array('expectedValue' => false)
        );
    }
}
