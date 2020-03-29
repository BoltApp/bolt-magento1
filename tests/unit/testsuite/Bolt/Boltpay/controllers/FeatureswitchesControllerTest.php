<?php

use Bolt_Boltpay_TestHelper as TestHelper;

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
     * when call updateAction and updateFeatureSwitches call return success response
     *
     * @covers ::updateAction
     */
    public function updateAction_whenUpdateFeatureSwithesReturnTrue_returnsSuccessResponse()
    {
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willReturn(true);
        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                200,
                array('status' => 'success')
            );
        $this->currentMock->updateAction();
    }

    /**
     * @test
     * when call updateAction and updateFeatureSwitches call return failure response
     *
     * @covers ::updateAction
     */
    public function updateAction_whenUpdateFeatureSwitchesThrowsException_returnsFailureResponse()
    {
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willThrowException(new GuzzleHttp\Exception\RequestException('Test exception', null));
        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                422,
                array('status' => 'failure')
            );
        $this->currentMock->updateAction();
    }

    /**
     * @test
     * when call _construct should set requestMustBeSigned to False
     *
     * @covers ::_construct
     */
    public function _construct_always_shouldSetRequestMustBeSignedToFalse()
    {
        TestHelper::setNonPublicProperty($this->currentMock, 'requestMustBeSigned', true);
        $this->currentMock->_construct();
        $this->assertFalse(TestHelper::getNonPublicProperty($this->currentMock, 'requestMustBeSigned'));
    }
}