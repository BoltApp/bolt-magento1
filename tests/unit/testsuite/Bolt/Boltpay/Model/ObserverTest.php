<?php

//class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
//{
//    const CUSTOMER_ID = 100;
//    const QUOTE_ID = 200;
//    const QUOTE_PAYMENT_ID = 300;
//    const CUSTOMER_EMAIL = 'abc@test.com';
//    const CUSTOMER_BOLT_USER_ID = "13456";

//    private $checkout = null;
//    private $quote = null;
//    private $order = null;
//    private $session = null;
//    private $quotePayment = null;
//    private $orderPayment = null;
//    private $customer = null;
//
//    public function setUp() {
//        $store = Mage::getModel('core/store')->load();
//        $this->checkout = $this->_createGuestCheckout(1, 2);
//        $this->quote = $this->_createQuote($store);
//        $this->assertEquals(1, $this->quote->getId());
//        $this->quotePayment = $this->_createQuotePayment(self::QUOTE_PAYMENT_ID, $this->quote, 'boltpay');
//        $this->quote->setPayment($this->quotePayment);
//
//        $this->order = $this->createMock(Mage_Sales_Model_Order::class);
//        $this->session = Mage::getSingleton('customer/session');
//        $this->orderPayment = $this->createMock(Mage_Sales_Model_Order_Payment::class);
//        $this->customer = Mage::getModel('customer/customer');
//        $this->customer->setId(self::CUSTOMER_ID);
//        $this->customer->setEmail(self::CUSTOMER_EMAIL);
//        $this->customer->save();
//    }
//
//    public function testEventCallsBoltObserverSaveOrderAfter() {
//        $this->quote->expects($this->once())
//            ->method('getPayment')
//            ->willReturn($this->payment);
//        Mage::dispatchEvent('checkout_type_onepage_save_order_after',
//            array('order' => $this->order, 'quote' => $this->quote));
//    }

    /**
     * Test Bolt user id is saved to the customer
     */
//    public function testBoltObserverSaveOrderAfterSavesBoltUserIdInCustomer() {
//        $this->_clearCustomerBoltUserId(self::CUSTOMER_ID);
//
//        $this->session->setData('bolt_user_id', self::CUSTOMER_BOLT_USER_ID);
//        $this->quote->method('getCustomer')->willReturn($this->customer);
//        $this->quote->expects($this->once())
//            ->method('getPayment')
//            ->willReturn($this->payment);
//
//        Mage::dispatchEvent('checkout_type_onepage_save_order_after',
//            array('order' => $this->order, 'quote' => $this->quote));
//
//        $storedCustomer = Mage::getModel('customer/customer')->load(self::CUSTOMER_ID);
//        $this->assertEquals(self::CUSTOMER_BOLT_USER_ID, $storedCustomer->getBoltUserId());
//    }

    /**
     * Test that bolt user id is not saved in the user if its not specified
     */
//    public function testDoesNotSaveBoltUserIdIfItsNotSpecified() {
//        $this->_clearCustomerBoltUserId(self::CUSTOMER_ID);
//        $this->assertEquals(null, $this->session->getBoltUserId());
//
//        $this->quote->method('getCustomer')->willReturn($this->customer);
//        $this->quote->expects($this->once())
//            ->method('getPayment')
//            ->willReturn($this->payment);
//
//        Mage::dispatchEvent('checkout_type_onepage_save_order_after',
//            array('order' => $this->order, 'quote' => $this->quote));
//
//        $storedCustomer = Mage::getModel('customer/customer')->load(self::CUSTOMER_ID);
//        $this->assertEquals(null, $storedCustomer->getBoltUserId());
//    }

//    public function testOrderTransactionIsCreated() {
//        $this->order->expects($this->once())
//            ->method('getPayment')
//            ->willReturn($this->orderPayment);
//        $this->payment->method('getMethod')->willReturn('boltpay');
//        $this->quote->expects($this->once())
//            ->method('getPayment')
//            ->willReturn($this->payment);
//        $this->orderPayment->method('getAdditionalInformation')->with('bolt_reference')->willReturn('AAA-BBB-CCC');
//        $this->orderPayment->method('getAdditionalInformation')->with('bolt_transaction_status')->willReturn('authorized');
//        $this->orderPayment->expects($this->once())
//            ->method('addTransaction')
//            ->with(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, "Order ");
//        $this->orderPayment->method('getData')->with('method')->willReturn('boltpay');
//        Mage::dispatchEvent('checkout_type_onepage_save_order_after',
//            array('order' => $this->order, 'quote' => $this->quote));
//    }

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

