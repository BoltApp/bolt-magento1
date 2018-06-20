<?php

/**
 * Class Bolt_Boltpay_Model_ObserverTest
 */
class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
{
    const CUSTOMER_ID = 100;
    const QUOTE_ID = 200;
    const QUOTE_PAYMENT_ID = 300;
    const CUSTOMER_EMAIL = 'abc@test.com';
    const CUSTOMER_BOLT_USER_ID = "13456";

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Mage_Sales_Model_Quote
     */
    private $quote = null;
    /**
     * @var Mage_Sales_Model_Order
     */
    private $order = null;

    /**
     * @var Mage_Customer_Model_Session
     */
    private $session = null;

    /**
     * @var Mage_Sales_Model_Quote_Payment
     */
    private $quotePayment = null;

    /**
     * @var Mage_Sales_Model_Order_Payment
     */
    private $orderPayment = null;

    /**
     * @var Mage_Customer_Model_Customer
     */
    private $customer = null;

    /**
     * Generate dummy products for testing purposes
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
    }

    /**
     * Delete dummy products after the test
     * @inheritdoc
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @inheritdoc
     *
     * @throws Varien_Exception
     */
    public function setUp()
    {
        $this->order = $this->getMockBuilder(Mage_Sales_Model_Order::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->session  = Mage::getSingleton('customer/session');
        $this->quote    = Mage::getSingleton('checkout/session')->getQuote();

        $this->orderPayment = $this->getMockBuilder(Mage_Sales_Model_Order_Payment::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->customer = Mage::getModel('customer/customer');
    }

    /**
     * @inheritdoc
     */
    public function testCheckObserverClass()
    {
        $observer = Mage::getModel('boltpay/Observer');

        $this->assertInstanceOf('Bolt_Boltpay_Model_Observer', $observer);
    }

    /**
     * @inheritdoc
     * @throws Varien_Exception
     */
    public function testSetBoltUserId()
    {
        $order = null;
        $this->setEmptyQuoteWithCustomer();

        $this->session->setBoltUserId(self::CUSTOMER_BOLT_USER_ID);

        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order' => $order, 'quote' => $this->quote));

        $this->assertEquals(self::CUSTOMER_BOLT_USER_ID, $this->quote->getCustomer()->getBoltUserId());
    }

    /**
     * @inheritdoc
     */
    public function testAddMessageWhenCapture()
    {
        $incrementId = '100000001';
        $order = $this->getMockBuilder(Mage_Sales_Model_Order::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(['getIncrementId'])
            ->getMock();

        $order->method('getIncrementId')
            ->will($this->returnValue($incrementId));

        $orderPayment = $this->getMockBuilder(Mage_Sales_Model_Order_Payment::class)
            ->setMethods(['getMethod', 'getOrder'])
            ->enableOriginalConstructor()
            ->getMock();

        $orderPayment
            ->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $orderPayment->method('getOrder')
            ->willReturn($order);

        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $orderPayment));

        $this->assertEquals('Magento Order ID: "'.$incrementId.'".', $orderPayment->getData('prepared_message'));
    }

    /**
     * @inheritdoc
     * @throws Varien_Exception
     */
    public function testVerifyOrderContents()
    {
        $observerModel = $this->getMockBuilder(Bolt_Boltpay_Model_Observer::class)
            ->enableOriginalConstructor()
            ->setMethods(array('getBoltApiHelper', 'sendOrderEmail'))
            ->getMock();

        $quote = $this->quote;
        $this->order = $this->getMockBuilder(Mage_Sales_Model_Order::class)
            ->enableOriginalConstructor()
            ->getMock()
        ;

        $this->order
            ->method('setState')
            ->willReturn($this->order);

        $this->order
            ->expects($this->atLeastOnce())
            ->method('save');

        $this->order
            ->expects($this->atMost(2))
            ->method('save');

        $history = $this->getMockBuilder(Mage_Sales_Model_Order_Status_History::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->order->expects($this->any())
            ->method('addStatusHistoryComment')
            ->willReturn($history);

        $this->quotePayment = $this->_createQuotePayment(
            self::QUOTE_PAYMENT_ID,
            $quote,
            Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $this->_createGuestCheckout(
            self::$productId,
            2
        );

        $quote->setPayment($this->quotePayment);

        $observerObject = new Varien_Object();
        $observerObject->setData('event', new Varien_Object());
        $observerObject->getEvent()->addData(array(
            'quote' => $quote,
            'order' => $this->order
        ));

        $observerModel->verifyOrderContents($observerObject);

    }

    /**
     * @inheritdoc
     */
    public function testSendOrderEmail()
    {
        /** @var Bolt_Boltpay_Model_Observer $observerModel */
        $methods = [];
        $observerModel = $this->getMockBuilder(Bolt_Boltpay_Model_Observer::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(empty($methods) ? null : $methods)
            ->getMock();

        $this->order = $this->getMockBuilder(Mage_Sales_Model_Order::class)
            ->setMethods(array('getIncrementId', 'addStatusHistoryComment', 'addStatusHistory', 'getPayment', 'sendNewOrderEmail'))
            ->getMock()
        ;

        $incrementId = '100000001';
        $this->order->method('getIncrementId')
            ->will($this->returnValue($incrementId));

        $orderPayment = $this->getMockBuilder(Mage_Sales_Model_Order_Payment::class)
            ->setMethods(['getMethod', 'getOrder'])
            ->enableOriginalConstructor()
            ->getMock();

        $orderPayment
            ->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $orderPayment->method('getOrder')
            ->willReturn($this->order);
        $this->order->method('getPayment')
            ->willReturn($orderPayment);

        $history = $this->getMockBuilder('Mage_Sales_Model_Order_Status_History')
            ->setMethods(array('getStatus'))
            ->getMock();

        $history->method('getStatus')
            ->will($this->returnValue('test_status'));

        $this->order->expects($this->any())
            ->method('addStatusHistoryComment')
            ->willReturn($history);

        $this->order
            ->method('sendNewOrderEmail')
            ->willReturnSelf();

        $observerModel->sendOrderEmail($this->order);

        $this->assertTrue($history->getIsCustomerNotified());
        $this->assertEquals('test_status', $history->getStatus());
    }

    /**
     * @inheritdoc
     *
     * @param $quotePaymentId
     * @param $quote
     * @param $method
     * @return false|Mage_Core_Model_Abstract
     * @throws Varien_Exception
     */
    private function _createQuotePayment($quotePaymentId, $quote, $method)
    {
        $quotePayment = Mage::getModel('sales/quote_payment');
        $quotePayment->setMethod($method);
        $quotePayment->setId($quotePaymentId);
        $quotePayment->setQuote($quote);
        $quotePayment->save();

        return $quotePayment;
    }

    /**
     * @inheritdoc
     *
     * @param $productId
     * @param $quantity
     * @return Mage_Core_Model_Abstract
     * @throws Varien_Exception
     */
    private function _createGuestCheckout($productId, $quantity)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $cart = Mage::getSingleton('checkout/cart');
        $param = array(
            'product' => self::$productId,
            'qty' => 4
        );
        $cart->addProduct($product, $param);
        $cart->save();

        $checkout = Mage::getSingleton('checkout/type_onepage');
        $addressData = array(
            'firstname' => 'Vagelis',
            'lastname' => 'Bakas',
            'street' => 'Sample Street 10',
            'city' => 'Somewhere',
            'postcode' => '123456',
            'telephone' => '123456',
            'country_id' => 'US',
            'region_id' => 12, // id from directory_country_region table
        );
        $checkout->initCheckout();
        $checkout->saveCheckoutMethod('guest');
        $checkout->getQuote()->getBillingAddress()->addData($addressData);

        $shippingAddress = $checkout->getQuote()->getShippingAddress()->addData($addressData);
        $shippingAddress
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate')
            ->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $checkout->getQuote()->getPayment()->importData(array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE));
        $checkout->getQuote()->collectTotals()->save();

        $checkout = Mage::getSingleton('checkout/type_onepage');

        $checkout->initCheckout();

        $quoteItem = Mage::getModel('sales/quote_item')
            ->setProduct($product)
            ->setQty($quantity)
            ->setSku($product->getSku())
            ->setName($product->getName())
            ->setWeight($product->getWeight())
            ->setPrice($product->getPrice());

        $checkout->getQuote()
            ->addItem($quoteItem);


        $checkout->getQuote()->collectTotals()->save();
        $checkout->saveCheckoutMethod('guest');
        $checkout->saveShippingMethod('flatrate_flatrate');

        return $cart;
    }

    /**
     * @inheritdoc
     * @throws Varien_Exception
     */
    private function setEmptyQuoteWithCustomer()
    {
        $this->customer->setId(self::CUSTOMER_ID);
        $this->customer->setEmail(self::CUSTOMER_EMAIL);

        $this->quote->setCustomer($this->customer);
    }
}
