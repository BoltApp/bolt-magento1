<?php

class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
{
    const CUSTOMER_ID = 100;
    const QUOTE_ID = 200;
    const QUOTE_PAYMENT_ID = 300;
    const CUSTOMER_EMAIL = 'abc@test.com';
    const CUSTOMER_BOLT_USER_ID = "13456";

    private $checkout = null;
    /**
     * @var Mage_Sales_Model_Quote
     */
    private $quote = null;
    private $order = null;

    /**
     * @var Mage_Customer_Model_Session
     */
    private $session = null;
    private $quotePayment = null;
    private $orderPayment = null;

    /**
     * @var Mage_Customer_Model_Customer
     */
    private $customer = null;

//
    public function setUp()
    {
        $this->order    = $this->createMock(Mage_Sales_Model_Order::class);
        $this->session  = Mage::getSingleton('customer/session');
        $this->quote    = Mage::getSingleton('checkout/session')->getQuote();

        $this->orderPayment = $this->createMock(Mage_Sales_Model_Order_Payment::class);
        $this->customer = Mage::getModel('customer/customer');
    }

    public function testCheckObserverClass()
    {
        $observer = Mage::getModel('boltpay/Observer');

        $this->assertInstanceOf('Bolt_Boltpay_Model_Observer', $observer);
    }

    public function testSetBoltUserId()
    {
        $order = null;
        $this->setEmptyQuoteWithCustomer();

        $this->session->setBoltUserId(self::CUSTOMER_BOLT_USER_ID);

        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order' => $order, 'quote' => $this->quote));

        $this->assertEquals(self::CUSTOMER_BOLT_USER_ID, $this->quote->getCustomer()->getBoltUserId());
    }

    public function testAddMessageWhenCapture()
    {
        $incrementId = '100000001';
        $order = $this->createPartialMock(Mage_Sales_Model_Order::class, ['getIncrementId']);

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

    // TODO: rewrite it. Transaction behaviour was changed.
    public function testOrderTransactionIsCreated()
    {
        $quote = $this->quote;
        $this->quotePayment = $this->_createQuotePayment(
            self::QUOTE_PAYMENT_ID,
            $quote,
            Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $this->quote->setPayment($this->quotePayment);

        $this->orderPayment = $this->createPartialMock(Mage_Sales_Model_Order_Payment::class,
            ['getAdditionalInformation', 'addTransaction', 'getData', 'getPayment']);

        $this->orderPayment->method('getAdditionalInformation')
            ->with('bolt_reference')
            ->willReturn('AAA-BBB-CCC');

        $this->orderPayment->method('getAdditionalInformation')
            ->with('bolt_transaction_status')
            ->willReturn(Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED);

        $this->orderPayment->method('addTransaction')
            ->with(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, "Order ");

        $this->orderPayment->method('getData')
            ->with('method')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $this->order->method('getPayment')
            ->willReturn($this->orderPayment);
    }

//    public function handleOrderUpdate(Varien_Object $order) {
//        try {
//            $orderPayment = $order->getPayment();
//            $reference = $orderPayment->getAdditionalInformation('bolt_reference');
//            $transactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
//            $orderPayment->setTransactionId(sprintf("%s-%d-order", $reference, $order->getId()));
//            $orderPayment->addTransaction(
//                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, "Order ");
//            $order->save();
//
//            $orderPayment->setData('auto_capture', $transactionStatus == self::TRANSACTION_COMPLETED);
//            $this->handleTransactionUpdate($orderPayment, $transactionStatus, null);
//        } catch (Exception $e) {
//            $error = array('error' => $e->getMessage());
//            Mage::log($error, null, 'bolt.log');
//            throw $e;
//        }
//    }

    public function testVerifyOrderContents()
    {
        $observerModel = $this->getMockBuilder(Bolt_Boltpay_Model_Observer::class)
            ->enableOriginalConstructor()
            ->setMethods(array('proceedTransmit', 'getBoltApiHelper', 'sendOrderEmail'))
            ->getMock();

        $quote = $this->quote;
        $this->order = $this->getMockBuilder(Mage_Sales_Model_Order::class)
            ->enableOriginalConstructor()
            ->getMock()
        ;

        $history = $this->createMock('Mage_Sales_Model_Order_Status_History');

        $this->order->expects($this->any())
            ->method('addStatusHistoryComment')
            ->willReturn($history);


        $this->quotePayment = $this->_createQuotePayment(
            self::QUOTE_PAYMENT_ID,
            $quote,
            Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $this->_createGuestCheckout(
            1,
            2
        );

        $quote->setPayment($this->quotePayment);

        $observerObject = new Varien_Object();
        $observerObject->setData('event', new Varien_Object());
        $observerObject->getEvent()->addData(array(
            'quote' => $quote,
            'order' => $this->order
        ));

        $transmitResponse = new stdClass();
        $transmitResponse->is_valid = 1;
        $observerModel->method('proceedTransmit')
            ->will($this->returnValue($transmitResponse));

        $observerModel->verifyOrderContents($observerObject);

        //TODO: does it make sense?
        $this->assertTrue(true);

    }

    /**
     * @param $id
     * @throws Exception
     */
    private function _clearCustomerBoltUserId($id)
    {
        /** @var Mage_Customer_Model_Customer $existingCustomer */
        $existingCustomer = Mage::getModel('customer/customer')->load($id);
        $existingCustomer->setBoltUserId(null);
        $existingCustomer->save();

        $this->assertEquals(null, $existingCustomer->getBoltUserId());
    }

    private function _createQuotePayment($quotePaymentId, $quote, $method)
    {
        $quotePayment = Mage::getModel('sales/quote_payment');
        $quotePayment->setMethod($method);
        $quotePayment->setId($quotePaymentId);
        $quotePayment->setQuote($quote);
        $quotePayment->save();

        return $quotePayment;
    }

    private function _createGuestCheckout($productId, $quantity)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $cart = Mage::getSingleton('checkout/cart');
        $param = array(
            'product' => $productId,
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

    private function setEmptyQuoteWithCustomer()
    {
        $this->customer->setId(self::CUSTOMER_ID);
        $this->customer->setEmail(self::CUSTOMER_EMAIL);

        $this->quote->setCustomer($this->customer);
    }
}
