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
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Payment_Block_Info
     */
    private $infoMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Info')
            ->setMethods(array('getInfo'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->infoMock = $this->getMockBuilder('Mage_Payment_Block_Info')
            ->setMethods(array('getCcType','getCcLast4'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

    }

    /**
     * @test
     * that prepareSpecificInformation return a dummy cc info array
     * @covers ::_prepareSpecificInformation
     */
    public function prepareSpecificInformation()
    {
        $this->currentMock->expects(self::any())->method('getInfo')->willReturn($this->infoMock);
        $this->infoMock->expects(self::once())->method('getCcType')->willReturn('visa');
        $this->infoMock->expects(self::once())->method('getCcLast4')->willReturn('1111');
        $data = TestHelper::callNonPublicFunction($this->currentMock, '_prepareSpecificInformation', [null]);
        $this->assertEquals(
            [
                'Credit Card Type' => 'VISA',
                'Credit Card Number' => 'xxxx-1111'
            ], $data->getData()
        );
    }
}