<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Model_OrderTest
 */
class Bolt_Boltpay_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Bolt_Boltpay_TestHelper|null
     */
    private $testHelper = null;

    /**
     * @var Bolt_Boltpay_Model_Order
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->currentMock = Mage::getModel('boltpay/order');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    protected function tearDown()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
    }

    /**
     * Generate dummy products for testing purposes
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    public function testGetOutOfStockSKUs()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = (bool)$this->currentMock->getOutOfStockSKUs($cart->getQuote());

        $this->assertFalse($result);
    }

    /**
     * Test whether flags are correctly set after an email is sent and that no exceptions are thrown in the process
     */
    public function testSendOrderEmail()
    {
        /** @var Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');

        /** @var Mage_Sales_Model_Order $order */
        $this->order = $this->getMockBuilder('Mage_Sales_Model_Order')
            ->setMethods(array('getPayment', 'addStatusHistoryComment', 'queueNewOrderEmail'))
            ->getMock();

        $orderPayment = $this->getMockBuilder('Mage_Sales_Model_Order_Payment')
            ->setMethods(['save'])
            ->enableOriginalConstructor()
            ->getMock();

        $this->order->method('getPayment')
            ->willReturn($orderPayment);

        $this->order->setIncrementId(187);

        $history = Mage::getModel('sales/order_status_history');

        $this->order->expects($this->once())
            ->method('queueNewOrderEmail');

        $this->order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->willReturn($history);

        $this->assertNull($history->getIsCustomerNotified());
        $this->assertNull($orderPayment->getAdditionalInformation("orderEmailWasSent"));

        try {
            $orderModel->sendOrderEmail($this->order);
        } catch ( Exception $e ) {
            $this->fail('An exception was thrown while sending the email');
        }

        $this->assertTrue($history->getIsCustomerNotified());
        $this->assertEquals('true', $orderPayment->getAdditionalInformation("orderEmailWasSent"));
    }

}