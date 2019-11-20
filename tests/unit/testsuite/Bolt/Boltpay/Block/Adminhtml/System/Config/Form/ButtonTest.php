<?php

require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Adminhtml_System_Config_Form_Button
 */
class Bolt_Boltpay_Block_Adminhtml_System_Config_Form_ButtonTest extends PHPUnit_Framework_TestCase
{
    const STORE_ID = 1;
    const WEBSITE_ID = 1;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Adminhtml_System_Config_Form_Button The mocked instance of the block being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Layout The mocked instance of the layout object
     */
    private $layout;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        $this->layout = $this->getMockBuilder('Mage_Core_Model_Layout')
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Adminhtml_System_Config_Form_Button')
            ->setMethods(array('_toHtml', 'getLayout'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->currentMock->method('getLayout')->willReturn($this->layout);
    }

    /**
     * @test
     * Verifies that _toHtml method is invoked in method call
     *
     * @covers ::_getElementHtml
     */
    public function _getElementHtml()
    {
        $this->currentMock->expects($this->once())->method('_toHtml');
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            '_getElementHtml',
            array(
                new Varien_Data_Form_Element_Button()
            )
        );
    }

    /**
     * @test
     * Verifies rendering of the button using core button widget block
     *
     * @covers ::getButtonHtml
     */
    public function getButtonHtml()
    {
        $this->layout->expects($this->once())->method('createBlock')->with('adminhtml/widget_button');

        $result = $this->currentMock->getButtonHtml();
        $this->assertContains('id="boltpay_check_button"', $result);
        $this->assertContains('title="Check"', $result);
        $this->assertContains('onclick="javascript:check(); return false;"', $result);
    }

    /**
     * @test
     * Getting store id from store scope
     *
     * @covers ::getStoreId
     */
    public function getStoreId_fromStore()
    {
        Mage::getSingleton('adminhtml/config_data')->setStore(self::STORE_ID);
        $this->assertEquals(
            self::STORE_ID,
            $this->currentMock->getStoreId()
        );
    }

    /**
     * @test
     * Getting store id from website scope
     *
     * @covers ::getStoreId
     */
    public function getStoreId_fromWebsite()
    {
        Mage::getSingleton('adminhtml/config_data')->setStore(null);
        Mage::getSingleton('adminhtml/config_data')->setWebsite(self::WEBSITE_ID);
        $this->assertEquals(
            self::STORE_ID,
            $this->currentMock->getStoreId()
        );
    }

    /**
     * @test
     * Getting store id without scope
     *
     * @covers ::getStoreId
     */
    public function getStoreId_noScope()
    {
        Mage::getSingleton('adminhtml/config_data')->setStore(null);
        Mage::getSingleton('adminhtml/config_data')->setWebsite(null);
        $this->assertEquals(
            0,
            $this->currentMock->getStoreId()
        );
    }
}