//    private function _clearCustomerBoltUserId($id) {
//        $existingCustomer = Mage::getModel('customer/customer')->load($id);
//        $existingCustomer->setBoltUserId(null);
//        $existingCustomer->save();
//        $this->assertEquals(null, $existingCustomer->getBoltUserId());
//    }

//    private function _createQuotePayment($quotePaymentId, $quote, $method) {
//        $quotePayment = Mage::getModel('sales/quote_payment');
//        $quotePayment->setMethod($method);
//        $quotePayment->setId($quotePaymentId);
//        $quotePayment->setQuote($quote);
//        $quotePayment->save();
//        return $quotePayment;
//    }

//    private function _createGuestCheckout($productId, $quantity) {
//        $product = Mage::getModel('catalog/product')->load($productId);
//        $cart = Mage::getSingleton('checkout/cart');
//        $param = array(
//            'product' => $productId,
//            'qty' => 4
//        );
//        $cart->addProduct($product, $param);
//        $cart->save();
//
//        $checkout = Mage::getSingleton('checkout/type_onepage');
//        $addressData = array(
//            'firstname' => 'Vagelis',
//            'lastname' => 'Bakas',
//            'street' => 'Sample Street 10',
//            'city' => 'Somewhere',
//            'postcode' => '123456',
//            'telephone' => '123456',
//            'country_id' => 'US',
//            'region_id' => 12, // id from directory_country_region table
//        );
//        $checkout->initCheckout();
//        $checkout->saveCheckoutMethod('guest');
//        $checkout->getQuote()->getBillingAddress()->addData($addressData);
//        $shippingAddress = $checkout->getQuote()->getShippingAddress()->addData($addressData);
//        $shippingAddress
//            ->setCollectShippingRates(true)
//            ->collectShippingRates()
//            ->setShippingMethod('flatrate_flatrate')
//            ->setPaymentMethod('boltpay');
//        $checkout->getQuote()->getPayment()->importData(array('method' => 'boltpay'));
//        $checkout->getQuote()->collectTotals()->save();
//        $service = Mage::getModel('sales/service_quote', $checkout->getQuote());
//        $service->submitAll();
//        $checkout = Mage::getSingleton('checkout/type_onepage');
//        $checkout->initCheckout();
//        $quoteItem = Mage::getModel('sales/quote_item')
//            ->setProduct($product)
//            ->setQty($quantity)
//            ->setSku($product->getSku())
//            ->setName($product->getName())
//            ->setWeight($product->getWeight())
//            ->setPrice($product->getPrice());
//        $checkout->getQuote()->addItem($quoteItem);
//        $billingAddressData = array(
//            'firstname' => 'Test',
//            'lastname' => 'Test',
//            'street' => 'Sample Street 10',
//            'city' => 'Somewhere',
//            'postcode' => '123456',
//            'telephone' => '123456',
//            'country_id' => 'US',
//            'region_id' => 12, // id from directory_country_region table
//        ); // billing address
//        $shippingAddressData = array(
//            'firstname' => 'Test',
//            'lastname' => 'Test',
//            'street' => 'Sample Street 10',
//            'city' => 'Somewhere',
//            'postcode' => '123456',
//            'telephone' => '123456',
//            'country_id' => 'US',
//            'region_id' => 12, // id from directory_country_region table
//        );// shipping address
//        $checkout->getQuote()->collectTotals()->save();
//        $checkout->saveCheckoutMethod('guest');
//        $checkout->saveShippingMethod('flatrate_flatrate');
//        return $cart;
//        $quote = Mage::getModel('sales/quote');
//        $product = Mage::getModel('catalog/product')->load($productId);
//        $quote->setStore($store);
//        $quote->addProdut($productId, $quantity);
//        $quote->save();
//        return $quote;
//    }
//}