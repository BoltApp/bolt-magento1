<?php
require_once('TestHelper.php');
require_once('MockingTrait.php');

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
            ->setMethods(array('boltHelper'))->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('getFeatureSwitches'))->getMock();
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
            ->setMethods(array_merge(array('boltHelper'), $methods))->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
    }

    /**
     * Return simple config value with two feature switches
     * @return array
     */
    private function generateConfigValue()
    {
        return array(
            'M1_BOLT_ENABLED' => array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 100
            ),
            'M1_SAMPLE_SWITCH' => array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 0
            )
        );
    }

    /**
     * Return default switches values
     * This method should be updated when we add new feature switch to plugin
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
            'M1_SAMPLE_SWITCH' => array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 0
            )
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
        $this->assertAttributeEquals($this->generateDefaultSwitchesValue(),'defaultSwitches',$this->currentMock);
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
            ->setMethods(
                array(
                    'saveConfig',
                    'cleanCache',
                )
            )->getMock();
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
                ),
                (object)array(
                    'name' => 'M1_SAMPLE_SWITCH',
                    'value' => true,
                    'defaultValue' => false,
                    'rolloutPercentage' => 0)
            ))));
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
            ->willThrowException(new \Exception('Any exception'));

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
    public function readSwitches_whenSwitchesPropertyIsNotEmpty_doNothing()
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
     * @throws ReflectionException if there is a problem in stubbing model
     */
    private function getUniqueUserIdSetUp()
    {
        $this->adjustCurrentMock();
        $this->cookieMock = $this->getMockBuilder('Mage_Core_Model_Cookie')
            ->setMethods(
                array(
                    'get',
                    'set',
                )
            )->getMock();
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
                }));
        $userId = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getUniqueUserId');
        $this->assertEquals($newCookieValue,$userId);
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
     * When call isInBucket with different values should return expected, precalculated result
     *
     * @covers ::isInBucket
     * @dataProvider isInBucketProvider
     * @param $switchName string switch name
     * @param $boltFeatureSwitchId string feature switch id (stubbed cooqie value)
     * @param $rolloutPercentage int rollout percentage
     * @param $expectedResult bool expected result
     * @throws ReflectionException
     */
    public function isInBucket_withDifferentParameters_returnResultAsExpected($switchName, $boltFeatureSwitchId, $rolloutPercentage, $expectedResult)
    {
        $this->adjustCurrentMock(array('getUniqueUserId'));
        $this->currentMock->expects($this->once())->method('getUniqueUserId')
            ->willReturn($boltFeatureSwitchId);
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isInBucket',
                array($switchName, $rolloutPercentage)
            )
        );
    }

    /**
     * Provides calculated value for @see isInBucket calls
     *
     * @return array of feature name, cookie value, threshold rollow percentage and expected value
     */
    public function isInBucketProvider()
    {
        return array(
            array('M1_BOLT_ENABLED', 'BFS5e650cf94e1892.48522620', 45, true),
            array('M1_BOLT_ENABLED', 'BFS5e650cf94e1892.48522620', 44, false),
            array('M1_SAMPLE_SWITCH', 'BFS5e650d757e5123.00941996', 88, true),
            array('M1_SAMPLE_SWITCH', 'BFS5e650d757e5123.00941996', 87, false),
        );
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::getFeatureSwitch}
     * tests
     *
     * @throws ReflectionException if there is a problem in stubbing model
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
        } catch (\Exception $e) {
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
        $configValue = $this->generateConfigValue();
        unset($configValue['M1_SAMPLE_SWITCH']);
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', $configValue);

        $this->assertEquals(
            array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 0),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getFeatureSwitchValueByName',
                array('M1_SAMPLE_SWITCH')
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
        $configValue['M1_SAMPLE_SWITCH']['rolloutPercentage'] = 57;
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', $configValue);

        $this->assertEquals(
            array(
                'value' => true,
                'defaultValue' => false,
                'rolloutPercentage' => 57),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getFeatureSwitchValueByName',
                array('M1_SAMPLE_SWITCH')
            )
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'switches', null);
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is 0 should return default value
     *
     * @covers ::isSwitchEnabled
     * @dateProvider isSwitchEnabledProvider
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
            ->with('M1_SAMPLE_SWITCH')->willReturn($feature);
        $this->assertEquals(
            $expectedValue,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('M1_SAMPLE_SWITCH')
            )
        );
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is 100 should return feature value
     *
     * @covers ::isSwitchEnabled
     * @dateProvider isSwitchEnabledProvider
     * @param bool $expected_value
     * @throws ReflectionException
     */
    public function isSwitchEnabled_whenRolloutPercentageIs100_shouldReturnValue($expected_value)
    {
        $this->adjustCurrentMock(array('getFeatureSwitchValueByName'));
        $feature = array(
            'value' => $expected_value,
            'defaultValue' => false,
            'rolloutPercentage' => 100
        );
        $this->currentMock->expects($this->once())->method('getFeatureSwitchValueByName')
            ->with('M1_SAMPLE_SWITCH')->willReturn($feature);
        $this->assertEquals(
            $expected_value,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('M1_SAMPLE_SWITCH')
            )
        );
    }

    /**
     * @test
     * When call isSwitchEnabled when feature rollout percentage is betwen 0 and 100 should
     * - call IsInBucket() method
     * - return its value
     *
     * @covers ::isSwitchEnabled
     * @dateProvider isSwitchEnabledProvider
     * @param bool $expected_value
     * @throws ReflectionException
     */
    public function isSwitchEnabled_whenRolloutPercentageBetween0And100_shouldCallIsInBucketAndReturnItsResult($expected_value)
    {
        $this->adjustCurrentMock(array('getFeatureSwitchValueByName', 'isInBucket'));
        $feature = array(
            'value' => true,
            'defaultValue' => false,
            'rolloutPercentage' => 37
        );
        $this->currentMock->expects($this->once())->method('getFeatureSwitchValueByName')
            ->with('M1_SAMPLE_SWITCH')->willReturn($feature);
        $this->currentMock->expects($this->once())->method('isInBucket')
            ->with('M1_SAMPLE_SWITCH', 37)->willReturn($expected_value);

        $this->assertEquals(
            $expected_value,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isSwitchEnabled',
                array('M1_SAMPLE_SWITCH')
            )
        );
    }

    /**
     * Provides calculated value for @see isSwitchEnabled
     *
     * @return array of true and false
     */
    private function isSwitchEnabledProvider()
    {
        return array(array(true), array(false));
    }
}