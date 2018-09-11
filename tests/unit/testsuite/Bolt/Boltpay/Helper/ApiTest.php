<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Helper_ApiTest
 */
class Bolt_Boltpay_Helper_ApiTest extends PHPUnit_Framework_TestCase
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
     * @var Bolt_Boltpay_Helper_Api
     */
    private $currentMock;

    private $testBoltResponse;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        /** @var Mage_Core_Model_Store $appStore */
        $appStore = $this->app->getStore();
        $appStore->resetConfig();

        $this->testHelper = null;

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(['verify_hook_secret', 'verify_hook_api', 'getBoltContextInfo', 'getItemImageUrl'])
            ->enableOriginalConstructor()
            ->getMock();

        $appStore->setConfig('payment/boltpay/active', 1);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');
        $testBoltResponse->cart = new StdClass();
        $testBoltResponse->external_data = new StdClass();
        $testBoltResponse->cart->order_reference = '69';
        $testBoltResponse->cart->display_id = 100001069;
        $testBoltResponse->cart->currency = [];
        $testBoltResponse->cart->items = [];

        $this->testBoltResponse = (object) $testBoltResponse;
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

    public function testBuildCart()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $_quote = $cart->getQuote();
        $_quote->reserveOrderId();
        $_items = $_quote->getAllItems();
        $_multipage = true;
        $item = $_items[0];
        $product = $item->getProduct();
        $imageUrl = 'no-image';

        $this->currentMock->method('getItemImageUrl')
            ->will($this->returnValue($imageUrl));

        $expected = array (
            'order_reference' => $_quote->getParentQuoteId(),
            'display_id' => $_quote->getReservedOrderId()."|".$_quote->getId(),
            'items' =>
                array (
                    0 =>
                        array (
                            'reference' => $_quote->getId(),
                            'image_url' => $imageUrl,
                            'name' => $item->getName(),
                            'sku' => $item->getSku(),
                            'description' => substr($product->getDescription(), 0, 8182) ?: '',
                            'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
                            'unit_price' => round($item->getCalculationPrice() * 100),
                            'quantity' => $item->getQty(),
                            'type' => 'physical'
                        ),
                ),
            'currency' => $_quote->getQuoteCurrencyCode(),
            'discounts' => array (),
            'total_amount' => round($_quote->getSubtotal() * 100),
        );

        $result = $this->currentMock->buildCart($_quote, $_items, $_multipage);

        $this->assertEquals($expected, $result);
    }

    /**
     * Unit test for get adjusted shipping amount with no discount
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: none
     */
    public function testGetAdjustedShippingAmountWithNoDiscount()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
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
        $this->testHelper = new Bolt_Boltpay_TestHelper();
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
        $this->testHelper = new Bolt_Boltpay_TestHelper();
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
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountedSubtotal = 50; // Discount on cart: 50%

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotalWithDiscount(25); // Discount on shipping: subtotal * 50% - shipping_amount * 50% = $25

        $expected = 25;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

        $this->assertEquals($expected, $result);
    }

    public function testStoreHasAllCartItems()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = $this->currentMock->storeHasAllCartItems($cart->getQuote());

        $this->assertTrue($result);
    }

    public function testIsResponseError()
    {
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertFalse($result);
    }

    public function testIsResponseErrorWithErrors()
    {
        $this->testBoltResponse->errors = ['some_error_key' => 'some_error_message'];
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }

    public function testIsResponseErrorWithErrorCode()
    {
        $this->testBoltResponse->error_code = 10603;
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
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