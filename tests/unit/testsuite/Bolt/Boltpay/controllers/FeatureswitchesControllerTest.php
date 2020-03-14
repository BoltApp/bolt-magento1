<?php

require_once 'Bolt/Boltpay/controllers/FeatureswitchesController.php';
require_once 'MockingTrait.php';
require_once 'TestHelper.php';

/**
 * @coversDefaultClass Bolt_Boltpay_FeatureswitchesController
 */
class Bolt_Boltpay_FeatureswitchesControllerTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_FeatureswitchesController';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_FeatureswitchesController Mocked instance of the class being tested
     */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_FeatureSwitch
     */
    private $featureSwitchMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            //->setMethods(array('getResponse', 'sendResponse', 'getRequestData'))
            ->setMethods(array('sendResponse'))
            ->getMock();
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('updateFeatureSwitches'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
    }

    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreSingleton('boltpay/featureSwitch');
        parent::tearDown();
    }

    /**
     * @test
     * when call changedAction and updateFeatureSwitches call return success response
     *
     * @covers ::changedAction
     */
    public function changedAction_whenUpdateFeatureSwithesReturnTrue_returnsSuccessResponse()
    {
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willReturn(true);
        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                200,
                array('status' => 'success')
            );
        $this->currentMock->changedAction();
    }

    /**
     * @test
     * when call changedAction and updateFeatureSwitches call return failure response
     *
     * @covers ::changedAction
     */
    public function changedAction_whenUpdateFeatureSwithesReturnFalse_returnsFailureResponse()
    {
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willReturn(false);
        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                422,
                array('status' => 'failure')
            );
        $this->currentMock->changedAction();
    }
}