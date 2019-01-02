<?php

require_once('TestHelper.php');

class Bolt_Boltpay_Helper_DataTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    private $app = null;

    /**
     * @var $dataHelper Bolt_Boltpay_Helper_Data
     */
    private $dataHelper = null;

    /**
     * @var $testHelper Bolt_Boltpay_TestHelper
     */
    private $testHelper = null;

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
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

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
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->testHelper->createCheckout('guest');
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $quote = $cart->getQuote();

        $result =  $this->dataHelper->canUseBolt($quote);
        $this->assertTrue($result);
    }

    public function testCanUseBoltReturnsFalseIfBillingCountryNotWhitelisted() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
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
        $cart = $this->testHelper->addProduct(self::$productId, 2);
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
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    public function testCanUseBoltReturnsTrueIfAllowSpecificIsFalse() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 0);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }
}
