<?php
require_once('TestHelper.php');
require_once('MockingTrait.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Model_FeatureSwitch
 */
class Bolt_Boltpay_Model_FeatureSwitchTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_FeatureSwitches Mocked instance of trait tested
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

    private function updateFeatureSwitches_setup()
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
        $this->updateFeatureSwitches_setup();
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

        Bolt_Boltpay_TestHelper::restoreModel('core/config');
    }

    /**
     * @test
     * When GraphQL call returns empty answer we should not save it
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_withNoSwitches_returnsWithoutSaving()
    {
        $this->updateFeatureSwitches_setup();
        $boltAnswer = '';
        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willReturn($boltAnswer);

        $this->configMock->expects($this->never())->method('saveConfig');
        $this->configMock->expects($this->never())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();

        Bolt_Boltpay_TestHelper::restoreModel('core/config');
    }

    /**
     * @test
     * When GraphQL call throws exception we should not change feature switches
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_whenException_returnsWithoutSaving()
    {
        $this->updateFeatureSwitches_setup();
        $this->boltHelperMock->expects($this->once())->method('getFeatureSwitches')
            ->willThrowException(new \Exception('Any exception'));

        $this->configMock->expects($this->never())->method('saveConfig');
        $this->configMock->expects($this->never())->method('cleanCache');

        $this->currentMock->updateFeatureSwitches();

        Bolt_Boltpay_TestHelper::restoreModel('core/config');
    }
}