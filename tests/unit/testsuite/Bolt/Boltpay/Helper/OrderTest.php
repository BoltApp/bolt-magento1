<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Helper_OrderTest
 */
class Bolt_Boltpay_Helper_OrderTest extends PHPUnit_Framework_TestCase
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
     * @var Bolt_Boltpay_Helper_Order
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->currentMock = new Bolt_Boltpay_Helper_Order();
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

    public function testStoreHasAllCartItems()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = $this->currentMock->storeHasAllCartItems($cart->getQuote());

        $this->assertTrue($result);
    }
}