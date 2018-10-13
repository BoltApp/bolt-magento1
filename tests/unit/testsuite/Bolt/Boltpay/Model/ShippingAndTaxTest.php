<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Model_ShippingAndTaxTest
 */
class Bolt_Boltpay_Model_ShippingAndTaxTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Bolt_Boltpay_TestHelper|null
     */
    private $testHelper;

    /**
     * @var Bolt_Boltpay_Model_ShippingAndTax
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $this->currentMock =  Mage::getModel('boltpay/shippingAndTax');
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
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1');
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Unit test for get adjusted shipping amount with no discount
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: none
     */
    public function testGetAdjustedShippingAmountWithNoDiscount()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountedSubtotal = 100;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotalWithDiscount(100);

        $expected = 50;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * Unit test for get adjusted shipping amount with shipping discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on shipping
     */
    public function testGetAdjustedShippingAmountWithShippingDiscount()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountedSubtotal = 100;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotalWithDiscount(75); // Discount on shipping: subtotal - shipping_amount * 50% = $75

        $expected = 25;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * Unit test for get adjusted shipping amount with cart discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on quote
     */
    public function testGetAdjustedShippingAmountWithCartDiscount()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountedSubtotal = 50;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotalWithDiscount(50); // Discount on quote: subtotal * 50% = $50

        $expected = 50;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * Unit test for get adjusted shipping amount with cart discount (50%) and shipping discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on subtotal and 50% on shipping method
     */
    public function testGetAdjustedShippingAmountCartAndShippingDiscount()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountedSubtotal = 50; // Discount on cart: 50%

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotalWithDiscount(25); // Discount on shipping: subtotal * 50% - shipping_amount * 50% = $25

        $expected = 25;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

        $this->assertEquals($expected, $result);
    }

    public function testShippingLabel()
    {
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('United Parcel Service');
        $rate->method('getMethodTitle')->willReturn('2 Day Shipping');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('United Parcel Service - 2 Day Shipping', $label);
    }

    public function testShippingLabel_notShowShippingTableLatePrefix()
    {
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('Shipping Table Rates');
        $rate->method('getMethodTitle')->willReturn('Free shipping (5 - 7 business days)');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('Free shipping (5 - 7 business days)', $label);
    }

    public function testShippingLabel_notDuplicateCommonPrefix()
    {
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('USPS');
        $rate->method('getMethodTitle')->willReturn('USPS Two days');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('USPS Two days', $label);
    }

    public function testShippingLabel_notDuplicateUPS()
    {
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('United Parcel Service');
        $rate->method('getMethodTitle')->willReturn('UPS Business 2 Days');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('UPS Business 2 Days', $label);
    }
}