<?php
require_once('TestHelper.php');

class Bolt_Boltpay_Helper_FeatureSwitches_ManagerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_FeatureSwitch_ManagerTrait Mocked instance of trait tested
     */
    private $currentMock;

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_FeatureSwitch_ManagerTrait')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(array('getFeatureSwitches'))
            ->getMockForTrait();
    }

    /**
     * @test
     */
    public function updateSwitchesFromBolt_saveFeatureSwitchesAsConfig()
    {
        if ( !function_exists( 'spl_object_hash' ) ) {
            function spl_object_hash( $object )
            {
                ob_start();
                var_dump( $object );
                preg_match( '[#(\d+)]', ob_get_clean(), $match );
                return $match[1];
            }
        }
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

        $this->currentMock->expects($this->once())->method('getFeatureSwitches')
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

