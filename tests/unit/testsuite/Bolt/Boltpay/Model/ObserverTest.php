<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class Bolt_Boltpay_Model_ObserverTest
 *
 * @coversDefaultClass Bolt_Boltpay_Model_Observer
 */
class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Observer';

    /**
     * @var MockObject\Bolt_Boltpay_Model_Observer The mocked instance the test class
     */
    private $testClassMock;

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * Generate dummy products for testing purposes
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'));
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @test
     */
    public function getModel_returnsBoltBoltpayModelObserver()
    {
        $observer = Mage::getModel('boltpay/Observer');

        $this->assertInstanceOf('Bolt_Boltpay_Model_Observer', $observer);
    }

    /**
     * @test
     *
     * @covers ::addMessageWhenCapture
     */
    public function addMessageWhenCapture_whenPaymentMethodIsBold_setsPreparedMessageCorrectly()
    {
        $incrementId = '100000001';
        $order = $this->getMockBuilder('Mage_Sales_Model_Order')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(['getIncrementId'])
            ->getMock();

        $order->method('getIncrementId')
            ->will($this->returnValue($incrementId));

        $orderPayment = $this->getMockBuilder('Mage_Sales_Model_Order_Payment')
            ->setMethods(['getMethod', 'getOrder'])
            ->enableOriginalConstructor()
            ->getMock();

        $orderPayment->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $orderPayment->method('getOrder')
            ->willReturn($order);

        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $orderPayment));

        $this->assertEquals('Magento Order ID: "' . $incrementId . '".', $orderPayment->getData('prepared_message'));
    }

    /**
     * @test
     *
     * @covers ::getInvoiceItemsFromShipment
     *
     * @throws ReflectionException
     */
    public function getInvoiceItemsFromShipment_returnsCorrectInvoiceItems()
    {
        $this->testClassMock = $this->getTestClassPrototype()->setMethods(null)->getMock();

        $orderItem = $this->getMockBuilder('Mage_Sales_Model_Order_Item')
            ->setMethods(['getQtyOrdered', 'getQtyInvoiced', 'canInvoice'])
            ->getMock();
        $orderItem->method('getQtyOrdered')->willReturn(3);
        $orderItem->method('getQtyInvoiced')->willReturn(1);
        $orderItem->method('canInvoice')->willReturn(true);

        $shipmentItem = $this->getMockBuilder('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(['getOrderItem', 'getOrderItemId', 'getQty'])
            ->getMock();
        $shipmentItem->method('getOrderItem')->willReturn($orderItem);
        $shipmentItem->method('getOrderItemId')->willReturn('100000001');
        $shipmentItem->method('getQty')->willReturn(3);

        $shipment = $this->getMockBuilder('Mage_Sales_Model_Order_Shipment')
            ->setMethods(['getAllItems'])
            ->getMock();
        $shipment->method('getAllItems')->willReturn([$shipmentItem]);

        $expected = ['100000001' => 2];
        $result = TestHelper::callNonPublicFunction($this->testClassMock, 'getInvoiceItemsFromShipment', [$shipment]);

        $this->assertEquals($expected, $result);
    }
}