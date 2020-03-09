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
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper'))->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('getFeatureSwitches'))->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
    }

    /**
     * Stubs core/config model for {@see Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * tests
     *
     * @throws ReflectionException  if there is a problem in stubbing model
     */
    private function updateFeatureSwitchesSetUp()
    {
        $this->configMock = $this->getMockBuilder('Mage_Core_Model_Config')
            ->setMethods(
                array(
                    'saveConfig',
                    'cleanCache',
                )
            )->getMock();
        Bolt_Boltpay_TestHelper::stubModel('core/config', $this->configMock);
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
        $expectedConfigValue = '{"M1_BOLT_ENABLED":{"value":true,"defaultValue":false,"rolloutPercentage":100},"M1_SAMPLE_SWITCH":{"value":true,"defaultValue":false,"rolloutPercentage":0}}';

        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willReturn($boltAnswer);
        $this->configMock->expects($this->once())->method('saveConfig')
            ->with('payment/boltpay/featureSwitches',$expectedConfigValue);
        $this->configMock->expects($this->once())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();

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
}