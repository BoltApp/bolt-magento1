<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Setup
 */
class Bolt_Boltpay_Model_SetupTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var MockObject\Bolt_Boltpay_Model_Setup The mocked instance the test class
     */
    private $currentMock;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Setup';

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(null)->getMock();
    }

    /**
     * @test
     * When _modifyResourceDb call should set Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches to true
     * if ActionType is "data-upgrade" and to false otherwise     *
     *
     * @covers ::_modifyResourceDb
     * @dataProvider _modifyResourceDbProvider
     * @param $actionType string actionType parameter for tested method
     * @param $expectedResult bool expected value for Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches
     */
    public function _modifyResourceDb_depengingOnActionType_ShouldSetUpdateFeatureSwitchesToTrueOrFalse($actionType, $expectedResult)
    {
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = !$expectedResult;
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            '_modifyResourceDb',
            [
                $actionType,
                // $fromVersion and $toVersion both should be lower then bolt first version '0.0.9'
                // Otherwise parent::_modifyResourceDb will make its work
                '0.0.1',
                '0.0.2'
            ]
        );
        $this->assertEquals($expectedResult,Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches);
    }

    /**
     * Data provider for {@see _modifyResourceDb_depengingOnActionType_ShouldSetUpdateFeatureSwitchesToTrueOrFalse}
     *
     * @return array containing ActionType and expected value for UpdateFeatureSwitches flag
     */
    public function _modifyResourceDbProvider() {
        return array(
            array('install',false),
            array('upgrade',false),
            array('rollback',false),
            array('uninstall',false),
            array('data-install',false),
            array('data-upgrade',true),
        );
    }
}