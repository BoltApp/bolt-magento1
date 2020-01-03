<?php

require_once('MockingTrait.php');
require_once('TestHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Abstract
 */
class AbstractTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string name of class tested */
    protected $testClassName = 'Bolt_Boltpay_Model_Abstract';

    /** @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Abstract mock instance of the class tested */
    private $currentMock;

    /**
     * Setup test dependencies
     *
     * @throws Exception if testClassName is not defined
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->getMock();
    }

    /**
     * Reset current store to default
     */
    protected function tearDown()
    {
        Mage::app()->setCurrentStore('default');
    }

    /**
     * @test
     * that isAdmin returns expected value depending on current store
     *
     * @covers ::isAdmin
     * @dataProvider isAdmin_withVariousStores_determinesIfStoreIsAdminContextProvider
     *
     * @param int $storeId to set as current store
     * @param bool $expectedResult of the method call
     * @throws ReflectionException is isAdmin method doesn't exist
     */
    public function isAdmin_withVariousStores_determinesIfStoreIsAdminContext($storeId, $expectedResult)
    {
        Mage::app()->setCurrentStore($storeId);
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'isAdmin')
        );
    }

    /**
     * Provider for {@see isAdmin_withVariousStores_returnsExpectedValue}
     *
     * @return array containing store id and expected result of the method call
     */
    public function isAdmin_withVariousStores_determinesIfStoreIsAdminContextProvider()
    {
        return array(
            'Admin store'   => array('storeId' => 0, 'expectedResult' => true),
            'Default store' => array('storeId' => 1, 'expectedResult' => false),
        );
    }

    /**
     * @test
     * that createLayoutBlock returns expected block instance when provided with block name
     *
     * @covers ::createLayoutBlock
     * @dataProvider createLayoutBlock_withVariousBlockNames_returnsExpectedBlockClassProvider
     *
     * @param string $blockName provided to method
     * @param string $blockClass expected class of method call result
     * @throws ReflectionException if createLayoutBlock doesn't exist
     */
    public function createLayoutBlock_withVariousBlockNames_returnsExpectedBlockClass($blockName, $blockClass)
    {
        $block = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'createLayoutBlock',
            array($blockName)
        );
        $this->assertInstanceOf($blockClass, $block);
    }

    /**
     * Provider for {@see createLayoutBlock_withVariousBlockNames_returnsExpectedBlockClass}
     *
     * @return array containing block name and expected class result
     */
    public function createLayoutBlock_withVariousBlockNames_returnsExpectedBlockClassProvider()
    {
        return array(
            'Admin order create shipping method form' => array(
                'blockName'  => 'adminhtml/sales_order_create_shipping_method_form',
                'blockClass' => 'Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form'
            ),
            'Admin order create totals' => array(
                'blockName'  => 'adminhtml/sales_order_create_totals_shipping',
                'blockClass' => 'Mage_Adminhtml_Block_Sales_Order_Create_Totals'
            )
        );
    }
}
