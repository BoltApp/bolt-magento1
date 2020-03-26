<?php

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Form
 */
class Bolt_Boltpay_Block_FormTest extends PHPUnit_Framework_TestCase
{
    const TEMPLATE = 'boltpay/form.phtml';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Form Mocked instance of the block being tested
     */
    private $currentMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Form')
            ->setMethods(array('setMethodLabelAfterHtml', 'setTemplate', 'setMethodTitle'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
    }

    /**
     * @test
     * Internal constructor when executed from admin scope
     *
     * @covers ::_construct
     */
    public function _construct_admin()
    {
        Mage::app()->getStore()->setId(Mage_Core_Model_App::ADMIN_STORE_ID);

        $this->currentMock->expects($this->never())->method('setMethodLabelAfterHtml');

        $this->assertCorrectTemplateWasSet($isCorrectTemplateSet);
        $this->currentMock->expects($this->once())->method('setMethodTitle')
            ->with(Mage::getStoreConfig('payment/boltpay/title'));

        TestHelper::callNonPublicFunction($this->currentMock, '_construct');
        $this->assertTrue($isCorrectTemplateSet);
    }

    /**
     * @test
     * Internal constructor when executed from frontend scope
     *
     * @covers ::_construct
     */
    public function _construct_frontend()
    {
        Mage::app()->getStore()->setId(1);

        $this->currentMock->method('setMethodLabelAfterHtml')->willReturnCallback(
            function ($html) {
                $this->assertNotEmpty($html);
            }
        );

        $this->assertCorrectTemplateWasSet($isCorrectTemplateSet);
        $this->currentMock->expects($this->once())->method('setMethodTitle')
            ->with(Mage::getStoreConfig('payment/boltpay/title'));

        TestHelper::callNonPublicFunction($this->currentMock, '_construct');
        $this->assertTrue($isCorrectTemplateSet);
    }

    /**
     * Will set $isCorrectTemplateSet variable to true if setTemplate is called with correct value at least once
     *
     * @param bool $isCorrectTemplateSet variable passed by reference to be set to true if setTemplate is called with
     * the correct value, otherwise set to false
     */
    private function assertCorrectTemplateWasSet(&$isCorrectTemplateSet)
    {
        $isCorrectTemplateSet = false;
        $this->currentMock->expects($this->atLeastOnce())->method('setTemplate')
            ->willReturnCallback(
                function ($template) use (&$isCorrectTemplateSet) {
                    if ($template == self::TEMPLATE) {
                        $isCorrectTemplateSet = true;
                    }

                    return $this->currentMock;
                }
            );
    }
}