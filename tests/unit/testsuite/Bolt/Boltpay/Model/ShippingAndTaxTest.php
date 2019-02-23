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
     * Test ShippingAddresstoQuote standard with all four address lines
     * Non-sequential, associative array used as input
     */
    public function testApplyShippingAddressToQuoteFourLineAddress() {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object) array(
            'email' => 'test@bolt.com',
            'first_name' => 'Luke',
            'last_name' => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address3' => 'Apt 123',
            'street_address2' => '4th Floor',
            'street_address4' => 'Attention: Jedi Knights',
            'locality' => 'Los Angeles',
            'postal_code' => '90014',
            'phone' => '+1 867 345 123 5681',
            'country_code' => 'US',
            'company' => 'Bolt',
            'region' => 'California'
        );
        $result = $this->currentMock->applyShippingAddressToQuote($quote, $shippingAddress);

        $expected = array(
            'email' => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . '4th Floor' . "\n" . 'Apt 123' . "\n" . 'Attention: Jedi Knights',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company' => 'Bolt',
            'region_id'  => '12',
            'region' => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Test ShippingAddresstoQuote standard two line address with empty
     * address line 3 and address line 4 values
     */
    public function testApplyShippingAddressToQuote() {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object) array(
            'email' => 'test@bolt.com',
            'first_name' => 'Luke',
            'last_name' => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address2' => 'Apt 123',
            'street_address3' => '',
            'street_address4' => null,
            'locality' => 'Los Angeles',
            'postal_code' => '90014',
            'phone' => '+1 867 345 123 5681',
            'country_code' => 'US',
            'company' => 'Bolt',
            'region' => 'California'
        );
        $result = $this->currentMock->applyShippingAddressToQuote($quote, $shippingAddress);

        $expected = array(
            'email' => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . 'Apt 123',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company' => 'Bolt',
            'region_id'  => '12',
            'region' => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    public function testApplyShippingAddressToQuote_withsomefieldmissing() {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object) array(
            'email' => 'test@bolt.com',
            'first_name' => 'Luke',
            'last_name' => 'Skywalker',
            'postal_code' => '90014',
            'phone' => '+1 867 345 123 5681',
            'country_code' => 'US',
            'region' => 'California'
        );
        $result = $this->currentMock->applyShippingAddressToQuote($quote, $shippingAddress);

        $expected = array(
            'email' => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => "",
            'city'       => null,
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => null,
            'region_id'  => '12',
            'region' => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Test ShippingAddresstoQuote with a skipped internal address field
     */
    public function testApplyShippingAddressToQuoteWithSkippedAddressFields() {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object) array(
            'email' => 'test@bolt.com',
            'first_name' => 'Luke',
            'last_name' => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address2' => 'Apt 123',
            'street_address4' => 'Skipped Address 3',
            'locality' => 'Los Angeles',
            'postal_code' => '90014',
            'phone' => '+1 867 345 123 5681',
            'country_code' => 'US',
            'company' => 'Bolt',
            'region' => 'California'
        );
        $result = $this->currentMock->applyShippingAddressToQuote($quote, $shippingAddress);

        $expected = array(
            'email' => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . 'Apt 123' . "\n\n". 'Skipped Address 3',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company' => 'Bolt',
            'region_id'  => '12',
            'region' => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    public function testApplyShippingAddressToQuote_throwsForBadInput() {
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object) array(
            'email' => 'test@bolt.com'
        );
        try {
            $this->currentMock->applyShippingAddressToQuote($quote, $shippingAddress);
        } catch (Exception $e) {
            $this->assertEquals('Address must contain postal_code and country_code.', $e->getMessage());
            return;
        }

        $this->fail('applyShippingAddressToQuote did not throw');
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

    /**
     * Test if P.O. boxes in address are successfully detected in various formats
     */
    public function testPOBoxMatchesCases()
    {
        $address1 = 'P.O. Box 66';
        $address2 = 'Post Box 123';
        $address3 = 'Post Office Box 456';
        $address4 = 'PO Box 456';
        $address5 = 'PO Box #456';
        $address6 = 'Post Office Box #456';
        $address7 = 'PO.BOX';

        $check1 = $this->currentMock->doesAddressContainPOBox($address1);
        $check2 = $this->currentMock->doesAddressContainPOBox($address2);
        $check3 = $this->currentMock->doesAddressContainPOBox($address3);
        $check4 = $this->currentMock->doesAddressContainPOBox($address4);
        $check5 = $this->currentMock->doesAddressContainPOBox($address5);
        $check6 = $this->currentMock->doesAddressContainPOBox($address6);
        $check7 = $this->currentMock->doesAddressContainPOBox($address7);
        $additionalCheck1 = $this->currentMock->doesAddressContainPOBox($address1, $address2);
        $additionalCheck2 = $this->currentMock->doesAddressContainPOBox($address1, $address3);
        $additionalCheck3 = $this->currentMock->doesAddressContainPOBox($address2, $address3);

        $this->assertTrue($check1);
        $this->assertTrue($check2);
        $this->assertTrue($check3);
        $this->assertTrue($check4);
        $this->assertTrue($check5);
        $this->assertTrue($check6);
        $this->assertTrue($check7);
        $this->assertTrue($additionalCheck1);
        $this->assertTrue($additionalCheck2);
        $this->assertTrue($additionalCheck3);
    }

    /**
     * Test that non-P.O. Box addresses do not trigger a false positive
     */
    public function testPOBoxDoesNotMatchCases()
    {
        $address1 = 'Post street';
        $address2 = '2 Box';
        $address3 = '425 Sesame St';
        $check1 = $this->currentMock->doesAddressContainPOBox($address1);
        $check2 = $this->currentMock->doesAddressContainPOBox($address2);
        $check3 = $this->currentMock->doesAddressContainPOBox($address3);
        $additionalCheck1 = $this->currentMock->doesAddressContainPOBox($address1, $address2);
        $additionalCheck2 = $this->currentMock->doesAddressContainPOBox($address2, $address3);

        $this->assertNotTrue($check1);
        $this->assertNotTrue($check2);
        $this->assertNotTrue($check3);
        $this->assertNotTrue($additionalCheck1);
        $this->assertNotTrue($additionalCheck2);
    }
}
