<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Traits_ValidatorTrait
 */
class Bolt_Boltpay_Model_Traits_ValidatorTraitTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Model_Traits_ValidatorTrait';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Traits_ValidatorTrait mock instance of trait tested
     */
    private $currentMock;

    /**
     * Setup test dependencies
     *
     * @throws Exception if testClassName property is not specified
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->getMockForTrait();
    }

    /**
     * @test
     * that resetRoundingDeltas method resets _roundingDeltas property to empty array
     *
     * @covers Bolt_Boltpay_Model_Traits_ValidatorTrait::resetRoundingDeltas
     */
    public function resetRoundingDeltas_always_resetsRoundingDeltasProperty()
    {
        $this->currentMock->_roundingDeltas = array('10' => 0.01, '20' => 0.02);
        $this->currentMock->resetRoundingDeltas();
        $this->assertEquals(array(), $this->currentMock->_roundingDeltas);
    }

    /**
     * @test
     * that resetRoundingDeltas method resets _baseRoundingDeltas property to empty array
     *
     * @covers Bolt_Boltpay_Model_Traits_ValidatorTrait::resetRoundingDeltas
     */
    public function resetRoundingDeltas_always_resetsBaseRoundingDeltasProperty()
    {
        $this->currentMock->_baseRoundingDeltas = array('10' => 0.01, '20' => 0.02);
        $this->currentMock->resetRoundingDeltas();
        $this->assertEquals(array(), $this->currentMock->_baseRoundingDeltas);
    }
}
