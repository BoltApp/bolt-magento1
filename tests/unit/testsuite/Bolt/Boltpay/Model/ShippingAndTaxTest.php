<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_ShippingAndTax
 */
class Bolt_Boltpay_Model_ShippingAndTaxTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of tested class */
    protected $testClassName = 'Bolt_Boltpay_Model_ShippingAndTax';

    /** @var int|null Dummy product id */
    private static $productId = null;

    /** @var Bolt_Boltpay_Model_ShippingAndTax */
    private $currentMock;

    /** @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of Bolt helper */
    private $boltHelperMock;

    /** @var MockObject|Mage_Sales_Model_Quote_Address Mocked instance of Magento quote address used as billing address */
    private $quoteBillingAddressMock;

    /** @var MockObject|Mage_Sales_Model_Quote_Address Mocked instance of Magento quote address used as shipping address */
    private $quoteShippingAddressMock;

    /** @var MockObject|Mage_Sales_Model_Quote Mocked instance of Magento quote */
    private $quoteMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Model_Store_Exception if store is not defined
     */
    public function setUp()
    {
        Mage::app()->getStore()->resetConfig();
        $this->currentMock = Mage::getModel('boltpay/shippingAndTax');
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('collectTotals', 'notifyException'))->getMock();
        $this->quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')->getMock();
        $this->quoteBillingAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
            ->setMethods(array('getTaxAmount'))->getMock();
        $this->quoteMock->method('getBillingAddress')->willReturn($this->quoteBillingAddressMock);
        $this->quoteShippingAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
            ->setMethods(
                array(
                    'setCollectShippingRates',
                    'collectShippingRates',
                    'save',
                    'getTaxAmount',
                    'isObjectNew',
                    'getData',
                    'setShippingMethod',
                    'setCollectShipppingRates',
                    'setData',
                    'getAllItems'
                )
            )->getMock();
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteShippingAddressMock);
    }

    /**
     * Restore original stubbed values and truncate session cart
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        TestHelper::restoreOriginals();
        Mage::getSingleton('checkout/cart')->truncate()->save();
    }

    /**
     * Generate dummy product for testing purposes and initialize benchmark profiler
     */
    public static function setUpBeforeClass()
    {
        Mage::getModel('boltpay/observer')->initializeBenchmarkProfiler();

        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'));
    }

    /**
     * Delete dummy product after the test
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @test
     * ShippingAddresstoQuote standard with all four address lines
     * Non-sequential, associative array used as input
     *
     * @covers ::applyBoltAddressData
     */
    public function applyBoltAddressData_whenShippingAddressContainsFourLineAddress_appliesAddressDataToQuote()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address3' => 'Apt 123',
            'street_address2' => '4th Floor',
            'street_address4' => 'Attention: Jedi Knights',
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $result = $this->currentMock->applyBoltAddressData($quote, $shippingAddress);

        $expected = array(
            'email'      => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . '4th Floor' . "\n" . 'Apt 123' . "\n" . 'Attention: Jedi Knights',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => 'Bolt',
            'region_id'  => '12',
            'region'     => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that applyBoltAddressData when provided with only first two street lines applies them to addresses
     *
     * @covers ::applyBoltAddressData
     *
     * @throws Exception if unable to add product to cart
     */
    public function applyBoltAddressData_withOnlyFirstTwoStreetLines_appliesAddressDataWitTheTwoStreetLines()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address2' => 'Apt 123',
            'street_address3' => '',
            'street_address4' => null,
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $result = $this->currentMock->applyBoltAddressData($quote, $shippingAddress);

        $expected = array(
            'email'      => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . 'Apt 123',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => 'Bolt',
            'region_id'  => '12',
            'region'     => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that applyBoltAddressData uses available address data if it is incomplete
     *
     * @covers ::applyBoltAddressData
     *
     * @throws Exception if unable to add product to cart
     */
    public function applyBoltAddressData_withSomeFieldsMissing_appliesAddressDataWithNonMissingFields()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email'        => 'test@bolt.com',
            'first_name'   => 'Luke',
            'last_name'    => 'Skywalker',
            'postal_code'  => '90014',
            'phone'        => '+1 867 345 123 5681',
            'country_code' => 'US',
            'region'       => 'California'
        );
        $result = $this->currentMock->applyBoltAddressData($quote, $shippingAddress);

        $expected = array(
            'email'      => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => "",
            'city'       => null,
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => null,
            'region_id'  => '12',
            'region'     => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that applyBoltAddressData will use available street fields when a street field is skipped
     *
     * @covers ::applyBoltAddressData
     *
     * @throws Exception if unable to add products to cart
     */
    public function applyBoltAddressData_withSkippedStreetField_appliesAddressDataWithAvailableFields()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address2' => 'Apt 123',
            'street_address4' => 'Skipped Address 3',
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $result = $this->currentMock->applyBoltAddressData($quote, $shippingAddress);

        $expected = array(
            'email'      => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . 'Apt 123' . "\n\n" . 'Skipped Address 3',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => 'Bolt',
            'region_id'  => '12',
            'region'     => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * applyBoltAddressData with region as region_code
     *
     * @covers ::applyBoltAddressData
     *
     * @throws Exception if unable to add products to cart
     */
    public function applyBoltAddressData_withRegionCodeForRegion_regionConvertedFromCodeToIdAndLabel()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address2' => 'Apt 123',
            'street_address3' => '',
            'street_address4' => null,
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'CA'
        );
        $result = $this->currentMock->applyBoltAddressData($quote, $shippingAddress);

        $expected = array(
            'email'      => 'test@bolt.com',
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10' . "\n" . 'Apt 123',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'company'    => 'Bolt',
            'region_id'  => '12',
            'region'     => 'California'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that applyBoltAddressData throws an exception if missing postal_code or country_code
     *
     * @covers ::applyBoltAddressData
     *
     * @expectedException Exception
     * @expectedExceptionMessage Address must contain postal_code and country_code.
     *
     * @throws Exception if unable to add product to quote
     */
    public function applyBoltAddressData_ifAddressDataIsMissingPostalAndCountryCodes_throwsException()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $shippingAddress = (object)array(
            'email' => 'test@bolt.com'
        );

        $this->currentMock->applyBoltAddressData($quote, $shippingAddress);
    }

    /**
     * @test
     * Get adjusted shipping amount with no discount
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: none
     *
     * @covers ::getAdjustedShippingAmount
     *
     * @throws Exception if unable to add product to cart
     */
    public function getAdjustedShippingAmount_withNoDiscount_returnsAdjustedShippingAmount()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountTotal = 0;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotal(100);
        $quote->setSubtotalWithDiscount(100);

        $expected = 50;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountTotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * Unit test for get adjusted shipping amount with shipping discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on shipping
     *
     * @covers ::getAdjustedShippingAmount
     *
     * @throws Exception if unable to add product to cart
     */
    public function getAdjustedShippingAmount_withShippingDiscount_returnsAdjustedShippingAmount()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountTotal = 0;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotal(100);
        $quote->setSubtotalWithDiscount(75); // Discount on shipping: subtotal - shipping_amount * 50% = $75

        $expected = 25;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountTotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * Get adjusted shipping amount with cart discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on quote
     *
     * @covers ::getAdjustedShippingAmount
     *
     * @throws Exception if unable to add product to cart
     */
    public function getAdjustedShippingAmount_withCartDiscount_returnsAdjustedShippingAmount()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountTotal = 50;

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotal(100);
        $quote->setSubtotalWithDiscount(50); // Discount on quote: subtotal * 50% = $50

        $expected = 50;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountTotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * Get adjusted shipping amount with cart discount (50%) and shipping discount (50%)
     * - subtotal : $100
     * - shipping amount: $50
     * - discount amount: 50% on subtotal and 50% on shipping method
     *
     * @covers ::getAdjustedShippingAmount
     *
     * @throws Exception if unable to add product to cart
     */
    public function getAdjustedShippingAmount_withCartAndShippingDiscount_returnsAdjustedShippingAmount()
    {
        $cart = TestHelper::addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $originalDiscountTotal = 0; // Discount on cart: 50%

        $quote->getShippingAddress()->setShippingAmount(50);
        $quote->setSubtotal(100);
        $quote->setSubtotalWithDiscount(75); // Discount on shipping: subtotal * 50% - shipping_amount * 50% = $25

        $expected = 25;
        $result = $this->currentMock->getAdjustedShippingAmount($originalDiscountTotal, $quote);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that getShippingLabel returns concatenated carrier and method title as shipping label
     *
     * @covers ::getShippingLabel
     */
    public function getShippingLabel_withCompleteAddressRate_returnsShippingLabel()
    {
        /** @var MockObject|Mage_Sales_Model_Quote_Address_Rate $rate */
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $carrierTitle = 'United Parcel Service';
        $rate->method('getCarrierTitle')->willReturn($carrierTitle);
        $methodTitle = '2 Day Shipping';
        $rate->method('getMethodTitle')->willReturn($methodTitle);

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals($carrierTitle . ' - ' . $methodTitle, $label);
    }

    /**
     * @test
     * that getShippingLabel for shipping rates for Shipping Table Rates carrier are returned without carrier title
     *
     * @covers ::getShippingLabel
     */
    public function getShippingLabel_ifShippingMethodIsTableRate_shippingLabelContainsOnlyMethodTitle()
    {
        /** @var MockObject|Mage_Sales_Model_Quote_Address_Rate $rate */
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('Shipping Table Rates');
        $rate->method('getMethodTitle')->willReturn('Free shipping (5 - 7 business days)');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('Free shipping (5 - 7 business days)', $label);
    }

    /**
     * @test
     * that getShippingLabel won't return duplicated carrier title prefix if method title already starts with carrier title
     *
     * @covers ::getShippingLabel
     */
    public function getShippingLabel_ifMethodTitleContainsCarrierTitle_returnedLabelContainsOnlyMethodTitle()
    {
        $rate = $this->getMockBuilder('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getMethodTitle'))
            ->getMock();
        $rate->method('getCarrierTitle')->willReturn('USPS');
        $rate->method('getMethodTitle')->willReturn('USPS Two days');

        $label = $this->currentMock->getShippingLabel($rate);

        $this->assertEquals('USPS Two days', $label);
    }

    /**
     * @test
     * that getShippingLabel returns shipping label without carrier title prefix for UPS as they are already prefixed
     *
     * @covers ::getShippingLabel
     */
    public function getShippingLabel_ifShippingRateIsUPS_returnsShippingLabelWithoutAdditionalPrefix()
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
     * @test
     * that getShippingLabel returns just carrier title if method title is not defined
     *
     * @covers ::getShippingLabel
     */
    public function getShippingLabel_ifShippingMethodDoesNotHaveMethodTitle_returnsOnlyCarrierTitleAsLabel()
    {
        /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
        $rate = Mage::getModel('sales/quote_address_rate', array('carrier_title' => 'FedEx'));
        $this->assertEquals('FedEx', $this->currentMock->getShippingLabel($rate));
    }

    /**
     * @test
     * that P.O. boxes in address are successfully detected in various formats
     *
     * @covers ::doesAddressContainPOBox
     *
     * @dataProvider doesAddressContainPOBox_withVariousAddresses_determinesIfAddressContainsPOBoxProvider
     *
     * @param string $POBoxAddress
     */
    public function doesAddressContainPOBox_withVariousAddresses_determinesIfAddressContainsPOBox($POBoxAddress)
    {
        $this->assertTrue($this->currentMock->doesAddressContainPOBox($POBoxAddress));
        $this->assertTrue($this->currentMock->doesAddressContainPOBox('', $POBoxAddress));
        $this->assertTrue($this->currentMock->doesAddressContainPOBox($POBoxAddress, $POBoxAddress));
    }

    /**
     * Data provider for {@see doesAddressContainPOBox_withVariousAddresses_determinesIfAddressContainsPOBox}
     *
     * @return array containing PO Box address
     */
    public function doesAddressContainPOBox_withVariousAddresses_determinesIfAddressContainsPOBoxProvider()
    {
        return array(
            array("POBoxAddress" => 'P.O. Box 66'),
            array("POBoxAddress" => 'Post Box 123'),
            array("POBoxAddress" => 'Post Office Box 456'),
            array("POBoxAddress" => 'PO Box 456'),
            array("POBoxAddress" => 'PO Box #456'),
            array("POBoxAddress" => 'Post Office Box #456'),
            array("POBoxAddress" => 'PO.BOX'),
        );
    }

    /**
     * @test
     * that non-P.O. Box addresses do not trigger a false positive
     *
     * @covers ::doesAddressContainPOBox
     *
     * @param string $regularAddress
     */
    public function doesAddressContainPOBox_withNonPOBoxAddresses_returnsFalse($regularAddress)
    {
        $this->assertNotTrue($this->currentMock->doesAddressContainPOBox($regularAddress));
        $this->assertNotTrue($this->currentMock->doesAddressContainPOBox('', $regularAddress));
        $this->assertNotTrue($this->currentMock->doesAddressContainPOBox($regularAddress, $regularAddress));
    }

    /**
     * Data provider for {@see doesAddressContainPOBox_withNonPOBoxAddresses_returnsFalse}
     *
     * @return array containing regular addresses
     */
    public function doesAddressContainPOBox_withNonPOBoxAddresses_returnsFalseProvider()
    {
        return array(
            array("regularAddress" => 'Post street'),
            array("regularAddress" => '2 Box'),
            array("regularAddress" => '425 Sesame St'),
        );
    }

    /**
     * @test
     * that isPOBoxAllowed returns config flag for allow_po_box
     *
     * @dataProvider isPOBoxAllowed_withVariousConfigurations_determinesIfPOBoxesAreAllowedProvider
     *
     * @covers ::isPOBoxAllowed
     *
     * @param mixed $allowPOBox config value
     * @param bool  $expectedResult of the method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config
     */
    public function isPOBoxAllowed_withVariousConfigurations_determinesIfPOBoxesAreAllowed($allowPOBox, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/allow_po_box', $allowPOBox);
        $this->assertSame($expectedResult, $this->currentMock->isPOBoxAllowed());
    }

    /**
     * Data provider for @see isPOBoxAllowed_withVariousConfigurations_determinesIfPOBoxesAreAllowed
     *
     * @return array containing config value for allow_po_box and expected result of method call
     */
    public function isPOBoxAllowed_withVariousConfigurations_determinesIfPOBoxesAreAllowedProvider()
    {
        return array(
            'Empty value should return false'   => array('allowPOBox' => '', 'expectedResult' => false),
            'Zero should return false'          => array('allowPOBox' => '0', 'expectedResult' => false),
            'Null should return false'          => array('allowPOBox' => null, 'expectedResult' => false),
            'Boolean false should return false' => array('allowPOBox' => 'false', 'expectedResult' => false),
            'String false should return false'  => array('allowPOBox' => false, 'expectedResult' => false),
            'String true should return true'    => array('allowPOBox' => 'true', 'expectedResult' => true),
            'String one should return true'     => array('allowPOBox' => '1', 'expectedResult' => true),
            'Integer one should return true'    => array('allowPOBox' => 1, 'expectedResult' => true),
        );
    }

    /**
     * @test
     * that applyBoltAddressData sets customer default billing and shipping address from address data that is applied
     * if quote has customer id and customer doesn't already have default shipping and billing
     *
     * @covers ::applyBoltAddressData
     */
    public function applyBoltAddressData_ifCustomerSetOnQuoteAndCustomerDoesNotHaveDefaultAddresses_setsCustomerDefaultBillingAndShippingAddress()
    {
        $customerId = Bolt_Boltpay_CouponHelper::createDummyCustomer(
            array(),
            uniqid('APPLY_BOLT_ADDRESS_TEST_') . '@bolt.com'
        );
        $quote = Mage::getModel('sales/quote');
        $quote->setCustomerId($customerId);
        $shippingAddress = array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address3' => 'Apt 123',
            'street_address2' => '4th Floor',
            'street_address4' => 'Attention: Jedi Knights',
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $this->currentMock->applyBoltAddressData($quote, (object)$shippingAddress);

        $expectedAddressData = array(
            "firstname"  => "Luke",
            "lastname"   => "Skywalker",
            "company"    => "Bolt",
            "city"       => "Los Angeles",
            "region"     => "California",
            "postcode"   => "90014",
            "country_id" => "US",
            "telephone"  => "+1 867 345 123 5681",
            "region_id"  => 12,
            "street"     => "Sample Street 10\n4th Floor\nApt 123\nAttention: Jedi Knights",
        );
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);
        $this->assertNotFalse($customer->getDefaultShippingAddress());
        $this->assertNotFalse($customer->getDefaultBillingAddress());
        $this->assertArraySubset($expectedAddressData, $customer->getDefaultShippingAddress()->getData());
        $this->assertArraySubset($expectedAddressData, $customer->getDefaultBillingAddress()->getData());
        Bolt_Boltpay_CouponHelper::deleteDummyCustomer($customerId);
    }

    /**
     * @test
     * that applyBoltAddressData sets customer default billing and shipping address from address data that is applied
     * if quote has customer id and customer doesn't already have default shipping and billing
     *
     * @covers ::applyBoltAddressData
     *
     * @throws Exception
     */
    public function applyBoltAddressData_whenSettingDefaultAddressThrowsException_logsExceptionAndProceeds()
    {
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $exception = new Exception('Expected exception');
        $customerMock = $this->getClassPrototype('Mage_Customer_Model_Customer')->getMock();
        $customerMock->method('load')->willReturnSelf();
        $customerMock->method('addAddress')->willThrowException($exception);
        TestHelper::stubModel('customer/customer', $customerMock);
        $customerAddressMock = $this->getClassPrototype('Mage_Customer_Model_Address')->getMock();
        $customerAddressMock->method('setCustomerId')->willThrowException($exception);
        TestHelper::stubModel('customer/address', $customerAddressMock);

        $quote = Mage::getModel('sales/quote');
        $quote->setCustomerId(1);
        $shippingAddress = array(
            'email'           => 'test@bolt.com',
            'first_name'      => 'Luke',
            'last_name'       => 'Skywalker',
            'street_address1' => 'Sample Street 10',
            'street_address3' => 'Apt 123',
            'street_address2' => '4th Floor',
            'street_address4' => 'Attention: Jedi Knights',
            'locality'        => 'Los Angeles',
            'postal_code'     => '90014',
            'phone'           => '+1 867 345 123 5681',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $expectedAddressData = array(
            'email'      => 'test@bolt.com',
            "firstname"  => "Luke",
            "lastname"   => "Skywalker",
            "street"     => "Sample Street 10\n4th Floor\nApt 123\nAttention: Jedi Knights",
            "company"    => "Bolt",
            "city"       => "Los Angeles",
            "region"     => "California",
            "region_id"  => "12",
            "postcode"   => "90014",
            "country_id" => "US",
            "telephone"  => "+1 867 345 123 5681",
        );

        $this->boltHelperMock->expects($this->exactly(4))->method('notifyException')
            ->with(
                $exception,
                array('bolt_address_data' => json_encode($expectedAddressData)),
                'warning'
            );

        $this->assertEquals(
            $expectedAddressData,
            $this->currentMock->applyBoltAddressData($quote, (object)$shippingAddress)
        );

    }

    /**
     * @test
     * that getShippingAndTaxEstimate returns No Shipping Required as the only shipping option for virtual quotes
     *
     * @covers ::getShippingAndTaxEstimate
     *
     * @throws Exception if unable to stub helper or model
     */
    public function getShippingAndTaxEstimate_ifQuoteIsVirtual_returnsNoShippingRequired()
    {
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);

        /** @var MockObject|Mage_Sales_Model_Quote $quoteMock */
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')->getMock();
        $quoteMock->method('getBillingAddress')->willReturn($this->quoteBillingAddressMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);

        TestHelper::stubModel('sales/quote', $quoteMock);

        $this->quoteBillingAddressMock->expects($this->once())->method('getTaxAmount')->willReturn(145);

        $this->assertEquals(
            array(
                'shipping_options' => array(
                    array(
                        "service"    => Mage::helper('core')->__('No Shipping Required'),
                        "reference"  => 'noshipping',
                        "cost"       => 0,
                        "tax_amount" => 14500
                    )
                ),
                'tax_result'       => array(
                    'amount' => 0
                )
            ),
            $this->currentMock->getShippingAndTaxEstimate($quoteMock)
        );
    }

    /**
     * @test
     * that getShippingAndTaxEstimate returns expected shipping and tax estimate
     *
     * @covers ::getShippingAndTaxEstimate
     *
     * @throws ReflectionException if unable to stub model
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws Exception if test class name is not defined
     */
    public function getShippingAndTaxEstimate_withMultipleShippingMethods_returnsExpectedShippingAndTaxEstimate()
    {
        $boltOrder = new stdClass();
        $boltOrder->cart->discounts = array(
            (object)array('amount' => 123),
            (object)array('amount' => 456),
            (object)array('amount' => 654),
            (object)array('amount' => 987),
        );

        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('applyShippingRate', 'getSortedShippingRates', 'getAdjustedShippingAmount'))->getMock();
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);

        /** @var MockObject|Mage_Sales_Model_Quote $quoteMock */
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')->getMock();
        $quoteMock->method('getBillingAddress')->willReturn($this->quoteBillingAddressMock);
        $quoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $quoteMock->method('getShippingAddress')->willReturn($this->quoteShippingAddressMock);

        TestHelper::stubModel('sales/quote', $quoteMock);

        $shippingRates = array(
            'Shipping rate with error message should be skipped' => Mage::getModel(
                'sales/quote_address_rate',
                array('error_message' => 'Error message', 'carrier_title' => 'Rate with error',)
            ),
            'Shipping rate without code should be skipped'       => Mage::getModel(
                'sales/quote_address_rate',
                array('carrier_title' => 'Second', 'cost' => 200, 'tax_amount' => 40)
            ),
            'Valid shipping rate'                                => Mage::getModel(
                'sales/quote_address_rate',
                array('carrier_title' => 'Second', 'code' => 'second_shipment_code', 'cost' => 100, 'tax_amount' => 40)
            ),
        );

        $currentMock->expects($this->exactly(3))->method('applyShippingRate')
            ->withConsecutive(
                array($quoteMock, null, false),
                array($quoteMock, null, false),
                array($quoteMock, 'second_shipment_code', false)
            );

        $this->quoteShippingAddressMock->expects($this->once())->method('setCollectShippingRates')->with(
            true
        )->willReturnSelf();
        $this->quoteShippingAddressMock->expects($this->once())->method('collectShippingRates')->with()->willReturnSelf(
        );
        $this->quoteShippingAddressMock->expects($this->once())->method('save')->with()->willReturnSelf();
        $this->quoteShippingAddressMock->expects($this->once())->method('getTaxAmount')->willReturn(456.78);

        $currentMock->expects($this->atLeastOnce())->method('getSortedShippingRates')->with(
            $this->quoteShippingAddressMock
        )
            ->willReturn($shippingRates);

        $currentMock->method('getAdjustedShippingAmount')->with(22.2, $quoteMock, $boltOrder)->willReturn(2345.67);

        $this->assertEquals(
            array(
                "shipping_options" => array(
                    array(
                        "service"    => "Second",
                        "reference"  => "second_shipment_code",
                        "cost"       => 234567,
                        "tax_amount" => 45678
                    )
                ),
                "tax_result"       => array("amount" => 0)
            ),
            $currentMock->getShippingAndTaxEstimate($quoteMock, $boltOrder)
        );
    }

    /**
     * @test
     * that applyShippingRate sets shipping method, collects totals, and restores item discount data
     *
     * @covers ::applyShippingRate
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function applyShippingRate_always_appliesShippingRateToQuote()
    {
        $addressId = 1;
        $shouldRecalculateShipping = true;
        $shippingRateCode = 'flatrate_flatrate';

        TestHelper::stubHelper('boltpay', $this->boltHelperMock);

        $itemMockBuilder = $this->getClassPrototype('Mage_Sales_Model_Quote_Item')
            ->setMethods(array('setData', 'getOrigData'));
        $items = array();
        for ($i = 0; $i < 10; $i++) {
            $discount = mt_rand();
            $itemMock = $itemMockBuilder->getMock();
            $itemMock->expects($this->exactly(2))->method('getOrigData')
                ->withConsecutive(array('discount_amount'), array('base_discount_amount'))->willReturn($discount);
            $itemMock->expects($this->exactly(2))->method('setData')
                ->withConsecutive(array('discount_amount', $discount), array('base_discount_amount', $discount));
            $items[] = $itemMock;
        }

        $this->quoteMock->expects($this->once())->method('getAllItems')->willReturn($items);

        $this->quoteShippingAddressMock->expects($this->once())->method('isObjectNew')->with(true);
        $this->quoteShippingAddressMock->expects($this->exactly(2))->method('getData')->with('address_id')
            ->willReturnOnConsecutiveCalls($addressId, null);
        $this->quoteShippingAddressMock->expects($this->once())->method('setShippingMethod')->with($shippingRateCode)
            ->willReturnSelf();
        $this->quoteShippingAddressMock->expects($this->once())->method('setCollectShippingRates')
            ->with($shouldRecalculateShipping);

        $this->boltHelperMock->expects($this->once())->method('collectTotals')->with($this->quoteMock, true);
        $this->quoteShippingAddressMock->expects($this->once())->method('setData')->with('address_id', $addressId);

        $this->currentMock->applyShippingRate($this->quoteMock, $shippingRateCode);
    }

    /**
     * @test
     * that getSortedShippingRates returns result of {@see Mage_Sales_Model_Quote_Address::getGroupedAllShippingRates()}
     * with the result flattened into one dimensional array of rates instead of being grouped by carrier
     *
     * @covers ::getSortedShippingRates
     *
     * @throws ReflectionException if getSortedShippingRates method doesn't exist
     */
    public function getSortedShippingRates_always_returnsFlattenedArrayOfShippingRates()
    {
        $shippingRates = array(
            'flatrate' => array(
                Mage::getModel('sales/quote_address_rate', array('code' => 'flatrate_flatrate'))
            ),
            'FEDEX'    => array(
                Mage::getModel('sales/quote_address_rate', array('code' => 'FEDEX_1_DAY_FREIGHT')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'FEDEX_2_DAY')),
            ),
            'USPS'     => array(
                Mage::getModel('sales/quote_address_rate', array('code' => 'USPS_1')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'USPS_2')),
            ),
        );

        $addressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')->getMock();
        $addressMock->expects($this->once())->method('getGroupedAllShippingRates')->willReturn($shippingRates);

        $this->assertEquals(
            array(
                Mage::getModel('sales/quote_address_rate', array('code' => 'flatrate_flatrate')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'FEDEX_1_DAY_FREIGHT')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'FEDEX_2_DAY')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'USPS_1')),
                Mage::getModel('sales/quote_address_rate', array('code' => 'USPS_2')),
            ),
            TestHelper::callNonPublicFunction($this->currentMock, 'getSortedShippingRates', array($addressMock))
        );
    }
}
