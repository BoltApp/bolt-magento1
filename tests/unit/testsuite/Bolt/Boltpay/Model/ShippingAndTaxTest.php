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
     *Test ShippingAddresstoQuote
    */
    public function testApplyShippingAddressToQuote() {

        $quote = Mage::getModel('checkout/cart')->getQuote();
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $shippingAddress = $checkout->getQuote()->getShippingAddress();

        $directory = Mage::getModel('directory/region')->loadByName($shippingAddress->region, $shippingAddress->country_code);
        $region = $directory->getName(); // For region field should be the name not a code.
        $regionId = $directory->getRegionId(); // This is require field for calculation: shipping, shopping price rules and etc.

        $shipping_email = '';
        if (isset($shippingAddress->email) && !empty($shippingAddress->email)){
            $shipping_email = $shippingAddress->email;
        }else if (isset($shippingAddress->email_address)){
            $shipping_email = $shippingAddress->email_address;
        }

        $shipping_street = '';
        if (isset($shippingAddress->street_address1) && !empty($shippingAddress->street_address1)){
            $shipping_street = $shippingAddress->street_address1;
        }
        if (isset($shippingAddress->street_address2) && !empty($shippingAddress->street_address2)){
            $shipping_street .= $shippingAddress->street_address2;
        }

        $shipping_telephone = '';
        if (isset($shippingAddress->phone) && !empty($shippingAddress->phone)){
            $shipping_telephone = $shippingAddress->phone;
        }else if (isset($shippingAddress->phone_number) && !empty($shippingAddress->phone_number)){
            $shipping_telephone = $shippingAddress->phone_number;
        }

        $shipping_company = '';
        if (isset($shippingAddress->company) && !empty($shippingAddress->company)){
            $shipping_company = $shippingAddress->company;
        }

        $addressData = array(
            'email' => @$shipping_email,
            'firstname' => $shippingAddress->first_name,
            'lastname' => $shippingAddress->last_name,
            'street' => @$shipping_street,
            'company' => @$shipping_company,
            'city' => $shippingAddress->locality,
            'region' => $region,
            'region_id' => $regionId,
            'postcode' => $shippingAddress->postal_code,
            'country_id' => $shippingAddress->country_code,
            'telephone' => @$shipping_telephone
        );

        if ($quote->getCustomerId()) {
            $customerSession = Mage::getSingleton('customer/session');
            $customerSession->setCustomerGroupId($quote->getCustomerGroupId());
            $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
            $address = $customer->getPrimaryShippingAddress();

            if (!$address) {
                $address = Mage::getModel('customer/address');

                $address->setCustomerId($customer->getId())
                    ->setCustomer($customer)
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1')
                    ->save();


                $address->addData($addressData);
                $address->save();

                $customer->addAddress($address)
                    ->setDefaultShipping($address->getId())
                    ->save();
            }
        }

        // https://github.com/BoltApp/bolt-magento1/pull/255
        if (strpos(Mage::getVersion(), '1.7') !== 0){
            $quote->removeAllAddresses();
            $quote->save();
        }

        $quote->getShippingAddress()->addData($addressData)->save();

        $billingAddress = $quote->getBillingAddress();

        $quote->getBillingAddress()->addData(
            array(
                'email' => $billingAddress->getEmail() ?: @$shipping_email,
                'firstname' => $billingAddress->getFirstname() ?: $shippingAddress->first_name,
                'lastname' => $billingAddress->getLastname() ?: $shippingAddress->last_name,
                'street' => implode("\n", $billingAddress->getStreet()) ?: @$shipping_street,
                'company' => $billingAddress->getCompany() ?: @$shipping_company,
                'city' => $billingAddress->getCity() ?: $shippingAddress->locality,
                'region' => $billingAddress->getRegion() ?: $region,
                'region_id' => $billingAddress->getRegionId() ?: $regionId,
                'postcode' => $billingAddress->getPostcode() ?: $shippingAddress->postal_code,
                'country_id' => $billingAddress->getCountryId() ?: $shippingAddress->country_code,
                'telephone' => $billingAddress->getTelephone() ?: @$shipping_telephone
            )
        )->save();

        $expected = array(
                'firstname' => 'Luke',
                'lastname' => 'Skywalker',
                'street' => 'Sample Street 10',
                'city' => 'Los Angeles',
                'postcode' => '90014',
                'telephone' => '+1 867 345 123 5681',
                'country_id' => 'US',
                'region_id' => 12
            );

        $this->assertEquals($expected, $addressData);
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