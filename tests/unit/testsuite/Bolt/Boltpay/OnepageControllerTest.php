<?php

require_once('TestHelper.php');
require_once('controllers/OnepageController.php');

class Bolt_Boltpay_OnepageCheckoutIntegrationTest extends PHPUnit_Framework_TestCase {
    private $app = null;
    private $testHelper;
    private $controller;
    private $response;

    public function setUp() {
        $this->response = new Mage_Core_Controller_Response_Http();
        $this->response->headersSentThrowsException = false;
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $this->testHelper->resetApp();
        $this->app = Mage::app('default');
        $this->controller = new Bolt_Boltpay_OnepageController($this->app->getRequest(), $this->response);
    }

    public function testCheckoutSaveOrderActionCallbackSuccess() {
        $this->_initCart('boltpay');
        $this->_makeAuthorizeCallbackRequest();
        $this->controller->saveOrderAction();
        $order = Mage::getModel('sales/order')->loadByIncrementId(
            $this->controller->getOnepage()->getQuote()->getReservedOrderId());
        $orderPayment = $order->getPayment();
        $this->assertEquals('flatrate_flatrate', $order->getShippingMethod());

        // Assert the bolt attributes
        $this->assertEquals('boltpay', $orderPayment->getMethod());
        $this->assertEquals('authorize', $orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertEquals('ABCD-1234-EFGH', $orderPayment->getAdditionalInformation('bolt_reference'));

        // Assert transactions
        $transactions =  Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $order->getEntityId()));
        $orderTransactionId = 'ABCD-1234-EFGH-' . $order->getEntityId() . '-order';
        $this->assertEquals(2, sizeof($transactions));
        foreach ( $transactions as $t) {
            $this->assertArrayHasKey($t->getTxnType(), array('order' => 0, 'authorization' => 1));
            $this->assertArrayHasKey($t->getTxnId(), array('ABCD-1234-EFGH' => 0, $orderTransactionId => 0));
            $this->assertEquals(0, $t->getIsClosed());
        }
    }

    public function testCheckoutSaveOrderWhenMethodIsNotBolt() {
        $this->_initCart('checkmo');
        $this->controller->saveOrderAction();
        $order = Mage::getModel('sales/order')->loadByIncrementId(
            $this->controller->getOnepage()->getQuote()->getReservedOrderId());
        $orderPayment = $order->getPayment();
        $this->assertEquals('flatrate_flatrate', $order->getShippingMethod());

        // Assert the bolt attributes
        $this->assertEquals('pending', $order->getStatus());
        $this->assertEquals('checkmo', $orderPayment->getMethod());
        $this->assertEquals('', $orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertEquals('', $orderPayment->getAdditionalInformation('bolt_reference'));

        // Assert transactions
        $transactions =  Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', array('eq' => $order->getEntityId()));
        $this->assertEquals(0, sizeof($transactions));
    }

    public function testCheckoutSaveOrderWhenStatusIsAuthorize() {

    }

    public function testCheckoutSaveOrderWhenStatusIsCapture() {

    }

    public function testCheckoutSaveOrderWithUserLoggedInWhenStatusIsAuthorize() {

    }

    private function _initCart($paymentMethod) {
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $addressData = array(
            'firstname' => 'Vagelis',
            'lastname' => 'Bakas',
            'street' => 'Sample Street 10',
            'city' => 'Somewhere',
            'postcode' => '123456',
            'telephone' => '123456',
            'country_id' => 'US',
            'region_id' => 12
        );
        $this->testHelper->addTestBillingAddress($addressData);
        $this->testHelper->addTestFlatRateShippingAddress($addressData, $paymentMethod);
        $this->testHelper->addPaymentToQuote($paymentMethod);
    }

    private function _makeAuthorizeCallbackRequest() {
        $this->app->getRequest()
            ->setPost('payment', array(
                'reference' => 'ABCD-1234-EFGH',
                'method' => 'boltpay',
                'transaction_status' => 'authorize'
            ));
    }
}