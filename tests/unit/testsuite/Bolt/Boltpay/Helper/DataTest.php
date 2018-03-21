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

    public function testCanUseBoltReturnsFalseIfBillingCountryNotWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfBillingCountryIsWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfSkipPaymentEvenIfBillingCountryIsNotWhitelisted() {
    }

    public function testCanUseBoltReturnsTrueIfAllowSpecificIsFalse() {
    }
}