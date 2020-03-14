<?php

require_once('MockingTrait.php');

/**
 * @coversDefaultClass Bolt_Boltpay_BoltGlobalTrait
 */
class Bolt_Boltpay_BoltGlobalTraitTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of the class tested. */
    protected $testClassName = 'Bolt_Boltpay_BoltGlobalTrait';

    /**
     * @test
     * that boltHelper method returns instance of main helper when called directly from trait
     *
     * @covers Bolt_Boltpay_BoltGlobalTrait::boltHelper
     * @throws Exception if test class name is not specified
     */
    public function boltHelper_whenNotOverridden_returnsMainHelper()
    {
        /** @var Bolt_Boltpay_BoltGlobalTrait $currentMock */
        $currentMock = $this->getTestClassPrototype()->getMockForTrait();
        $this->assertInstanceOf('Bolt_Boltpay_Helper_Data', $currentMock->boltHelper());
    }
}
