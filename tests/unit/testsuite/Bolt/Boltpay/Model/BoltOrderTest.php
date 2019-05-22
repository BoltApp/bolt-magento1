<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Model_BoltOrderTest
 */
class Bolt_Boltpay_Model_BoltOrderTest extends PHPUnit_Framework_TestCase
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
     * @var Bolt_Boltpay_Model_BoltOrder
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->currentMock = Mage::getModel('boltpay/boltOrder');
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
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1');
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
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $_quote = $cart->getQuote();
        $_quote->reserveOrderId();
        $_items = $_quote->getAllVisibleItems();
        $_isMultipage = true;
        $item = $_items[0];
        $product = $item->getProduct();

        /** @var Bolt_Boltpay_Helper_Data $helper */
        $helper = Mage::helper('boltpay');
        $imageUrl = $helper->getItemImageUrl($item);

        $helper->collectTotals($_quote)->save();

        $expected = array (
            'order_reference' => $_quote->getParentQuoteId(),
            'display_id' => $_quote->getReservedOrderId()."|".$_quote->getId(),
            'items' =>
                array (
                    0 =>
                        array (
                            'reference' => $item->getId(),
                            'image_url' => (string) $imageUrl,
                            'name' => $item->getName(),
                            'sku' => $item->getSku(),
                            'description' => substr($product->getDescription(), 0, 8182) ?: '',
                            'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
                            'unit_price' => round($item->getCalculationPrice() * 100),
                            'quantity' => $item->getQty(),
                            'type' => 'physical',
                            'properties' => array ()
                        ),
                ),
            'currency' => $_quote->getQuoteCurrencyCode(),
            'discounts' => array (),
            'total_amount' => round($_quote->getSubtotal() * 100),
        );

        $result = $this->currentMock->buildCart($_quote, $_items, $_isMultipage);

        $this->assertEquals($expected, $result);
    }


    /**
     * Test that complete address data is not overwritten by correction
     */
    public function testCorrectBillingAddressWithNoCorrectionNeeded() {

        $mockQuote = $this->testHelper->getCheckoutQuote();
        $mockQuote->removeAllAddresses();

        $billingAddressData = array(
            'email' => 'hero@general_mills.com',
            'firstname' => 'Under',
            'lastname' => 'Dog',
            'street' => '15th Phone Booth',
            'company' => 'ShoeShine Inc.',
            'city' => 'Unnamed City',
            'region' => 'Unnamed Region',
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-555-5555',
            'address_type' => 'billing'
        );

        $shippingAddressData = array(
            'email' => 'hero@general_mills.com',
            'firstname' => 'Polly',
            'lastname' => 'Purebred',
            'street' => '4 Ever In Distress',
            'company' => 'TV Studio',
            'city' => 'A Second Unnamed City',
            'region' => 'A Second Unnamed Region',
            'postcode' => '54321',
            'country_id' => 'US',
            'telephone' => '555-123-5555'
        );

        $billingAddress = $mockQuote->getBillingAddress()
            ->addData($billingAddressData);
        $billingAddressData['quote_id'] = $mockQuote->getId();
        $billingAddressData['address_id'] = $billingAddress->getId();

        $shippingAddress = $mockQuote->getShippingAddress()
            ->addData($shippingAddressData);

        $this->assertFalse($this->currentMock->correctBillingAddress($billingAddress, $shippingAddress));

        $result = $billingAddress->getData();
        unset($result['customer_id']);
        unset($result['created_at']);
        unset($result['updated_at']);

        $this->assertEquals($billingAddressData, $result);
    }

    /**
     * Test that if an imperative piece of data is missing, like the street
     * the address is replaced by shipping.
     */
    public function testCorrectBillingAddressWithMissingStreet() {

        $mockQuote = $this->testHelper->getCheckoutQuote();
        $mockQuote->removeAllAddresses();

        $billingAddressData = array(
            'email' => 'hero@general_mills.com',
            'firstname' => 'Under',
            'lastname' => 'Dog',
            'company' => 'ShoeShine Inc.',
            'city' => 'Incomplete Address City',
            'region' => 'Incomplete Address Region',
            'postcode' => '54321-Incomplete',
            'country_id' => 'US',
            'telephone' => '555-555-5555'
        );

        $shippingAddressData = array(
            'email' => 'reporter@general_mills.com',
            'firstname' => 'Polly',
            'lastname' => 'Purebred',
            'street' => '4 Ever In Distress',
            'company' => 'TV Studio',
            'city' => 'An Unnamed City',
            'region' => 'An Unnamed Region',
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-123-5555'
        );

        $expected = array(
            'email' => 'hero@general_mills.com',
            'firstname' => 'Under',
            'lastname' => 'Dog',
            'street' => '4 Ever In Distress',
            'company' => 'ShoeShine Inc.',
            'city' => 'An Unnamed City',
            'region' => 'An Unnamed Region',
            'region_id' => null,
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-555-5555',
            'address_type' => 'billing'
        );

        $billingAddress = $mockQuote->getBillingAddress()
            ->addData($billingAddressData);
        $expected['quote_id'] = $mockQuote->getId();
        $expected['address_id'] = $billingAddress->getId();

        $shippingAddress = $mockQuote->getShippingAddress()
            ->addData($shippingAddressData);

        $this->assertTrue($this->currentMock->correctBillingAddress($billingAddress, $shippingAddress, false));

        $result = $billingAddress->getData();
        unset($result['customer_id']);
        unset($result['created_at']);
        unset($result['updated_at']);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test for name update from shipping when missing from billing
     */
    public function testCorrectBillingAddressWithNoName() {

        $mockQuote = $this->testHelper->getCheckoutQuote();
        $mockQuote->removeAllAddresses();

        $billingAddressData = array(
            'email' => 'hero@general_mills.com',
            'street' => '15th Phone Booth',
            'company' => 'ShoeShine Inc.',
            'city' => 'Unnamed City',
            'region' => 'Unnamed Region',
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-555-5555',
            'address_type' => 'billing'
        );

        $shippingAddressData = array(
            'email' => 'hero@general_mills.com',
            'firstname' => 'Polly',
            'lastname' => 'Purebred',
            'street' => '4 Ever In Distress',
            'company' => 'TV Studio',
            'city' => 'A Second Unnamed City',
            'region' => 'A Second Unnamed Region',
            'postcode' => '54321',
            'country_id' => 'US',
            'telephone' => '555-123-5555'
        );

        $billingAddress = $mockQuote->getBillingAddress()
            ->addData($billingAddressData);
        $billingAddressData['quote_id'] = $mockQuote->getId();
        $billingAddressData['address_id'] = $billingAddress->getId();

        $shippingAddress = $mockQuote->getShippingAddress()
            ->addData($shippingAddressData);

        $this->assertTrue($this->currentMock->correctBillingAddress($billingAddress, $shippingAddress, false));

        $result = $billingAddress->getData();
        unset($result['customer_id']);
        unset($result['created_at']);
        unset($result['updated_at']);

        $billingAddressData['firstname'] = 'Polly';
        $billingAddressData['lastname'] = 'Purebred';
        $billingAddressData['prefix'] = $billingAddressData['middlename'] = $billingAddressData['suffix'] = null;
        $this->assertEquals($billingAddressData, $result);
    }

    /**
     * Test that if an imperative piece of data is missing, like the street
     * the address is replaced by shipping.
     */
    public function testCorrectBillingAddressWithNoBillingAddress() {

        $mockQuote = $this->testHelper->getCheckoutQuote();
        $mockQuote->removeAllAddresses();

        $shippingAddressData = array(
            'email' => 'reporter@general_mills.com',
            'firstname' => 'Polly',
            'lastname' => 'Purebred',
            'company' => 'TV Studio',
            'street' => '4 Ever In Distress',
            'city' => 'An Unnamed City',
            'region' => 'An Unnamed Region',
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-123-5555'
        );

        $expected = array(
            'firstname' => 'Polly',
            'lastname' => 'Purebred',
            'company' => 'TV Studio',
            'street' => '4 Ever In Distress',
            'city' => 'An Unnamed City',
            'region' => 'An Unnamed Region',
            'region_id' => null,
            'postcode' => '12345',
            'country_id' => 'US',
            'telephone' => '555-123-5555',
            'address_type' => 'billing',
            'prefix' => null,
            'middlename' => null,
            'suffix' => null
        );

        $billingAddress = $mockQuote->getBillingAddress();
        $expected['quote_id'] = $mockQuote->getId();
        $expected['address_id'] = $billingAddress->getId();

        $shippingAddress = $mockQuote->getShippingAddress()
            ->addData($shippingAddressData);

        $this->assertTrue($this->currentMock->correctBillingAddress($billingAddress, $shippingAddress, false));

        $result = $billingAddress->getData();
        unset($result['customer_id']);
        unset($result['created_at']);
        unset($result['updated_at']);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * @group Discount
     * @dataProvider addDiscountsCases
     */
    public function addDiscounts($data = [])
    {
        
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $totals = $quote->getTotals();
        
        $class = new \ReflectionClass($this->currentMock);
        $method = $class->getMethod('addDiscounts');
        $method->setAccessible(true);
        $totalDiscount = $method->invokeArgs($this->currentMock, [$totals, []]);
        $this->assertEquals($data['expect'], $totalDiscount);
        $this->assertTrue(true);
    }

    public function addDiscountsCases()
    {
        return [
            [
                'data' => [
                    'expect' => 0
                ]
            ]
        ];
    }

    /**
     * @test
     * @group Tax
     * @dataProvider getTaxForAdminCases
     */
    public function getTaxForAdmin($data)
    {
        $class = new \ReflectionClass($this->currentMock);
        $method = $class->getMethod('getTaxForAdmin');
        $method->setAccessible(true);
        $tax = $method->invoke($this->currentMock, $data['taxTotal']);
        $this->assertEquals($data['expect'], $tax);
    }

    public function getTaxForAdminCases()
    {
        $cases = [];
        $testHelper = new Bolt_Boltpay_TestHelper();
        $product1 = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TAX_TEST_1', ['price' => 50]);
        $cart = $testHelper->addProduct($product1, 1);
        $quote = Mage::getModel('checkout/session')->getQuote();
        Mage::helper('boltpay')->collectTotals($quote)->save(); 
        $totals1 = $cart->getQuote()->getTotals();
        $cases[] = [
            'data' => [
                'taxTotal' => $totals1['tax'],
                'expect' => 413
            ]
        ];
        Bolt_Boltpay_ProductProvider::deleteDummyProduct($product1);
        // @todo fix this case
//         $product2 = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TAX_TEST_2', ['price' => 100]);
//         $cart = $testHelper->addProduct($product2, 2);
//         $quote = Mage::getModel('checkout/session')->getQuote();
//         Mage::helper('boltpay')->collectTotals($quote)->save(); 
//         $totals2 = $cart->getQuote()->getTotals();
//         $cases[] = [
//             'data' => [
//                 'taxTotal' => $totals2['tax'],
//                 'expect' => 1238
//             ]
//         ];
//         Bolt_Boltpay_ProductProvider::deleteDummyProduct($product2);
        return $cases;
    }
}