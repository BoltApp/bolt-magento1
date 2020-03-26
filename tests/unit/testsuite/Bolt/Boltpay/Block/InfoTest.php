<?php

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Info
 */
class Bolt_Boltpay_Block_InfoTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Info Mocked instance of the block being tested
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Info')
            ->setMethods(array('setTemplate'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
    }

    /**
     * @test
     * Verify that internal constructor sets the template correctly
     *
     * @covers ::_construct
     */
    public function _construct()
    {
        $this->currentMock->expects($this->atLeastOnce())->method('setTemplate')->with('boltpay/info.phtml');

        TestHelper::callNonPublicFunction($this->currentMock, '_construct');
    }
}