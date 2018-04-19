<?php

require('TestHelper.php');

class Bolt_Boltpay_Helper_DataTest extends PHPUnit_Framework_TestCase
{
    private $app = null;

    /**
     * @var $dataHelper Bolt_Boltpay_Helper_Data
     */
    private $dataHelper = null;

    /**
     * @var $testHelper Bolt_Boltpay_TestHelper
     */
    private $testHelper = null;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->dataHelper = Mage::helper('boltpay/data');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    public function testCanUseBoltReturnsFalseIfDisabled() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 0);
        $quote = $this->testHelper->getCheckoutQuote();

        $this->assertFalse($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsTrueIfSkipPaymentIsEnabled() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->testHelper->createCheckout('guest');
        $cart = $this->testHelper->addProduct(1, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsFalseIfBillingCountryNotWhitelisted() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(1, 2);
        $quote = $cart->getQuote();

        $this->assertFalse($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsTrueIfBillingCountryIsWhitelisted()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,US,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(1, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsTrueIfSkipPaymentEvenIfBillingCountryIsNotWhitelisted() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(1, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsTrueIfAllowSpecificIsFalse() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 0);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(1, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }
}
