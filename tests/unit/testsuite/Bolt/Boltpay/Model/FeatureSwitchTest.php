<?php
require_once('TestHelper.php');
require_once('MockingTrait.php');

class Bolt_Boltpay_Model_FeatureSwitchesTest extends PHPUnit_Framework_TestCase
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
     * @test
     */
    public function updateSwitchesFromBolt_saveFeatureSwitchesAsConfig()
    {
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
        $configMock = $this->getMockBuilder('Mage_Core_Model_Config')
            ->setMethods(
                array(
                    'saveConfig',
                    'cleanCache',
                )
            )->getMock();
        $configMock->expects($this->once())->method('saveConfig')
            ->with('payment/boltpay/featureSwitches',$expectedConfigValue);
        $configMock->expects($this->once())->method('cleanCache');
        Bolt_Boltpay_TestHelper::stubModel('core/config', $configMock);
        $this->currentMock->updateSwitchesFromBolt();

        Bolt_Boltpay_TestHelper::restoreModel('core/config');
    }
}

