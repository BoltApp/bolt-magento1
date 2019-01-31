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
        $_items = $_quote->getAllItems();
        $_multipage = true;
        $item = $_items[0];
        $product = $item->getProduct();

        /** @var Bolt_Boltpay_Helper_Data $helper */
        $helper = Mage::helper('boltpay');
        $imageUrl = $helper->getItemImageUrl($item);

        $expected = array (
            'order_reference' => $_quote->getParentQuoteId(),
            'display_id' => $_quote->getReservedOrderId()."|".$_quote->getId(),
            'items' =>
                array (
                    0 =>
                        array (
                            'reference' => $_quote->getId(),
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

        $result = $this->currentMock->buildCart($_quote, $_items, $_multipage);

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
     * Test that an "address line 1' longer than 50 characters is broken into two
     * address lines
     */
    function testCorrectStreetAddressLongAddress1() {
        $originalStreetAddressData = $streetAddressData = array(
            '1900 F Street NW Room 928, Thurston Hall George Washington University'
        );

        $this->currentMock->correctStreetAddress($streetAddressData);

        $this->assertEquals(count($streetAddressData), 4);
        $this->assertNotEmpty($streetAddressData[0]);
        $this->assertLessThan(50, strlen($streetAddressData[0]));
        $this->assertNotEmpty($streetAddressData[1]);
        $this->assertLessThan(50, strlen($streetAddressData[1]));
        $this->assertEmpty($streetAddressData[2]);
        $this->assertEmpty($streetAddressData[3]);
        $this->assertNotEquals($originalStreetAddressData, $streetAddressData);
        $this->assertEquals(
            trim(implode(' ', $originalStreetAddressData)),
            trim(implode(' ', $streetAddressData))
        );
    }

    /**
     * Test that when an address line is broken and there is an empty address
     * field, the address field is shifted to make room
     */
    function testCorrectStreetAddressByShifting() {
        $originalStreetAddressData = $streetAddressData = array(
            '32 Fox Hut Drive',
            'Attention: Velvet Fox Group, Thurston Hall George Washington University',
            'Shifted To Line 4',
            ''
        );

        $this->currentMock->correctStreetAddress($streetAddressData);

        $this->assertEquals(count($streetAddressData), 4);
        $this->assertNotEmpty($streetAddressData[0]);
        $this->assertLessThan(50, strlen($streetAddressData[0]));
        $this->assertNotEmpty($streetAddressData[1]);
        $this->assertLessThan(50, strlen($streetAddressData[1]));
        $this->assertNotEmpty($streetAddressData[2]);
        $this->assertLessThan(50, strlen($streetAddressData[2]));
        $this->assertNotEmpty($streetAddressData[3]);
        $this->assertLessThan(50, strlen($streetAddressData[3]));
        $this->assertEquals('Shifted To Line 4', $streetAddressData[3]);
        $this->assertNotEquals($originalStreetAddressData, $streetAddressData);
        $this->assertEquals(
            trim(implode(' ', $originalStreetAddressData)),
            trim(implode(' ', $streetAddressData))
        );
    }

    /**
     * Test that when an address line is broken and there is an empty address
     * field that contains newline delimiter, the address field is shifted
     * to make room
     */
    function testCorrectStreetAddressWithNewLineByShifting() {
        $originalStreetAddressData = $streetAddressData = array(
            'Attention: Velvet Fox Group, Thurston Hall George Washington University',
            'Shifted To Line 3',
            "\n",
            'Line 4 Remains The Same'
        );

        $this->currentMock->correctStreetAddress($streetAddressData);

        $this->assertEquals(count($streetAddressData), 4);
        $this->assertNotEmpty($streetAddressData[0]);
        $this->assertLessThan(50, strlen($streetAddressData[0]));
        $this->assertNotEmpty($streetAddressData[1]);
        $this->assertLessThan(50, strlen($streetAddressData[1]));
        $this->assertNotEmpty($streetAddressData[2]);
        $this->assertLessThan(50, strlen($streetAddressData[2]));
        $this->assertEquals('Shifted To Line 3', $streetAddressData[2]);
        $this->assertNotEmpty($streetAddressData[3]);
        $this->assertLessThan(50, strlen($streetAddressData[3]));
        $this->assertEquals('Line 4 Remains The Same', $streetAddressData[3]);
        $this->assertNotEquals($originalStreetAddressData, $streetAddressData);
        $this->assertEquals(
            trim(implode(' ', array_filter($originalStreetAddressData, function($v){return $v!=="\n";}))),
            trim(implode(' ', $streetAddressData))
        );
    }

    /**
     * Test that when and address line must be broken, and there is no empty address
     * fields, then wrapping and concatenation is used to correct the problem
     */
    function testCorrectStreetAddressByWrappingAndConcatenation() {
        $originalStreetAddressData = $streetAddressData = array(
            '32 Fox Hut Drive',
            'Special Care: Velvet Fox Group, Thurston Hall George Washington University',
            '3rd Floor',
            'Black Door'
        );

        $this->currentMock->correctStreetAddress($streetAddressData);


        $this->assertEquals(count($streetAddressData), 4);
        $this->assertNotEmpty($streetAddressData[0]);
        $this->assertLessThan(51, strlen($streetAddressData[0]));
        $this->assertNotEmpty($streetAddressData[1]);
        $this->assertLessThan(51, strlen($streetAddressData[1]));
        $this->assertNotEmpty($streetAddressData[2]);
        $this->assertLessThan(51, strlen($streetAddressData[2]));
        $this->assertEquals('George Washington University 3rd Floor', $streetAddressData[2]);
        $this->assertNotEmpty($streetAddressData[3]);
        $this->assertLessThan(51, strlen($streetAddressData[3]));
        $this->assertEquals('Black Door', $streetAddressData[3]);
        $this->assertNotEquals($originalStreetAddressData, $streetAddressData);
        $this->assertEquals(
            trim(implode(' ', $originalStreetAddressData)),
            trim(implode(' ', $streetAddressData))
        );

    }


    /**
     * Test that when the last address line is too long, then truncation is performed
     * to resolve the issue
     */
    function testCorrectStreetAddressByTruncation() {
        $streetAddressData = array(
            '32 Fox Hut Drive',
            '3rd Floor',
            'Black Door',
            'Special Attention: Velvet Fox Group, Thurston Hall George Washington University'
        );

        $this->currentMock->correctStreetAddress($streetAddressData);

        $this->assertEquals(count($streetAddressData), 4);
        $this->assertNotEmpty($streetAddressData[0]);
        $this->assertLessThan(51, strlen($streetAddressData[0]));
        $this->assertNotEmpty($streetAddressData[1]);
        $this->assertLessThan(51, strlen($streetAddressData[1]));
        $this->assertNotEmpty($streetAddressData[2]);
        $this->assertLessThan(51, strlen($streetAddressData[2]));
        $this->assertNotEmpty($streetAddressData[3]);
        $this->assertLessThan(51, strlen($streetAddressData[3]));
        $this->assertEquals('Special Attention: Velvet Fox Group, Thurston Hall', $streetAddressData[3]);

    }

}