<?php

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

    public static $orderRequest = array(
        'token' => 'addc7c36e014f6216599f631dd021dbba283efc2c5fe9468f4a66be5bf1ae495',
        'cart' => array(
            'order_reference' => '772',
            'display_id' => '145000015|773',
            'currency' => array(),
            'subtotal_amount' => array(),
            'total_amount' => array(),
            'tax_amount' => array(),
            'shipping_amount' => array(),
            'discount_amount' => array(),
            'billing_address' => array(),
            'items' => array(0 => array('reference' => '2539')),
            'shipments' => array(),
        ),
        'external_data' => array(),
    );

    public static $orderResponseJson = array(
        'token' => 'addc7c36e014f6216599f631dd021dbba283efc2c5fe9468f4a66be5bf1ae495',
        'cart' => array(
            'order_reference' => '772',
            'display_id' => '145000015|773',
            'currency' => array(),
            'subtotal_amount' => array(),
            'total_amount' => array(),
            'tax_amount' => array(),
            'shipping_amount' => array(),
            'discount_amount' => array(),
            'billing_address' => array(),
            'items' => array(0 => array('reference' => '2539')),
            'shipments' => array(),
        ),
        'external_data' => array(),
    );

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
        $this->markTestIncomplete('broken test');

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
     * @group        Model
     * @group        ModelBoltOrder
     * @dataProvider getBoltOrderTokenCases
     * @param array $case
     */
    public function getBoltOrderToken(array $case)
    {
        $quoteMock = $this->createQuoteMock($case['items'], $case['shipping_method'], $case['quote_is_virtual']);
        $helper = $this->getMockBuilder(Bolt_Boltpay_Helper_Data::class)
            ->setMethods(array('transmit'))
            ->getMock();
        $blockMock = $this->getMockBuilder(Bolt_Boltpay_Model_BoltOrder::class)
            ->setMethods(array('getActiveMethodRate'))
            ->getMock();
        $blockMock->method('getActiveMethodRate')
            ->will($this->returnValue($case['admin_active_method_rate']));

        $mock = $this->getMockBuilder(Bolt_Boltpay_Model_BoltOrder::class)
            ->setMethods(array('validateVirtualQuote', 'buildOrder', 'boltHelper', 'isAdmin', 'getLayoutBlock'))
            ->getMock();
        $mock->method('validateVirtualQuote')
            ->willReturn(false);
        $mock->method('isAdmin')
            ->will($this->returnValue($case['is_admin']));
        $mock->method('getLayoutBlock')
            ->will($this->returnValue($blockMock));
        $mock->method('buildOrder')
            ->withAnyParameters()
            ->will($this->returnValue($case['buildOrderData']));

        $helper->method('transmit')
            ->with('orders', $case['buildOrderData'])
            ->will($this->returnValue($case['result']));
        $mock->method('boltHelper')
            ->will($this->returnValue($helper));

        $result = $mock->getBoltOrderToken($quoteMock, $case['checkoutType']);

        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     *
     * @return array
     */
    public function getBoltOrderTokenCases()
    {
        $resultJson = self::$orderResponseJson;
        $orderRequestData = self::$orderRequest;

        return array(
            array(
                'case' => array(
                    'expect' => $resultJson,
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
                    'shipping_method' => '',
                    'items' => $orderRequestData['cart']['items'],
                    'buildOrderData' => $orderRequestData,
                    'result' => $resultJson,
                    'is_admin' => false,
                    'admin_active_method_rate' => false,
                    'quote_is_virtual' => false,
                ),
            ),
            array(
                'case' => array(
                    'expect' => $resultJson,
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'shipping_method' => 'flatrate_flatrate',
                    'items' => $orderRequestData['cart']['items'],
                    'buildOrderData' => $orderRequestData,
                    'result' => $resultJson,
                    'is_admin' => true,
                    'admin_active_method_rate' => array('test'),
                    'quote_is_virtual' => false,
                    'validate_virtual_quote' => true,
                ),
            ),
            array(
                'case' => array(
                    'expect' => $resultJson,
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'shipping_method' => 'flatrate_flatrate',
                    'items' => $orderRequestData['cart']['items'],
                    'buildOrderData' => $orderRequestData,
                    'result' => $resultJson,
                    'is_admin' => true,
                    'admin_active_method_rate' => array(),
                    'quote_is_virtual' => true,
                    'validate_virtual_quote' => true,
                ),
            ),
        );
    }

    /**
     * @test
     * @group        Model
     * @group        ModelBoltOrder
     * @dataProvider getBoltOrderTokenErrorCases
     * @param array $case
     */
    public function getBoltOrderTokenExpectErrors(array $case)
    {
        $quoteMock = $this->createQuoteMock($case['items'], $case['shipping_method'], $case['quote_is_virtual']);
        $helper = $this->getMockBuilder(Bolt_Boltpay_Helper_Data::class)
            ->setMethods(array('transmit'))
            ->getMock();
        $blockMock = $this->getMockBuilder(Bolt_Boltpay_Model_BoltOrder::class)
            ->setMethods(array('getActiveMethodRate'))
            ->getMock();
        $blockMock->method('getActiveMethodRate')
            ->will($this->returnValue($case['admin_active_method_rate']));

        $mockMethods = array('validateVirtualQuote', 'buildOrder', 'boltHelper', 'getLayoutBlock',
            'isAdmin');
        $mock = $this->getMockBuilder(Bolt_Boltpay_Model_BoltOrder::class)
            ->setMethods($mockMethods)
            ->getMock();
        $mock->method('validateVirtualQuote')
            ->will($this->returnValue($case['validate_virtual_quote']));
        $mock->method('buildOrder');
        $mock->method('getLayoutBlock')
            ->with('adminhtml/sales_order_create_shipping_method_form')
            ->will($this->returnValue($blockMock));
        $mock->method('isAdmin')
            ->will($this->returnValue($case['is_admin']));

        $helper->method('transmit');
        $mock->method('boltHelper')
            ->will($this->returnValue($helper));

        $result = $mock->getBoltOrderToken($quoteMock, $case['checkoutType']);

        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getBoltOrderTokenErrorCases()
    {
        $orderRequestData = self::$orderRequest;
        return array(
            array(
                'case' => array(
                    'expect' => json_decode('{"token" : "", "error": "Your shopping cart is empty. Please add products to the cart."}'),
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
                    'items' => array(),
                    'shipping_method' => '',
                    'admin_active_method_rate' => array(),
                    'validate_virtual_quote' => false,
                    'quote_is_virtual' => false,
                    'is_admin' => false
                ),
            ),
            array(
                'case' => array(
                    'expect' => json_decode('{"token" : "", "error": "A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again."}'),
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'items' => $orderRequestData['cart']['items'],
                    'shipping_method' => '',
                    'admin_active_method_rate' => array(),
                    'quote_is_virtual' => false,
                    'validate_virtual_quote' => true,
                    'is_admin' => true,
                ),
            ),
            array(
                'case' => array(
                    'expect' => json_decode('{"token" : "", "error": "Billing address is required."}'),
                    'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'items' => $orderRequestData['cart']['items'],
                    'shipping_method' => '',
                    'admin_active_method_rate' => array(),
                    'quote_is_virtual' => true,
                    'validate_virtual_quote' => false,
                    'is_admin' => true,
                ),
            ),
        );
    }

    private function createQuoteMock($items = array(), $shippingMethod = '', $isVirtual = false)
    {
        $shipAddressMock = $this->getMockBuilder(Mage_Sales_Model_Quote_Address::class)
            ->setMethods(array('getShippingMethod'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
        $shipAddressMock->method('getShippingMethod')
            ->will($this->returnValue($shippingMethod));

        $storeMock = $this->getMockBuilder(Mage_Core_Model_Store::class)
            ->setMethods(array('getId', 'getWebsiteId'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
        $storeMock->method('getId')
            ->willReturn(1);
        $storeMock->method('getWebsiteId')
            ->willReturn(1);

        $quoteMethods = array('getAllVisibleItems', 'getShippingAddress', 'isVirtual', 'getStore',
            'getCustomerGroupId');
        $quoteMock = $this->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
        $quoteMock->expects($this->any())
            ->method('getAllVisibleItems')
            ->willReturn($items);
        $quoteMock->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($shipAddressMock);
        $quoteMock->expects($this->any())
            ->method('isVirtual')
            ->will($this->returnValue($isVirtual));
        $quoteMock->expects($this->any())
            ->method('getCustomerGroupId')
            ->willReturn(1);
        $quoteMock->expects($this->any())
            ->method('getStore')
            ->willReturn($storeMock);

        return $quoteMock;
    }
}
