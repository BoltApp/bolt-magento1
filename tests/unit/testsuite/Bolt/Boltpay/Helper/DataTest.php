<?php

require('TestHelper.php');

class Bolt_Boltpay_Helper_DataTest extends PHPUnit_Framework_TestCase {
    private $app = null;
    private $dataHelper = null;
    private $testHelper = null;

    public function setUp() {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->dataHelper = Mage::helper('boltpay/data');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    public function testCanUseBoltReturnsFalseIfDisabled() {
        $this->app->getStore()->setConfig('payment/boltpay/active', 0);
        $this->assertFalse($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsFalseIfNoItemsInCart() {
        $this->testHelper->createCheckout('guest');
        $this->assertFalse($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsTrueIfSkipPaymentIsEnabled() {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertTrue($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsTrueAmountWithinMinMaxBounds() {
        $this->app->getStore()->setConfig('payment/boltpay/min_order_total', 1);
        $this->app->getStore()->setConfig('payment/boltpay/max_order_total', 100);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertTrue($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsFalseIfAmountLessThanMin() {
        $this->app->getStore()->setConfig('payment/boltpay/min_order_total', 1000);
        $this->app->getStore()->setConfig('payment/boltpay/max_order_total', 1100);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertFalse($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsFalseIfAmountGreaterThanMax() {
        $this->app->getStore()->setConfig('payment/boltpay/min_order_total', 1);
        $this->app->getStore()->setConfig('payment/boltpay/max_order_total', 2);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertFalse($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsTrueForSkipPaymentEvenIfAmountGreaterThanMax() {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->app->getStore()->setConfig('payment/boltpay/min_order_total', 1);
        $this->app->getStore()->setConfig('payment/boltpay/max_order_total', 2);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertTrue($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsTrueForSkipPaymentEvenIfAmountLessThanMin() {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->app->getStore()->setConfig('payment/boltpay/min_order_total', 1000);
        $this->app->getStore()->setConfig('payment/boltpay/max_order_total', 2000);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addProduct(1, 2);
        $this->assertTrue($this->dataHelper->canUseBolt());
    }

    public function testCanUseBoltReturnsFalseIfBillingCountryNotWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfBillingCountryIsWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfSkipPaymentEvenIfBillingCountryIsNotWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfAllowSpecificIsFalse() {
    }
}