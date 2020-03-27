<?php

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_BoltOrder
 */
class Bolt_Boltpay_Model_BoltOrderTest extends PHPUnit_Framework_TestCase
{
	use Bolt_Boltpay_MockingTrait;

	/** @var string Name of tested class */
	protected $testClassName = 'Bolt_Boltpay_Model_BoltOrder';

	/** @var int|null Dummy product id */
	private static $productId = null;

	/** @var int Dummy virtual product id */
	private static $virtualProductId = null;

	/** @var string Dummy coupon code */
	private static $couponCode = '';

	/** @var int|null Dummy sales rule id */
	private static $salesRuleId = null;

	/** @var string locale of store prior to test */
	private static $originalLocale;

	/**
	 * @var MockObject|Bolt_Boltpay_Model_BoltOrder mocked instance of tested class
	 */
	private $currentMock;

	/**
	 * @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of Bolt helper
	 */
	private $boltHelperMock;

	/** @var array Dummy result of {@see Bolt_Boltpay_Model_BoltOrder::buildOrder} */
	public static $orderRequest = array(
		'cart' => array(
			'order_reference' => '772',
			'display_id' => '145000015|773',
			'currency' => 'USD',
			'subtotal_amount' => 0,
			'total_amount' => 0,
			'tax_amount' => 0,
			'shipping_amount' => 0,
			'discount_amount' => 0,
			'billing_address' => array(),
			'items' => array(array('reference' => '2539')),
			'shipments' => array(),
		),
	);

	/** @var array Dummy result of {@see Bolt_Boltpay_Model_BoltOrder::getBoltOrderToken} */
	public static $orderResponse = array(
		'token' => 'addc7c36e014f6216599f631dd021dbba283efc2c5fe9468f4a66be5bf1ae495',
		'cart' => array(
			'order_reference' => '772',
			'display_id' => '145000015|773',
			'currency' => 'USD',
			'subtotal_amount' => 0,
			'total_amount' => 0,
			'tax_amount' => 0,
			'shipping_amount' => 0,
			'discount_amount' => 0,
			'billing_address' => array(),
			'items' => array(array('reference' => '2539')),
			'shipments' => array(),
		),
		'external_data' => array(),
	);

	/** @var array Dummy result of {@ee Mage_Catalog_Model_Product_Type_Abstract::getOrderOptions} */
	private static $dummyItemProductOptions = array(
		'info_buyRequest' => array(
			'uenc' => 'aHR0cDovL21hZ2VudG8xOTQzLmxvY2FsL2luZGV4LnBocC9jYXRhbG9nL3Byb2R1Y3Qvdmlldy9pZC8zMzExLz9fX19TSUQ9VQ,,',
			'product' => '3311',
			'form_key' => 'QCyddEoDPeEuEov3',
			'related_product' => '',
			'qty' => '1',
		),
		'attributes_info' => array(
			array('label' => 'Color', 'value' => 'red')
		),
		'options' => array(
			array(
				'label' => 'Color',
				'value' => 'red',
				'print_value' => 'red',
				'option_id' => '1',
				'option_type' => 'drop_down',
				'option_value' => '2',
				'custom_view' => false,
			),
		),
		'additional_options' => array(
			array('label' => 'Test Label', 'value' => 'Test Value')
		),
		'bundle_options' => array(
			array(
				'option_id' => '1',
				'label' => 'Test',
				'value' =>
					array(
						array(
							'title' => '123',
							'qty' => 1,
							'price' => 156,
						),
					),
			),
		)
	);

	/** @var array Default address data */
	private static $defaultAddressData = array(
		'email' => 'test-old@bolt.com',
		'firstname' => 'Luke',
		'lastname' => 'Skywalker',
		'street' => 'Sample Street 10',
		'city' => 'Los Angeles',
		'postcode' => '90014',
		'telephone' => '+1 867 345 123 5681',
		'country_id' => 'US',
		'region_id' => 12
	);

	/**
	 * Configure test dependencies, called before each test
	 *
	 * @throws Exception if test class name is not defined
	 */
	public function setUp()
	{
		$this->currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper', 'isAdmin'))
			->getMock();
		$this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
			->setMethods(array('notifyException', 'transmit'))
			->getMock();
		$this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
	}

	/**
	 * Restore stubbed values, clear registry and truncate cart
	 *
	 * @throws ReflectionException if unable to restore _config property of Mage class
	 * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
	 * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
	 */
	protected function tearDown()
	{
		Mage::getSingleton('checkout/cart')->truncate()->save();
		TestHelper::restoreOriginals();
		TestHelper::clearRegistry();
	}

	/**
	 * Generate dummy products for testing purposes
	 *
	 * @throws Exception if unable to create dummy product
	 */
	public static function setUpBeforeClass()
	{
		// Create some dummy products
		self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'));
		self::$virtualProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(
			uniqid('PHPUNIT_TEST_VIRTUAL_'),
			array('type_id' => Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
		);
		self::$couponCode = uniqid('BOLTORDER_COUPON_CODE_');
		self::$salesRuleId = Bolt_Boltpay_CouponHelper::createDummyRule(self::$couponCode);
		self::$originalLocale = Mage::getStoreConfig('general/locale/code');
		Mage::getConfig()->saveConfig('general/locale/code', 'en_US');
	}

	/**
	 * Delete dummy products after the test
	 *
	 * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
	 */
	public static function tearDownAfterClass()
	{
		Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
		Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$virtualProductId);
		Bolt_Boltpay_CouponHelper::deleteDummyRule(self::$salesRuleId);
		Mage::getConfig()->saveConfig('general/locale/code', self::$originalLocale);
	}

    /**
     * @test
     * that buildOrder returns cart created by {@see Bolt_Boltpay_Model_BoltOrder::buildCart} passed through filter event
     *
     * @covers ::buildOrder
     *
     * @dataProvider buildOrder_always_buildsOrderDataProvider
     *
     * @param bool $isMultiPage provided to buildCart and filter event
     * @param bool $isProductPage provided to buildCart and filter event
     *
     * @throws Mage_Core_Model_Store_Exception from tested method if store cannot be found
     */
    public function buildOrder_always_buildsOrderData($isMultiPage, $isProductPage)
    {
        /** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('buildCart', 'isAdmin', 'boltHelper'))
            ->getMock();
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('dispatchFilterEvent'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($boltHelperMock);
        $quote = Mage::getModel('sales/quote');
        $currentMock->expects($this->once())->method('buildCart')->with($quote, $isMultiPage, $isProductPage)
            ->willReturn(self::$orderRequest['cart']);
        $boltHelperMock->expects($this->once())->method('dispatchFilterEvent')
            ->with(
                "bolt_boltpay_filter_bolt_order",
                self::$orderRequest,
                array('quote' => $quote, 'isMultiPage' => $isMultiPage, 'isProductPage' => $isProductPage)
            )
            ->willReturn(self::$orderRequest);
        $result = $currentMock->buildOrder($quote, $isMultiPage, $isProductPage);
        $this->assertEquals(self::$orderRequest, $result);
    }

    /**
     * Data provider for {@see buildOrder_always_buildsOrderData}
     *
     * @return array containing isMultiPage and isProductPage flags
     */
    public function buildOrder_always_buildsOrderDataProvider()
    {
        return array(
            array('isMultiPage' => true, 'isProductPage' => true),
            array('isMultiPage' => false, 'isProductPage' => true),
            array('isMultiPage' => true, 'isProductPage' => false),
            array('isMultiPage' => false, 'isProductPage' => false),
        );
    }

	/**
	 * @test
	 * that buildCart generates cart submission data for sending to Bolt when checkout type is multi-page
	 *
	 * @covers ::buildCart
	 *
	 * @throws Mage_Core_Model_Store_Exception from tested method if the store cannot be determined
	 * @throws Exception if unable to create cart
	 */
	public function buildCart_whenMultipageCheckout_returnsQuoteSubmissionData()
	{
		$cart = TestHelper::addProduct(self::$productId, 2);

		$_quote = $cart->getQuote();
		$_quote->reserveOrderId();
		$_isMultipage = true;
		/** @var Mage_Sales_Model_Quote_Item $item */
		$item = $_quote->getItemsCollection()->getFirstItem();
		$product = $item->getProduct();

		/** @var Bolt_Boltpay_Helper_Data $helper */
		$helper = Mage::helper('boltpay');
		$imageUrl = $helper->getItemImageUrl($item);

		$helper->collectTotals($_quote)->save();

		$expected = array(
			'order_reference' => $_quote->getParentQuoteId(),
			'display_id' => $_quote->getReservedOrderId() . "|" . $_quote->getId(),
			'items' =>
				array(
					array(
						'reference' => $item->getId(),
						'image_url' => (string)$imageUrl,
						'name' => $item->getName(),
						'sku' => $item->getSku(),
						'description' => substr($product->getDescription(), 0, 8182) ?: '',
						'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
						'unit_price' => round($item->getCalculationPrice() * 100),
						'quantity' => $item->getQty(),
						'type' => 'physical',
						'properties' => array()
					),
				),
			'currency' => $_quote->getQuoteCurrencyCode(),
			'discounts' => array(),
			'total_amount' => round($_quote->getSubtotal() * 100),
		);

		$result = $this->currentMock->buildCart($_quote, $_isMultipage);

		$this->assertEquals($expected, $result);
	}

	/**
	 * @test
	 * that buildCart returns 0 as total amount instead of a negative value
	 *
	 * @covers ::buildCart
	 *
	 * @throws Mage_Core_Model_Store_Exception from tested method if the store cannot be determined
	 * @throws Exception if unable to create cart
	 */
	public function buildCart_withNegativeTotal_returnsZeroTotal()
	{
		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(array('createLayoutBlock', 'isAdmin'))
			->getMock();
		$totalsBlockMock = $this->getClassPrototype('Mage_Adminhtml_Block_Sales_Order_Create_Totals')
			->setMethods(array('getTotals'))->getMock();
		$shippingMethodBlockMock = $this->getClassPrototype(
			'Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form'
		)->setMethods(array('getActiveMethodRate'))->getMock();
		$currentMock->method('createLayoutBlock')->willReturnMap(
			array(
				array('adminhtml/sales_order_create_totals_shipping', $totalsBlockMock),
				array('adminhtml/sales_order_create_shipping_method_form', $shippingMethodBlockMock),
			)
		);
		$currentMock->expects($this->atLeastOnce())->method('isAdmin')->willReturn(true);
		$totalsBlockMock->method('getTotals')->willReturn(
			array(
				'grand_total' => Mage::getModel('sales/quote_address_total', array('value' => -1000)),
			)
		);
		$quote = Mage::getModel('sales/quote');
		$result = $currentMock->buildCart($quote, false);
		$this->assertSame(0, $result['total_amount']);
	}

	/**
	 * @test
	 * that buildCart generates cart submission data for sending to Bolt
	 *
	 * @covers ::buildCart
	 *
	 * @throws Mage_Core_Model_Store_Exception from tested method if the store cannot be determined
	 * @throws Exception if unable to create cart
	 */
	public function buildCart_whenNotMultipageCheckout_returnsQuoteSubmissionData()
	{
		$qty = 2;
		/** @var Mage_Catalog_Model_Product $dummyProduct */
		$dummyProduct = Mage::getModel('catalog/product')->load(self::$productId);
		$dummyQuote = Mage::getModel('sales/quote', array('coupon_code' => self::$couponCode));
		$dummyQuoteItem = $dummyQuote->addProduct($dummyProduct, $qty);
		$dummyQuote->reserveOrderId();
		$dummyQuote->save();

		/** @var Mage_Sales_Model_Quote_Address $shippingAddress */
		$shippingAddress = $dummyQuote->getShippingAddress();
		$shippingAddress->addData(
			array(
				'email' => 'test@bolt.com',
				'firstname' => 'Luke',
				'lastname' => 'Skywalker',
				'street' => 'Sample Street 10' . "\n" . '4th Floor' . "\n" . 'Apt 123' . "\n" . 'Attention: Jedi Knights',
				'city' => 'Los Angeles',
				'postcode' => '90014',
				'telephone' => '+1 867 345 123 5681',
				'country_id' => 'US',
				'company' => 'Bolt',
				'region_id' => '12',
				'region' => 'California'
			)
		);
		$shippingAddress->setShippingMethod('flatrate_flatrate');
		$shippingAddress->setCollectShippingRates(true);
		$shippingAddress->save();
		Mage::helper('boltpay')->collectTotals($dummyQuote, true)->save();

		$expected = array(
			'order_reference' => $dummyQuote->getParentQuoteId(),
			'display_id' => $dummyQuote->getReservedOrderId() . "|" . $dummyQuote->getId(),
			'items' => array(
				array(
					'reference' => $dummyQuoteItem->getId(),
					'image_url' => '',
					'name' => $dummyProduct->getName(),
					'sku' => $dummyProduct->getSku(),
					'description' => $dummyProduct->getData('description'),
					'total_amount' => $dummyProduct->getFinalPrice() * $qty * 100,
					'unit_price' => $dummyProduct->getFinalPrice() * 100,
					'quantity' => $qty,
					'type' => 'physical',
					'properties' => array(),
				),
			),
			'currency' => $dummyQuote->getQuoteCurrencyCode(),
			'discounts' => array(
				array(
					'description' => 'Discount (Dummy Percent Rule Frontend Label)',
					'type' => 'fixed_amount',
				)
			),
			'billing_address' => array(
				'street_address1' => 'Sample Street 10',
				'street_address2' => '4th Floor',
				'street_address3' => 'Apt 123',
				'street_address4' => 'Attention: Jedi Knights',
				'first_name' => 'Luke',
				'last_name' => 'Skywalker',
				'locality' => 'Los Angeles',
				'region' => 'California',
				'postal_code' => '90014',
				'country_code' => 'US',
				'phone' => '+1 867 345 123 5681',
				'email' => 'test@bolt.com',
			),
			'total_amount' => round($dummyQuote->getGrandTotal() * 100),
			'shipments' => array(
				array(
					'shipping_address' =>
						array(
							'street_address1' => 'Sample Street 10',
							'street_address2' => '4th Floor',
							'street_address3' => 'Apt 123',
							'street_address4' => 'Attention: Jedi Knights',
							'first_name' => 'Luke',
							'last_name' => 'Skywalker',
							'locality' => 'Los Angeles',
							'region' => 'California',
							'postal_code' => '90014',
							'country_code' => 'US',
							'phone' => '+1 867 345 123 5681',
							'email' => 'test@bolt.com',
						),
					'service' => 'Flat Rate - Fixed',
					'carrier' => 'flatrate_flatrate',
					'reference' => 'flatrate_flatrate'
				),
			)
		);

		$result = $this->currentMock->buildCart($dummyQuote, false);

		$this->assertArraySubset($expected, $result);
	}

	/**
	 * @test
	 * that buildCart, if in admin, collects grand total and tax from order create totals block
	 * and active shipping method from order create shipping method form block
	 *
	 * @covers ::buildCart
	 *
	 * @throws Exception if test class name is not defined
	 */
	public function buildCart_whenInAdmin_collectsDataFromBlocks()
	{
		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(array('createLayoutBlock', 'isAdmin'))
			->getMock();
		$totalsBlockMock = $this->getClassPrototype('Mage_Adminhtml_Block_Sales_Order_Create_Totals')
			->setMethods(array('getTotals'))->getMock();
		$shippingMethodBlockMock = $this->getClassPrototype(
			'Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form'
		)->setMethods(array('getActiveMethodRate'))->getMock();
		$dummyQuote = Mage::getModel('sales/quote', array('customer_email' => 'test@bolt.com'));
		$currentMock->method('createLayoutBlock')->willReturnMap(
			array(
				array('adminhtml/sales_order_create_totals_shipping', $totalsBlockMock),
				array('adminhtml/sales_order_create_shipping_method_form', $shippingMethodBlockMock),
			)
		);

		$currentMock->method('isAdmin')->willReturn(true);
		$adminSessionMock = $this->getClassPrototype('Mage_Admin_Model_Session')
			->setMethods(array('getOrderShippingAddress'))->getMock();
		$adminSessionCartShippingAddress = array(
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
		$adminSessionMock->method('getOrderShippingAddress')->willReturn($adminSessionCartShippingAddress);
		TestHelper::stubSingleton('admin/session', $adminSessionMock);

		$totalsBlockMock->method('getTotals')->willReturn(
			array(
				'grand_total' => Mage::getModel('sales/quote_address_total', array('value' => 123)),
				'tax' => Mage::getModel('sales/quote_address_total', array('value' => 5)),
				'shipping' => Mage::getModel('sales/quote_address_total', array('value' => 56)),
			)
		);
		$shippingMethodBlockMock->method('getActiveMethodRate')
			->willReturn(
				Mage::getModel(
					'sales/quote_address_rate',
					array(
						'method_title' => 'Flatrate',
						'carrier_title' => 'Flatrate'
					)
				)
			);
		$this->assertArraySubset(
			array(
				'total_amount' => 12300,
				'tax_amount' => 500,
				'shipments' => array(
					array(
						'shipping_address' =>
							array(
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
								'region' => 'California',
							),
						'tax_amount' => 0,
						'service' => 'Flatrate',
						'carrier' => 'Flatrate',
						'cost' => 5600,
					),
				)
			),
			$currentMock->buildCart($dummyQuote, false)
		);
	}

	/**
	 * @test
	 * that buildCart, if in admin, takes grand total and tax from order create totals block
	 *
	 * @covers ::buildCart
	 *
	 * @throws Exception if test class name is not defined
	 */
	public function buildCart_whenInAdminAndQuoteVirtual_noShippingIsNeeded()
	{
		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(array('createLayoutBlock', 'isAdmin'))
			->getMock();
		$totalsBlockMock = $this->getClassPrototype('Mage_Adminhtml_Block_Sales_Order_Create_Totals')
			->setMethods(array('getTotals'))->getMock();
		$shippingMethodBlockMock = $this->getClassPrototype(
			'Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form'
		)
			->setMethods(array('getActiveMethodRate'))->getMock();
		$virtualProduct = Mage::getModel('catalog/product')->load(self::$virtualProductId);

		$dummyQuote = Mage::getModel('sales/quote');
		$dummyQuote->addProduct($virtualProduct, 1);

		/** @var MockObject|Mage_Sales_Model_Quote $dummyQuoteMock */
		$dummyQuoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote', false)
			->enableProxyingToOriginalMethods()
			->setProxyTarget($dummyQuote)
			->getMock();
		$currentMock->method('createLayoutBlock')->willReturnMap(
			array(
				array('adminhtml/sales_order_create_totals_shipping', $totalsBlockMock),
				array('adminhtml/sales_order_create_shipping_method_form', $shippingMethodBlockMock),
			)
		);

		$currentMock->expects($this->atLeastOnce())->method('isAdmin')->willReturn(true);
		$adminSessionMock = $this->getClassPrototype('Mage_Admin_Model_Session')
			->setMethods(array('getOrderShippingAddress'))->getMock();
		$adminSessionCartShippingAddress = array(
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
		$adminSessionMock->method('getOrderShippingAddress')->willReturn($adminSessionCartShippingAddress);
		TestHelper::stubSingleton('admin/session', $adminSessionMock);

		$totalsBlockMock->method('getTotals')->willReturn(
			array(
				'grand_total' => Mage::getModel('sales/quote_address_total', array('value' => 123)),
				'tax' => Mage::getModel('sales/quote_address_total', array('value' => 5)),
				'shipping' => Mage::getModel('sales/quote_address_total', array('value' => 56)),
			)
		);
		$shippingMethodBlockMock->method('getActiveMethodRate')
			->willReturn(null);
		$result = $currentMock->buildCart($dummyQuoteMock, false);
		$this->assertArraySubset(
			array(
				'total_amount' => 12300,
				'tax_amount' => 500,
				'shipments' => array(
					array(
						'tax_amount' => 0,
						'service' => 'No Shipping Required',
						'reference' => 'noshipping',
						'cost' => 0,
					),
				)
			),
			$result
		);
	}

	/**
	 * @test
	 * that addDiscounts properly handles discount coupon code
	 *
	 * @covers ::addDiscounts
	 *
	 * @throws Varien_Exception if unable to create dummy rule
	 * @throws ReflectionException if addDiscounts method does not exist
	 * @throws Zend_Db_Adapter_Exception if unable to delete dummy rule
	 */
	public function addDiscounts_withCouponCode_addsDiscountToSubmissionData()
	{
		$ruleName = 'Test Rule';
		$couponCode = uniqid('discount-coupon_');
		$ruleId = Bolt_Boltpay_CouponHelper::createDummyRule($couponCode, array('name' => $ruleName));
		$totals = array(
			'discount' => Mage::getModel(
				'sales/quote_address_total',
				array('value' => 123, 'title' => "Discount ($ruleName)")
			)
		);
		$cartSubmissionData = array();
		$quote = Mage::getModel('sales/quote', array('coupon_code' => $couponCode));

		$this->assertSame(
			12300,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'addDiscounts',
				array(
					$totals,
					&$cartSubmissionData,
					$quote
				)
			)
		);
		$this->assertSame(
			array(
				array(
					'amount' => 12300,
					'description' => "Discount ($ruleName)",
					'type' => 'fixed_amount',
					'reference' => $couponCode,
				)
			),
			$cartSubmissionData['discounts']
		);
		Bolt_Boltpay_CouponHelper::deleteDummyRule($ruleId);
	}

	/**
	 * @test
	 * that addDiscounts properly handles multiple discounts at the same time
	 *
	 * @covers ::addDiscounts
	 *
	 * @throws ReflectionException if addDiscounts method is not defined
	 */
	public function addDiscounts_withMultipleDiscounts_addsDiscountsToSubmissionData()
	{
		$totals = array(
			'giftcardcredit' => new Varien_Object(array('value' => -345.65, 'title' => 'Gift Card')),
			'giftcardcredit_after_tax' => new Varien_Object(array('value' => 22, 'title' => 'Gift Card After Tax')),
			'giftvoucher' => new Varien_Object(array('value' => -15.55, 'title' => 'Gift Voucher')),
			'giftvoucher_after_tax' => new Varien_Object(
				array('value' => 64.38, 'title' => 'Gift Voucher After Tax')
			),
			'aw_storecredit' => new Varien_Object(
				array('value' => -72, 'title' => 'Aheadworks Store Credit')
			),
			'credit' => new Varien_Object(
				array('value' => 36, 'title' => 'Magestore Customer Credit')
			),
			'amgiftcard' => new Varien_Object(array('value' => 677.45, 'title' => 'Amasty Giftcard')),
			'amstcred' => new Varien_Object(array('value' => -1234, 'title' => 'Amasty Store Credit')),
			'awraf' => new Varien_Object(
				array('value' => 5232, 'title' => 'Aheadworks Refer a Friend')
			),
			'rewardpoints_after_tax' => new Varien_Object(
				array('value' => -1234.35, 'title' => 'Magestore Reward Points')
			),
		);
		$cartSubmissionData = array();
		$this->assertSame(
			893338,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'addDiscounts',
				array(
					$totals,
					&$cartSubmissionData,
					Mage::getModel('sales/quote')
				)
			)
		);
		$this->assertSame(
			array(
				array(
					'amount' => 34565,
					'description' => 'Gift Card',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 2200,
					'description' => 'Gift Card After Tax',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 1555,
					'description' => 'Gift Voucher',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 6438,
					'description' => 'Gift Voucher After Tax',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 7200,
					'description' => 'Aheadworks Store Credit',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 3600,
					'description' => 'Magestore Customer Credit',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 67745,
					'description' => 'Amasty Giftcard',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 123400,
					'description' => 'Amasty Store Credit',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 523200,
					'description' => 'Aheadworks Refer a Friend',
					'type' => 'fixed_amount',
				),
				array(
					'amount' => 123435,
					'description' => 'Magestore Reward Points',
					'type' => 'fixed_amount',
				),
			),
			$cartSubmissionData['discounts']
		);
	}

	/**
	 * @test
	 * that getTax returns tax amount if tax is present inside totals
	 *
	 * @covers ::getTax
	 *
	 * @throws ReflectionException if getTax method is not defined
	 */
	public function getTax_whenTotalsContainTax_returnsTaxAmount()
	{
		$totals = array(
			'tax' => Mage::getModel('sales/quote_address_total', array('value' => 273))
		);
		$this->assertSame(
			27300,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getTax',
				array(
					$totals
				)
			)
		);
	}

	/**
	 * @test
	 * that getTax returns zero if tax is not present inside totals
	 *
	 * @covers ::getTax
	 *
	 * @throws ReflectionException if getTax method is not defined
	 */
	public function getTax_whenTotalsDontContainTax_returnsZero()
	{
		$totals = array();
		$this->assertSame(
			0,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getTax',
				array(
					$totals
				)
			)
		);
	}

	/**
	 * @test
	 * that addShipping returns shipping total and adds shipping information to cart submission data
	 *
	 * @covers ::addShipping
	 *
	 * @throws ReflectionException if addShipping is not defined
	 */
	public function addShipping_whenTotalsContainShipping_addsShippingToSubmissionDataAndReturnsShippingTotal()
	{
		$totals = array(
			'shipping' => Mage::getModel('sales/quote_address_total', array('value' => 456.78))
		);
		$cartSubmissionData = array();
		$shippingAddress = Mage::getModel(
			'sales/quote_address',
			array(
				'shipping_tax_amount' => 12.34,
				'shipping_description' => 'Flat Rate',
				'shipping_method' => 'flatrate_flatrate'
			)
		);
		$boltFormatAddress = array(
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
		$this->assertSame(
			45678,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'addShipping',
				array(
					$totals,
					&$cartSubmissionData,
					$shippingAddress,
					$boltFormatAddress
				)
			)
		);
		$this->assertSame(
			array(
				array(
					'shipping_address' => $boltFormatAddress,
					'tax_amount' => (int)round($shippingAddress->getShippingTaxAmount() * 100),
					'service' => $shippingAddress->getShippingDescription(),
					'carrier' => $shippingAddress->getShippingMethod(),
					'reference' => $shippingAddress->getShippingMethod(),
					'cost' => 45678,
				)
			),
			$cartSubmissionData['shipments']
		);
	}

	/**
	 * @test
	 * that addShippingForAdmin returns shipping total and adds shipping information to cart submission data
	 *
	 * @covers ::addShippingForAdmin
	 *
	 * @throws ReflectionException if addShippingForAdmin is not defined
	 */
	public function addShippingForAdmin_withShippingTotalProvided_addsShippingToSubmissionDataAndReturnsShippingTotal()
	{
		$totalsBlockMock = $this->getClassPrototype(
			'Mage_Adminhtml_Block_Sales_Order_Create_Totals'
		)->setMethods(array('getTotals'))->getMock();
		$totalsBlockMock->method('getTotals')->willReturn(
			array(
				'shipping' => Mage::getModel('sales/quote_address_total', array('value' => 456.78)),
			)
		);
		$cartSubmissionData = array();
		$shippingRate = Mage::getModel(
			'sales/quote_address_rate',
			array(
				'method_title' => 'Flat Rate',
				'carrier_title' => 'Flat Rate',
			)
		);
		$boltFormatAddress = array(
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
		$this->assertSame(
			45678,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'addShippingForAdmin',
				array(
					$totalsBlockMock,
					&$cartSubmissionData,
					$shippingRate,
					$boltFormatAddress,
					null,
				)
			)
		);
		$this->assertSame(
			array(
				array(
					'shipping_address' => $boltFormatAddress,
					'tax_amount' => 0,
					'service' => $shippingRate->getMethodTitle(),
					'carrier' => $shippingRate->getCarrierTitle(),
					'cost' => 45678,
				)
			),
			$cartSubmissionData['shipments']
		);
	}

	/**
	 * @test
	 * that addShippingForAdmin uses 0 as shipping total if shipping total is not provided
	 *
	 * @covers ::addShippingForAdmin
	 *
	 * @throws ReflectionException if addShippingForAdmin is not defined
	 */
	public function addShippingForAdmin_withShippingTotalNotProvided_useZeroAsShippingTotal()
	{
		$totalsBlockMock = $this->getClassPrototype(
			'Mage_Adminhtml_Block_Sales_Order_Create_Totals'
		)->setMethods(array('getTotals'))->getMock();
		$totalsBlockMock->method('getTotals')->willReturn(
			array(
				'shipping' => null,
			)
		);
		$cartSubmissionData = array();
		$shippingRate = Mage::getModel(
			'sales/quote_address_rate',
			array(
				'method_title' => 12.34,
				'carrier_title' => 'Flat Rate',
			)
		);
		$boltFormatAddress = array(
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
		$this->assertSame(
			0,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'addShippingForAdmin',
				array(
					$totalsBlockMock,
					&$cartSubmissionData,
					$shippingRate,
					$boltFormatAddress
				)
			)
		);
		$this->assertSame(
			array(
				array(
					'shipping_address' => $boltFormatAddress,
					'tax_amount' => 0,
					'service' => $shippingRate->getMethodTitle(),
					'carrier' => $shippingRate->getCarrierTitle(),
					'cost' => 0,
				)
			),
			$cartSubmissionData['shipments']
		);
	}

	/**
	 * @test
	 * that getTaxForAdmin returns tax amount in cents if tax total is provided as parameter
	 *
	 * @covers ::getTaxForAdmin
	 *
	 * @throws ReflectionException if getTaxForAdmin is not defined
	 */
	public function getTaxForAdmin_withTaxTotalProvided_returnsTaxTotalAmount()
	{
		$taxTotal = Mage::getModel('sales/quote_address_total', array('value' => 234.56));
		$this->assertSame(
			23456,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getTaxForAdmin',
				array(
					$taxTotal
				)
			)
		);
	}

	/**
	 * @test
	 * that getTaxForAdmin returns zero if tax total is not provided
	 *
	 * @covers ::getTaxForAdmin
	 *
	 * @throws ReflectionException if getTaxForAdmin is not defined
	 */
	public function getTaxForAdmin_withTaxTotalNotProvided_returnsZero()
	{
		$this->assertSame(
			0,
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getTaxForAdmin',
				array(
					null
				)
			)
		);
	}

	/**
	 * @test
	 * that getCorrectedTotal halves the total amount if projected total is exactly two times greater
	 *
	 * @covers ::getCorrectedTotal
	 *
	 * @throws ReflectionException if getCorrectedTotal method is not defined
	 */
	public function getCorrectedTotal_whenProjectedTotalIsDoubleOfTotalAmount_replacesTotalAmountWithProjectedTotal()
	{
		$projectedTotal = 5678;
		$magentoDerivedCartData = array('total_amount' => $projectedTotal * 2);
		$this->assertSame(
			array('total_amount' => $projectedTotal),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getCorrectedTotal',
				array(
					$projectedTotal,
					$magentoDerivedCartData
				)
			)
		);
	}

	/**
	 * @test
	 * that getCorrectedTotal returns unchanged cart data if projected total is not exactly two times greater
	 *
	 * @covers ::getCorrectedTotal
	 *
	 * @throws ReflectionException if getCorrectedTotal method is not defined
	 */
	public function getCorrectedTotal_whenProjectedTotalIsNotDoubleOfTotalAmount_returnsUnchangedCartData()
	{
		$projectedTotal = 5678;
		$magentoDerivedCartData = array('total_amount' => 5690);
		$this->assertSame(
			array('total_amount' => 5690),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getCorrectedTotal',
				array(
					$projectedTotal,
					$magentoDerivedCartData
				)
			)
		);
	}

	/**
	 * @test
	 * that correctBillingAddress returns false if fallback address is not provided
	 *
	 * @covers ::correctBillingAddress
	 */
	public function correctBillingAddress_withoutFallbackAddress_returnsFalse()
	{
		/** @var Mage_Sales_Model_Quote_Address $billingAddress */
		$billingAddress = Mage::getModel('sales/quote_address');
		$this->assertFalse(
			$this->currentMock->correctBillingAddress(
				$billingAddress,
				null
			)
		);
	}

	/**
	 * @test
	 * that correctBillingAddress does not correct billing address it its already complete
	 *
	 * @covers ::correctBillingAddress
	 */
	public function correctBillingAddress_withNoCorrectionNeeded_doesNotPerformCorrection()
	{
		/** @var Mage_Sales_Model_Quote $dummyQuote */
		$dummyQuote = Mage::getModel('sales/quote');
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

		/** @var Mage_Sales_Model_Quote_Address $billingAddress */
		$billingAddress = $dummyQuote->getBillingAddress()->addData($billingAddressData);

		$shippingAddress = $dummyQuote->getShippingAddress()->addData($shippingAddressData);

		$this->assertFalse($this->currentMock->correctBillingAddress($billingAddress, $shippingAddress));

		$result = $billingAddress->getData();
		$this->assertArraySubset($billingAddressData, $result);
	}

	/**
	 * @test
	 * that correctBillingAddress populates all data of provided billing address is empty
	 *
	 * @covers ::correctBillingAddress
	 */
	public function correctBillingAddress_withEmptyBillingAddress_correctsBillingAddress()
	{
		$mockQuote = TestHelper::getCheckoutQuote();
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
			'suffix' => null,
			'email' => 'reporter@general_mills.com',
		);

		$billingAddress = $mockQuote->getBillingAddress();
		$expected['quote_id'] = $mockQuote->getId();

		$shippingAddress = $mockQuote->getShippingAddress()->addData($shippingAddressData);

		$this->assertTrue(
			$this->currentMock->correctBillingAddress($billingAddress, $shippingAddress, false)
		);

		$result = $billingAddress->getData();

		$this->assertArraySubset($expected, $result);
	}

	/**
	 * @test
	 * that correctBillingAddress updates provided billing address by taking the missing data from fallback address
	 *
	 * @dataProvider correctBillingAddressDataProvider
	 *
	 * @covers ::correctBillingAddress
	 *
	 * @param array $billingAddressData to be assigned to billing address that will be corrected
	 * @param array $fallbackAddressData to be assigned to fallback address which will be used for correction
	 * @param array $expectedResultShouldContain address data subset that is expected to be contained inside billing address
	 * @param string|null $expectedExceptionMessage message expected to be provided to
	 *                                              {@see \Bolt_Boltpay_Helper_BugsnagTrait::notifyException}
	 */
	public function correctBillingAddress_withVariousAddressesProvided_correctsBillingAddress(
		$billingAddressData,
		$fallbackAddressData,
		$expectedResultShouldContain,
		$expectedExceptionMessage
	)
	{
		/** @var Mage_Sales_Model_Quote $dummyQuote */
		$dummyQuote = Mage::getModel('sales/quote');
		$dummyQuote->setStoreId(1);

		/** @var Mage_Sales_Model_Quote_Address $billingAddress */
		$billingAddress = $dummyQuote->getBillingAddress()->addData($billingAddressData);
		$shippingAddress = $dummyQuote->getShippingAddress()->addData($fallbackAddressData);

		if ($expectedExceptionMessage) {
			$this->boltHelperMock->expects($this->once())->method('notifyException')
				->with(new Exception($expectedExceptionMessage), array(), 'info');
		}

		$this->assertTrue(
			$this->currentMock->correctBillingAddress($billingAddress, $shippingAddress, true)
		);

		$this->assertFalse($dummyQuote->hasDataChanges(), 'Quote not saved after changes');
		$this->assertFalse($billingAddress->hasDataChanges(), 'Billing address not saved after changes');

		$this->assertArraySubset($expectedResultShouldContain, $billingAddress->getData());
	}

	/**
	 * Data provider for @see correctBillingAddress
	 */
	public function correctBillingAddressDataProvider()
	{
		$completeBillingAddressData = self::$defaultAddressData;
		$fallbackAddressData = array(
			'email' => 'test@bolt.com',
			'street' => 'Other Street 22',
			'city' => 'Another City',
			'postcode' => '44444',
			'country_id' => 'GB',
			'region' => 'Another Region',
			'region_id' => 14,
			'firstname' => 'Luke',
			'lastname' => 'Skywalker',
			'prefix' => 'Mr.',
			'middlename' => 'Anakin',
			'suffix' => 'J.D',
			'telephone' => '+1 234 566 789'
		);
		return array(
			'Billing address missing street' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('street' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'street' => 'Other Street 22',
					'city' => 'Another City',
					'postcode' => '44444',
					'country_id' => 'GB',
					'region' => 'Another Region',
					'region_id' => 14
				),
				'expectedExceptionMessage' => 'Missing critical billing data.  Street:  City: Los Angeles Country: US'
			),
			'Billing address missing city' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('city' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'street' => 'Other Street 22',
					'city' => 'Another City',
					'postcode' => '44444',
					'country_id' => 'GB',
					'region' => 'Another Region',
					'region_id' => 14
				),
				'expectedExceptionMessage' => 'Missing critical billing data.  Street: Sample Street 10 City:  Country: US'
			),
			'Billing address missing country' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('country_id' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'street' => 'Other Street 22',
					'city' => 'Another City',
					'postcode' => '44444',
					'country_id' => 'GB',
					'region' => 'Another Region',
					'region_id' => 14
				),
				'expectedExceptionMessage' => 'Missing critical billing data.  Street: Sample Street 10 City: Los Angeles Country: '
			),
			'Billing address missing name' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('firstname' => null, 'lastname' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'street' => 'Sample Street 10',
					'city' => 'Los Angeles',
					'country_id' => 'US',
					'firstname' => 'Luke',
					'lastname' => 'Skywalker',
					'prefix' => 'Mr.',
					'middlename' => 'Anakin',
					'suffix' => 'J.D',
				),
				'expectedExceptionMessage' => 'Missing billing name.'
			),
			'Billing address missing telephone' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('telephone' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'telephone' => '+1 234 566 789'
				),
				'expectedExceptionMessage' => 'Missing billing telephone.'
			),
			'Billing address missing email' => array(
				'billingAddressData' => array_merge(
					$completeBillingAddressData,
					array('email' => null)
				),
				'fallbackAddressData' => $fallbackAddressData,
				'expectedResultShouldContain' => array(
					'email' => 'test@bolt.com'
				),
				'expectedExceptionMessage' => null
			),
		);
	}

	/**
	 * @test
	 * that getDiscountTypes returns discount types stored in discountTypes protected property
	 *
	 * @covers ::getDiscountTypes
	 */
	public function getDiscountTypes_always_returnsProtectedProperty()
	{
		$this->assertAttributeSame(
			$this->currentMock->getDiscountTypes(),
			'discountTypes',
			$this->currentMock
		);
	}

	/**
	 * @test
	 * that getCustomerEmail returns email from quote
	 *
	 * @covers ::getCustomerEmail
	 *
	 * @throws ReflectionException if getCustomerEmail method doesn't exist
	 */
	public function getCustomerEmail_ifProvidedQuoteContainsCustomerEmail_returnsCustomerEmailFromQuote()
	{
		$quote = Mage::getModel('sales/quote', array('customer_email' => 'test@bolt.com'));
		$this->assertSame(
			$quote->getCustomerEmail(),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getCustomerEmail',
				array(
					$quote
				)
			)
		);
	}

	/**
	 * @test
	 * that getCustomerEmail returns email from admin session order shipping address
	 *
	 * @covers ::getCustomerEmail
	 *
	 * @throws ReflectionException if getCustomerEmail doesn't exist
	 * @throws Mage_Core_Exception if unable to stub singleton
	 */
	public function getCustomerEmail_withEmptyQuoteAddress_returnsAddressFromAdminSession()
	{
		$quote = Mage::getModel('sales/quote');
		$adminSessionMock = $this->getClassPrototype('Mage_Admin_Model_Session')
			->setMethods(array('getOrderShippingAddress'))->getMock();
		$adminSessionMock->expects($this->once())->method('getOrderShippingAddress')
			->willReturn(array('email_address' => 'test@bolt.com'));
		TestHelper::stubSingleton('admin/session', $adminSessionMock);
		$this->currentMock->method('isAdmin')->willReturn(true);

		$this->assertSame(
			'test@bolt.com',
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getCustomerEmail',
				array(
					$quote
				)
			)
		);
	}

	/**
	 * @test
	 * that getCustomerEmail returns customer email from order when in admin order edit or reorder
	 *
	 * @covers ::getCustomerEmail
	 *
	 * @throws ReflectionException if getCustomerEmail doesn't exist
	 * @throws Mage_Core_Exception if unable to stub singleton
	 */
	public function getCustomerEmail_withEmptyQuoteAndSessionEmail_returnsEmailFromCurrentAdminOrder()
	{
		$quote = Mage::getModel('sales/quote');
		$adminSessionMock = $this->getClassPrototype('Mage_Admin_Model_Session')
			->setMethods(array('getOrderShippingAddress'))->getMock();
		$adminSessionMock->expects($this->once())->method('getOrderShippingAddress')
			->willReturn(false);
		TestHelper::stubSingleton('admin/session', $adminSessionMock);

		$adminSessionQuoteMock = $this->getClassPrototype('Mage_Adminhtml_Model_Session_Quote')
			->setMethods(array('getOrderId', 'getReordered'))->getMock();
		$adminSessionQuoteMock->method('getOrderId')->willReturn('123456');
		TestHelper::stubSingleton('adminhtml/session_quote', $adminSessionQuoteMock);

		$orderMock = $this->getClassPrototype('Mage_Sales_Model_Order')
			->setMethods(array('load', 'getCustomerEmail'))->getMock();
		$orderMock->expects($this->once())->method('load')->with('123456')->willReturnSelf();
		$orderMock->expects($this->once())->method('getCustomerEmail')->willReturn('test@bolt.com');
		TestHelper::stubModel('sales/order', $orderMock);

		$this->currentMock->method('isAdmin')->willReturn(true);

		$this->assertSame(
			'test@bolt.com',
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getCustomerEmail',
				array(
					$quote
				)
			)
		);
	}

	/**
	 * @test
	 * that calculateCartCacheKey returns cache key for provided quote
	 * cache key consist of quote id and md5 hash of generated order data for the quote
	 *
	 * @covers ::calculateCartCacheKey
	 *
	 * @dataProvider checkoutTypesProvider
	 *
	 * @param string $checkoutType currently in use
	 *
	 * @throws Mage_Core_Model_Store_Exception if unable to build order
	 * @throws ReflectionException if getCustomerEmail is not defined
	 */
	public function calculateCartCacheKey_withProvidedQuote_returnsCartCacheKey($checkoutType)
	{
		$this->currentMock = $this->getTestClassPrototype()->setMethods(array('correctBillingAddress', 'isAdmin'))
			->getMock();

		$quote = Mage::getModel('sales/quote', array('entity_id' => 456));

		$cacheKey = TestHelper::callNonPublicFunction(
			$this->currentMock,
			'calculateCartCacheKey',
			array(
				$quote,
				$checkoutType
			)
		);
		list($quoteId, $hash) = explode('_', $cacheKey);
		$this->assertEquals(456, $quoteId);
		$boltCartArray = $this->currentMock->buildOrder($quote, $checkoutType == 'multi-page');
		if ($boltCartArray['cart']) {
			unset($boltCartArray['cart']['display_id']);
			unset($boltCartArray['cart']['order_reference']);
		}

		$this->assertEquals(
			md5(json_encode($boltCartArray)),
			$hash
		);
	}

	/**
	 * Setup method for tests covering {@see Bolt_Boltpay_Model_BoltOrder::getCachedCartData}
	 *
	 * @return MockObject[]|Bolt_Boltpay_Model_BoltOrder[]|Mage_Core_Model_Session[]
	 *
	 * @throws Exception if unable to stub singleton
	 */
	private function getCachedCartDataSetUp()
	{
		$currentMock = $this->getTestClassPrototype()->setMethods(array('calculateCartCacheKey'))->getMock();
		$sessionMock = $this->getClassPrototype('Mage_Core_Model_Session')
			->setMethods(array('getCachedCartData', 'unsCachedCartData'))->getMock();
		TestHelper::stubSingleton('core/session', $sessionMock);
		return array($currentMock, $sessionMock);
	}

	/**
	 * @test
	 * that getCachedCartData returns null and removes cached cart data from session if it is expired
	 *
	 * @covers ::getCachedCartData
	 *
	 * @throws Exception if calculateCartCacheKey method is not defined
	 */
	public function getCachedCartData_withExpiredCartData_removesFromCacheAndReturnsNull()
	{
		$checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
		/** @var Mage_Sales_Model_Quote $quote */
		$quote = Mage::getModel('sales/quote');
		list($currentMock, $sessionMock) = $this->getCachedCartDataSetUp();
		$cachedCartDataJS = array(
			'creation_time' => time() - Bolt_Boltpay_Model_BoltOrder::$cached_token_expiration_time,
			'key' => TestHelper::callNonPublicFunction(
				$this->currentMock,
				'calculateCartCacheKey',
				array(
					$quote,
					$checkoutType
				)
			)
		);
		$sessionMock->method('getCachedCartData')->willReturn($cachedCartDataJS);
		$currentMock->method('calculateCartCacheKey')->willReturn($cachedCartDataJS['key']);
		$sessionMock->expects($this->once())->method('unsCachedCartData');
		$this->assertNull(
			$currentMock->getCachedCartData(
				$quote,
				$checkoutType
			)
		);

		$quote->delete();
	}

	/**
	 * @test
	 * that getCachedCartData returns null and removes cached cart data from session if it is expired
	 *
	 * @covers ::getCachedCartData
	 *
	 * @throws Exception if calculateCartCacheKey method is not defined
	 */
	public function getCachedCartData_ifCacheKeyDoesNotmatch_removesFromCacheAndReturnsNull()
	{
		$checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
		/** @var Mage_Sales_Model_Quote $quote */
		$quote = Mage::getModel('sales/quote');
		list($currentMock, $sessionMock) = $this->getCachedCartDataSetUp();
		$cachedCartDataJS = array(
			'creation_time' => time() - Bolt_Boltpay_Model_BoltOrder::$cached_token_expiration_time,
			'key' => md5('test1')
		);
		$sessionMock->method('getCachedCartData')->willReturn($cachedCartDataJS);
		$currentMock->method('calculateCartCacheKey')->willReturn(md5('test2'));
		$sessionMock->expects($this->once())->method('unsCachedCartData');
		$this->assertNull(
			$currentMock->getCachedCartData(
				$quote,
				$checkoutType
			)
		);

		$quote->delete();
	}

	/**
	 * @test
	 * that getCachedCartData returns cached cart data from session if not expired and cache keys match
	 *
	 * @covers ::getCachedCartData
	 *
	 * @throws Exception if calculateCartCacheKey method is not defined
	 */
	public function getCachedCartData_whenNotExpiredAndKeysMatch_returnsCachedCartData()
	{
		$checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
		$quote = Mage::getModel('sales/quote');
		list($currentMock, $sessionMock) = $this->getCachedCartDataSetUp();
		$cachedCartDataJS = array(
			'creation_time' => time(),
			'key' => TestHelper::callNonPublicFunction(
				$this->currentMock,
				'calculateCartCacheKey',
				array(
					$quote,
					$checkoutType
				)
			),
			'cart_data' => json_encode(array('cartContent' => array('item 1', 'item 2')))
		);
		$sessionMock->method('getCachedCartData')->willReturn($cachedCartDataJS);
		$sessionMock->expects($this->never())->method('unsCachedCartData');
		$currentMock->method('calculateCartCacheKey')->willReturn($cachedCartDataJS['key']);
		$this->assertEquals(
			json_decode($cachedCartDataJS['cart_data'], true),
			$currentMock->getCachedCartData(
				$quote,
				$checkoutType
			)
		);

		$quote->delete();
	}

	/**
	 * @test
	 * that cacheCartData sets provided cart data to session
	 *
	 * @covers ::cacheCartData
	 *
	 * @throws Mage_Core_Exception if unable to stub singleton
	 * @throws ReflectionException if calculateCartCacheKey method is not defined
	 */
	public function cacheCartData_always_willSetCachedCartDataToSession()
	{
		$sessionMock = $this->getClassPrototype('Mage_Core_Model_Session')
			->setMethods(array('setCachedCartData'))->getMock();
		TestHelper::stubSingleton('core/session', $sessionMock);

		$cartData = array('cartContent' => array('item 1', 'item 2', 'item 3', 'item 4'));
		$quote = Mage::getModel('sales/quote');
		$checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;

		$sessionMock->expects($this->once())->method('setCachedCartData')->with(
			new PHPUnit_Framework_Constraint_ArraySubset(
				array(
					'key' => TestHelper::callNonPublicFunction(
						$this->currentMock,
						'calculateCartCacheKey',
						array(
							$quote,
							$checkoutType
						)
					),
					'cart_data' => json_encode($cartData)
				)
			)
		);
		$this->currentMock->cacheCartData($cartData, $quote, $checkoutType);
	}

	/**
	 * Setup method for tests covering {@see Bolt_Boltpay_Model_BoltOrder::getBoltOrderToken}
	 *
	 * @param array $items array of quote visible items
	 * @param string $shippingMethod quote shipping method
	 * @param bool $isVirtual quote flag
	 *
	 * @return MockObject|Mage_Sales_Model_Quote
	 */
	private function getBoltOrderTokenSetUp($items = array(), $shippingMethod = '', $isVirtual = false)
	{
		$shipAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
			->setMethods(array('getShippingMethod'))
			->getMock();
		$shipAddressMock->method('getShippingMethod')->willReturn($shippingMethod);

		$storeMock = $this->getMockBuilder('Mage_Core_Model_Store')
			->setMethods(array('getId', 'getWebsiteId'))
			->getMock();
		$storeMock->method('getId')->willReturn(1);
		$storeMock->method('getWebsiteId')->willReturn(1);

		$quoteMock = $this->getMockBuilder('Mage_Sales_Model_Quote')
			->setMethods(
				array(
					'getAllVisibleItems',
					'getShippingAddress',
					'isVirtual',
					'getStore',
					'getCustomerGroupId'
				)
			)
			->getMock();
		$quoteMock->method('getAllVisibleItems')->willReturn($items);
		$quoteMock->method('getShippingAddress')->willReturn($shipAddressMock);
		$quoteMock->method('isVirtual')->willReturn($isVirtual);
		$quoteMock->method('getCustomerGroupId')->willReturn(1);
		$quoteMock->method('getStore')->willReturn($storeMock);

		return $quoteMock;
	}

	/**
	 * @test
	 * that getBoltOrderToken builds order request, transmits it to Bolt API and returns the API response
	 *
	 * @covers ::getBoltOrderToken
	 *
	 * @dataProvider getBoltOrderToken_withValidQuoteProvider
	 *
	 * @param string $checkoutType currently in use
	 * @param string $shippingMethod to be set as quote shipping method
	 * @param bool $isAdmin flag representing if current request is from admin area
	 * @param bool|array $adminActiveMethodRate returned from shipping method form block
	 * @param bool $quoteIsVirtual flag that determines if quote is virtual
	 *
	 * @throws Mage_Core_Model_Store_Exception from tested method if unable to find store
	 * @throws Exception if test class name is not defined
	 */
	public function getBoltOrderToken_withValidQuote_buildsOrderAndTransmitsToBoltAPI(
		$checkoutType,
		$shippingMethod,
		$isAdmin,
		$adminActiveMethodRate,
		$quoteIsVirtual
	)
	{
		$quoteMock = $this->getBoltOrderTokenSetUp(
			self::$orderRequest['cart']['items'],
			$shippingMethod,
			$quoteIsVirtual
		);
		$shippingMethodBlockMock = $this->getMockBuilder('Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form')
			->setMethods(array('getActiveMethodRate'))
			->getMock();
		$shippingMethodBlockMock->method('getActiveMethodRate')->willReturn($adminActiveMethodRate);

		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(array('validateVirtualQuote', 'buildOrder', 'boltHelper', 'isAdmin', 'createLayoutBlock'))
			->getMock();
		$currentMock->method('validateVirtualQuote')->willReturn(false);
		$currentMock->method('isAdmin')->willReturn($isAdmin);
		$currentMock->method('createLayoutBlock')->willReturn($shippingMethodBlockMock);
		$currentMock->method('buildOrder')->willReturn(self::$orderRequest);

		$this->boltHelperMock->method('transmit')->with('orders', self::$orderRequest)
			->willReturn(self::$orderResponse);
		$currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

		$result = $currentMock->getBoltOrderToken($quoteMock, $checkoutType);

		$this->assertEquals(self::$orderResponse, $result);
	}

	/**
	 * Data provider for {@see getBoltOrderToken_withValidQuote_buildsOrderAndTransmitsToBoltAPI}
	 *
	 * @return array containing checkout type
	 */
	public function getBoltOrderToken_withValidQuoteProvider()
	{
		return array(
			array(
				'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
				'shippingMethod' => '',
				'isAdmin' => false,
				'adminActiveMethodRate' => false,
				'quoteIsVirtual' => false,
			),
			array(
				'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
				'shippingMethod' => 'flatrate_flatrate',
				'isAdmin' => true,
				'adminActiveMethodRate' => array('test'),
				'quoteIsVirtual' => false,
			),
			array(
				'checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
				'shippingMethod' => 'flatrate_flatrate',
				'isAdmin' => true,
				'adminActiveMethodRate' => array(),
				'quoteIsVirtual' => true,
			),
		);
	}

	/**
	 * @test
	 * that getBoltOrderToken builds order request, transmits it to Bolt API and returns the API response
	 *
	 * @covers ::getBoltOrderToken
	 *
	 * @throws Mage_Core_Model_Store_Exception from tested method if unable to find store
	 * @throws Exception if test class name is not defined
	 */
	public function getBoltOrderToken_withValidVirtualQuote_buildsOrderAndTransmitsToBoltAPI()
	{
		$quoteMock = $this->getBoltOrderTokenSetUp(self::$orderRequest['cart']['items'], null, true);
		$shippingMethodBlockMock = $this->getMockBuilder('Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form')
			->setMethods(array('getActiveMethodRate'))
			->getMock();
		$shippingMethodBlockMock->method('getActiveMethodRate')->willReturn(null);

		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(array('validateVirtualQuote', 'buildOrder', 'boltHelper', 'isAdmin', 'createLayoutBlock'))
			->getMock();
		$currentMock->method('validateVirtualQuote')->willReturn(true);
		$currentMock->method('isAdmin')->willReturn(true);
		$currentMock->method('createLayoutBlock')->willReturn($shippingMethodBlockMock);
		$currentMock->method('buildOrder')->willReturn(self::$orderRequest);

		$this->boltHelperMock->method('transmit')->with('orders', self::$orderRequest)
			->willReturn(self::$orderResponse);
		$currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

		$result = $currentMock->getBoltOrderToken($quoteMock, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN);

		$this->assertEquals(self::$orderResponse, $result);
	}

	/**
	 * @test
	 * that returns object containing expected error if provided quote is incomplete
	 *
	 * @covers ::getBoltOrderToken
	 *
	 * @dataProvider getBoltOrderToken_withIncompleteQuoteProvider
	 *
	 * @param array $case
	 *
	 * @throws Exception if test class name is not defined
	 */
	public function getBoltOrderToken_withIncompleteQuote_returnsJSONError(array $case)
	{
		$quoteMock = $this->getBoltOrderTokenSetUp($case['items'], $case['shipping_method'], $case['quote_is_virtual']);
		$blockMock = $this->getClassPrototype('Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form')
			->setMethods(array('getActiveMethodRate'))
			->getMock();
		$blockMock->method('getActiveMethodRate')->willReturn($case['admin_active_method_rate']);

		/** @var MockObject|Bolt_Boltpay_Model_BoltOrder $currentMock */
		$currentMock = $this->getTestClassPrototype()
			->setMethods(
				array(
					'validateVirtualQuote',
					'buildOrder',
					'boltHelper',
					'createLayoutBlock',
					'isAdmin'
				)
			)
			->getMock();
		$currentMock->method('validateVirtualQuote')->willReturn($case['validate_virtual_quote']);
		$currentMock->method('createLayoutBlock')->with('adminhtml/sales_order_create_shipping_method_form')
			->willReturn($blockMock);
		$currentMock->method('isAdmin')->willReturn($case['is_admin']);
		$currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

		$this->boltHelperMock->expects($this->never())->method('transmit');

		$result = $currentMock->getBoltOrderToken($quoteMock, $case['checkoutType']);

		$this->assertEquals($case['expect'], $result);
	}

	/**
	 * Data provider for {@see getBoltOrderToken_withIncompleteQuote_returnsJSONError}
	 *
	 * @return array containing
	 * 1. expected result of the method call
	 * 2. checkout type to be provided
	 * 3. value to stub {@see Mage_Sales_Model_Quote::getAllVisibleItems}
	 * 4. current quote shipping method
	 * 5. admin active method rate
	 * 6. is quote virtual flag
	 * 7. stubbed result of {@see Bolt_Boltpay_Model_BoltOrder::validateVirtualQuote}
	 * 8. is admin flag
	 */
	public function getBoltOrderToken_withIncompleteQuoteProvider()
	{
		$orderRequestData = self::$orderRequest;
		return array(
			array(
				'case' => array(
					'expect' => json_decode(
						'{"token" : "", "error": "Your shopping cart is empty. Please add products to the cart."}'
					),
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
					'expect' => json_decode(
						'{"token" : "", "error": "A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again."}'
					),
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
					'expect' => json_decode(
						'{"token" : "", "error": "Billing address is required."}'
					),
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

	/**
	 * @test
	 * that getBoltOrderTokenPromise returns javascript promise containing expected checkoutTokenUrl and parameters
	 *
	 * @covers ::getBoltOrderTokenPromise
	 *
	 * @dataProvider getBoltOrderTokenPromise_withVariousCheckoutTypesProvider
	 *
	 * @param string $checkoutType currently in use
	 * @param array $checkoutTokenUrlParams provided to {@see Bolt_Boltpay_Helper_UrlTrait::getMagentoUrl}
	 *                                       for getting checkout token URL
	 * @param string $parameters expected AJAX parameters inside promise
	 */
	public function getBoltOrderTokenPromise_withVariousCheckoutTypes_returnsTokenPromise($checkoutType, $checkoutTokenUrlParams, $parameters)
	{
		$result = $this->currentMock->getBoltOrderTokenPromise($checkoutType);
		$checkoutTokenUrl = call_user_func_array(
			array($this->boltHelperMock, 'getMagentoUrl'),
			$checkoutTokenUrlParams
		);
		$expectedResult = <<<PROMISE
                    new Promise( 
                        function (resolve, reject) {
                            new Ajax.Request('$checkoutTokenUrl', {
                                method:'post',
                                parameters: $parameters,
                                onSuccess: function(response) {
                                    if(response.responseJSON.error) {                                                        
                                        reject(response.responseJSON.error_messages);
                                        
                                        // BoltCheckout is currently not doing anything reasonable to alert the user of a problem, so we will do something as a backup
                                        alert(response.responseJSON.error_messages);
                                        location.reload();
                                    } else {                                     
                                        resolve(response.responseJSON.cart_data);
                                    }                   
                                },
                                 onFailure: function(error) { reject(error); }
                            });                            
                        }
                    )
PROMISE;
		$this->assertEquals(
			preg_replace('/\s+/', '', $expectedResult),
			preg_replace('/\s+/', '', $result)
		);
	}

	/**
	 * Data provider for {@see getBoltOrderTokenPromise_withVariousCheckoutTypes_returnsTokenPromise}
	 *
	 * @return array containing checkout type, checkout token url parameters and expected AJAX parameters
	 */
	public function getBoltOrderTokenPromise_withVariousCheckoutTypesProvider()
	{
		return array(
			'Admin checkout' => array(
				'checkoutType' => $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
				'checkoutTokenUrlParams' => array(
					'route' => "adminhtml/sales_order_create/create/checkoutType/$checkoutType",
					'params' => array(),
					'isAdmin' => true
				),
				'parameters' => "''"
			),
			'Firecheckout' => array(
				'checkoutType' => $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT,
				'checkoutTokenUrlParams' => array(
					'route' => 'boltpay/order/firecheckoutcreate'
				),
				'parameters' => "checkout.getFormData ? checkout.getFormData() : Form.serialize(checkout.form, true)"
			),
			'Multi-page checkout' => array(
				'checkoutType' => $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
				'checkoutTokenUrlParams' => array(
					'route' => "boltpay/order/create/checkoutType/$checkoutType"
				),
				'parameters' => "''"
			),
			'One-page checkout' => array(
				'checkoutType' => $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE,
				'checkoutTokenUrlParams' => array(
					'route' => "boltpay/order/create/checkoutType/$checkoutType"
				),
				'parameters' => "''"
			),
			'Product page checkout' => array(
				'checkoutType' => $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE,
				'checkoutTokenUrlParams' => array(
					'route' => "boltpay/order/create/checkoutType/$checkoutType"
				),
				'parameters' => "''"
			)
		);
	}

    /**
     * @test
     * that cloneQuote returns a clone of the provided quote
     *
     * @covers ::cloneQuote
     *
     * @dataProvider checkoutTypesProvider
     *
     * @param string $checkoutType currently in use
     *
     * @throws Exception if unable to clone quote
     */
    public function cloneQuote_withProvidedQuoteInVariousCheckoutTypes_returnsClonedQuote($checkoutType)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote', array('test_field' => 'test_value'));
        $quote->addProduct($product, 5);
        $quote->getBillingAddress()->addData(self::$defaultAddressData);
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate')
            ->addData(self::$defaultAddressData);
        $quote->collectTotals();

        $clonedQuote = $this->currentMock->cloneQuote($quote, $checkoutType);

        $this->assertNotEquals($quote->getId(), $clonedQuote->getId());
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $quote->getItemByProduct($product);
        $clonedQuoteItem = $clonedQuote->getItemByProduct($product);
        $this->assertTrue($quoteItem->compare($clonedQuoteItem));
        $this->assertEquals($quoteItem->getQty(), $clonedQuoteItem->getQty());
        foreach (array('SubtotalWithDiscount', 'Subtotal', 'GrandTotal', 'CurrencyCode') as $total) {
            $getterMethodName = 'get' . $total;
            $baseGetterMethodName = 'getBase' . $total;
            $this->assertEquals($quote->$getterMethodName(), $clonedQuote->$getterMethodName());
            $this->assertEquals($quote->$baseGetterMethodName(), $clonedQuote->$baseGetterMethodName());
        }

        $this->assertEquals($quote->getReservedOrderId(), $clonedQuote->getReservedOrderId());
        $this->assertEquals($quote->getStoreId(), $clonedQuote->getStoreId());
        $this->assertEquals($quote->getId(), $clonedQuote->getParentQuoteId());
        $this->assertEquals(
            $quote->getReservedOrderId(),
            Mage::getSingleton('core/session')->getReservedOrderId()
        );

        $clonedQuoteShippingAddress = $clonedQuote->getShippingAddress();
        $clonedQuoteBillingAddress = $clonedQuote->getBillingAddress();
        if ($checkoutType != Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE) {
            $this->assertArraySubset(self::$defaultAddressData, $clonedQuoteShippingAddress->getData());
            $this->assertArraySubset(self::$defaultAddressData, $clonedQuoteBillingAddress->getData());
            $this->assertEquals('flatrate_flatrate', $clonedQuoteShippingAddress->getShippingMethod());
        } else {
            $this->assertNull($clonedQuoteShippingAddress->getShippingMethod());
        }

        if ($checkoutType == Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) {
            //verify totals collected
            $this->assertAttributeNotEmpty('_totalAmounts', $clonedQuoteShippingAddress);
            //verify shipping rates collected
            $this->assertFalse($clonedQuoteShippingAddress->getCollectShippingRates());
        } else {
            //verify totals not collected
            $this->assertAttributeEmpty('_totalAmounts', $clonedQuoteShippingAddress);
            //verify shipping rates not collected
            $this->assertNull($clonedQuoteShippingAddress->getCollectShippingRates());
        }

        $this->assertFalse($clonedQuote->getIsActive());

        $quote->delete();
        $clonedQuote->delete();
    }

	/**
	 * @test
	 * that cloneQuote continues processing if {@see Mage_Sales_Model_Quote::merge} throws an exception
	 *
	 * @covers ::cloneQuote
	 *
	 * @throws Mage_Core_Exception if unable to stub quote singleton
	 * @throws Exception if unable to clone or delete quote
	 */
	public function cloneQuote_whenQuoteMergeThrowsException_notifiesExceptionAndContinuesCloning()
	{
		/** @var Mage_Sales_Model_Quote $clonedQuote */
		$clonedQuote = Mage::getModel('sales/quote');
		/** @var Mage_Sales_Model_Quote $quote */
		$quote = Mage::getModel('sales/quote');
		/** @var MockObject|Mage_Sales_Model_Quote $clonedQuoteMock */
		$clonedQuoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote', false)
			->enableProxyingToOriginalMethods()
			->setProxyTarget($clonedQuote)
			->getMock();
		TestHelper::stubSingleton('sales/quote', $clonedQuoteMock);
		$exception = new Exception('Quote merge failed');
		$clonedQuoteMock->expects($this->once())->method('merge')->willThrowException($exception);
		$this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
		$this->currentMock->cloneQuote($quote);

		$this->assertNotEquals($quote->getId(), $clonedQuote->getId());
		$this->assertEquals(
			$quote->getReservedOrderId(),
			$clonedQuote->getReservedOrderId()
		);
		$this->assertEquals(
			$quote->getReservedOrderId(),
			Mage::getSingleton('core/session')->getReservedOrderId()
		);

		$this->assertFalse($clonedQuote->getIsActive());

		$quote->delete();
		$clonedQuote->delete();
	}

	/**
	 * @test
	 * that getItemProperties returns options selected by the customer e.g. color and size
	 *
	 * @covers ::getItemProperties
	 *
	 * @throws ReflectionException if getItemProperties method is not defined
	 */
	public function getItemProperties_always_returnSelectedItemProperties()
	{
		$quoteItem = Mage::getModel(
			'sales/quote_item',
			array(
				'product_order_options' => self::$dummyItemProductOptions
			)
		);
		$this->assertArraySubset(
			array(
				array(
					'name' => 'Color',
					'value' => 'red',
				),
				array(
					'name' => 'Color',
					'value' => 'red',
				),
				array(
					'name' => 'Test Label',
					'value' => 'Test Value'
				),
				array(
					'name' => 'Test',
				),
			),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getItemProperties',
				array(
					$quoteItem
				)
			)
		);
	}

	/**
	 * @test
	 * that getProductOptions takes options from quote item property,
	 * filters them against {@see \Bolt_Boltpay_Model_BoltOrder::$itemOptionKeys}
	 * and returns them
	 *
	 * @covers ::getProductOptions
	 *
	 * @throws ReflectionException if getProductOptions method is not defined
	 */
	public function getProductOptions_withItemContainingProductOrderOptions_returnsProductOptions()
	{
		$quoteItem = Mage::getModel(
			'sales/quote_item',
			array(
				'product_order_options' => self::$dummyItemProductOptions
			)
		);
		$this->assertEquals(
			array(
				array(
					'label' => 'Color',
					'value' => 'red',
				),
				array(
					'label' => 'Color',
					'value' => 'red',
					'print_value' => 'red',
					'option_id' => '1',
					'option_type' => 'drop_down',
					'option_value' => '2',
					'custom_view' => false,
				),
				array(
					'value' => 'Test Value',
					'label' => 'Test Label',
				),
				array(
					'option_id' => '1',
					'label' => 'Test',
					'value' =>
						array(
							array(
								'title' => '123',
								'qty' => 1,
								'price' => 156,
							),
						),
				),
			),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getProductOptions',
				array(
					$quoteItem
				)
			)
		);
	}

	/**
	 * @test
	 * that getProductOptions retrieves product options using product type instance if they are missing in quote item
	 *
	 * @covers ::getProductOptions
	 *
	 * @throws ReflectionException if getProductOptions method is not defined
	 */
	public function getProductOptions_withoutProductOptionsInItem_returnsOrderOptionsFromProductInstance()
	{
		$quoteItem = $this->getClassPrototype('Mage_Sales_Model_Quote_Item')
			->setMethods(array('getProduct', 'getTypeInstance', 'getOrderOptions'))
			->getMock();
		$quoteItem->method('getProduct')->willReturnSelf();
		$quoteItem->expects($this->once())->method('getTypeInstance')->willReturnSelf();
		$quoteItem->expects($this->once())->method('getOrderOptions')->willReturn(self::$dummyItemProductOptions);
		$this->assertEquals(
			array(
				array(
					'label' => 'Color',
					'value' => 'red',
				),
				array(
					'label' => 'Color',
					'value' => 'red',
					'print_value' => 'red',
					'option_id' => '1',
					'option_type' => 'drop_down',
					'option_value' => '2',
					'custom_view' => false,
				),
				array(
					'value' => 'Test Value',
					'label' => 'Test Label',
				),
				array(
					'option_id' => '1',
					'label' => 'Test',
					'value' =>
						array(
							array(
								'title' => '123',
								'qty' => 1,
								'price' => 156,
							),
						),
				),
			),
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getProductOptions',
				array(
					$quoteItem
				)
			)
		);
	}

	/**
	 * @test
	 * that getOptionValue returns value property of provided option if not array
	 *
	 * @covers ::getOptionValue
	 *
	 * @throws ReflectionException if getOptionValue method is not defined
	 */
	public function getOptionValue_withNonArrayOptionValue_returnsOptionValue()
	{
		$dummyOption = array(
			'value' => 'Dummy Option Value'
		);
		$this->assertEquals(
			'Dummy Option Value',
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getOptionValue',
				array(
					$dummyOption
				)
			)
		);
	}

	/**
	 * @test
	 * that getOptionValue forwards the call to {@see Bolt_Boltpay_Model_BoltOrder::getBundleProductOptionValue}
	 *
	 * @covers ::getOptionValue
	 *
	 * @throws ReflectionException if getOptionValue method is not defined
	 */
	public function getOptionValue_withArrayOptionValue_returnsBundleProductOptionValue()
	{
		$this->currentMock = $this->getTestClassPrototype()->setMethods(array('getBundleProductOptionValue'))
			->getMock();

		$dummyOption = array(
			'value' => array(
				array('qty' => 1, 'price' => 12.34, 'title' => 'Bundle Option 1'),
				array('qty' => 2, 'price' => 56.78, 'title' => 'Bundle Option 2')
			)
		);

		$this->currentMock->expects($this->once())->method('getBundleProductOptionValue')
			->with($dummyOption);

		TestHelper::callNonPublicFunction(
			$this->currentMock,
			'getOptionValue',
			array(
				$dummyOption
			)
		);
	}

	/**
	 * @test
	 * that getBundleProductOptionValue returns expected option value string from provided option
	 *
	 * @covers ::getBundleProductOptionValue
	 *
	 * @throws ReflectionException if getBundleProductOptionValue method is not defined
	 */
	public function getBundleProductOptionValue_withDummyProductOption_returnsBundleProductSelectionString()
	{
		$dummyOption = array(
			'value' => array(
				array('qty' => 1, 'price' => 12.34, 'title' => 'Bundle Option 1'),
				array('qty' => 2, 'price' => 56.78, 'title' => 'Bundle Option 2')
			)
		);
		$this->assertRegExp( # Supports American and European locale
			'/1 x Bundle Option 1 \$?12(\.|,)34(.+\$)?, 2 x Bundle Option 2 \$?56(\.|,)78(.+\$)?/',
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'getBundleProductOptionValue',
				array(
					$dummyOption
				)
			)
		);
	}

	/**
	 * @test
	 * that validateVirtualQuote returns true if provided with non-virtual quote
	 *
	 * @covers ::validateVirtualQuote
	 *
	 * @throws ReflectionException if validateVirtualQuote method is not defined
	 */
	public function validateVirtualQuote_withNonVirtualQuote_returnsTrue()
	{
		$quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')->setMethods(array('isVirtual'))->getMock();
		$quoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
		$this->assertTrue(
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'validateVirtualQuote',
				array(
					$quoteMock
				)
			)
		);
	}

	/**
	 * @test
	 * that validateVirtualQuote returns false if billing address has insufficient data
	 *
	 * @covers ::validateVirtualQuote
	 *
	 * @dataProvider validateVirtualQuote_withIncompleteBillingAddressProvider
	 *
	 * @param array $billingAddressData to be assigned to quote billing address
	 *
	 * @throws ReflectionException if validateVirtualQuote method is not defined
	 */
	public function validateVirtualQuote_withIncompleteBillingAddress_returnsFalse($billingAddressData)
	{
		$quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
			->setMethods(array('isVirtual', 'getBillingAddress'))->getMock();
		$quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);
		$quoteMock->expects($this->once())->method('getBillingAddress')
			->willReturn(Mage::getModel('sales/quote_address', $billingAddressData));
		$this->assertFalse(
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'validateVirtualQuote',
				array(
					$quoteMock
				)
			)
		);
	}

	/**
	 * Data provider for {@see validateVirtualQuote_withIncompleteBillingAddress_returnsFalse}
	 *
	 * @return array containing incomplete billing address data
	 */
	public function validateVirtualQuote_withIncompleteBillingAddressProvider()
	{
		return array(
			'Address missing last name' => array(
				'billingAddressData' => array(
					'street' => 'Sample Street 10',
					'city' => 'Los Angeles',
					'postcode' => '90014',
					'telephone' => '+1 867 345 123 5681',
					'country_id' => 'US'
				)
			),
			'Address missing street' => array(
				'billingAddressData' => array(
					'lastname' => 'Skywalker',
					'city' => 'Los Angeles',
					'postcode' => '90014',
					'telephone' => '+1 867 345 123 5681',
					'country_id' => 'US'
				)
			),
			'Address missing city' => array(
				'billingAddressData' => array(
					'lastname' => 'Skywalker',
					'street' => 'Sample Street 10',
					'postcode' => '90014',
					'telephone' => '+1 867 345 123 5681',
					'country_id' => 'US'
				)
			),
			'Address missing post code' => array(
				'billingAddressData' => array(
					'lastname' => 'Skywalker',
					'street' => 'Sample Street 10',
					'city' => 'Los Angeles',
					'telephone' => '+1 867 345 123 5681',
					'country_id' => 'US'
				)
			),
			'Address missing telephone' => array(
				'billingAddressData' => array(
					'lastname' => 'Skywalker',
					'street' => 'Sample Street 10',
					'city' => 'Los Angeles',
					'postcode' => '90014',
					'country_id' => 'US'
				)
			),
			'Address missing country id' => array(
				'billingAddressData' => array(
					'lastname' => 'Skywalker',
					'street' => 'Sample Street 10',
					'city' => 'Los Angeles',
					'postcode' => '90014',
					'telephone' => '+1 867 345 123 5681',
				)
			),
		);
	}

	/**
	 * @test
	 * that validateVirtualQuote returns true when provided quote is virtual and its billing address has sufficient data
	 *
	 * @covers ::validateVirtualQuote
	 *
	 * @throws ReflectionException if validateVirtualQuote method is not defined
	 */
	public function validateVirtualQuote_whenQuoteIsValid_returnsTrue()
	{
		$quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
			->setMethods(array('isVirtual', 'getBillingAddress'))->getMock();
		$quoteMock->expects($this->once())->method('isVirtual')->willReturn(true);
		$quoteMock->expects($this->once())->method('getBillingAddress')
			->willReturn(
				Mage::getModel(
					'sales/quote_address',
					array(
						'lastname' => 'Skywalker',
						'street' => 'Sample Street 10',
						'city' => 'Los Angeles',
						'postcode' => '90014',
						'telephone' => '+1 867 345 123 5681',
						'country_id' => 'US'
					)
				)
			);
		$this->assertTrue(
			TestHelper::callNonPublicFunction(
				$this->currentMock,
				'validateVirtualQuote',
				array(
					$quoteMock
				)
			)
		);
	}

	/**
	 * @test
	 * that dispatchCartDataEvent dispatches Magento event with provided cart submission data and quote
	 *
	 * @covers ::dispatchCartDataEvent
	 *
	 * @throws ReflectionException if unable to replace {@see Mage::$_app} or dispatchCartDataEvent method is not defined
	 */
	public function dispatchCartDataEvent_withProvidedCartDataAndQuote_dispatchesEvent()
	{
		$previousApp = Mage::app();
		$appMock = $this->getClassPrototype('Mage_Core_Model_App')
			->setMethods(array('dispatchEvent'))
			->getMock();
		$eventName = 'test';
		$quote = Mage::getModel('sales/quote');
		$cartSubmissionData = self::$orderRequest['cart'];
		$alternativeSubmissionData = array('test' => 'test');
		$appMock->expects($this->once())->method('dispatchEvent')
			->willReturnCallback(
				function ($name, $data) use ($alternativeSubmissionData) {
					//simulate value change from event observer
					$cartDataWrapper = $data['cart_data_wrapper'];
					$cartDataWrapper->setCartData(
						$alternativeSubmissionData
					);
				}
			);
		TestHelper::setNonPublicProperty('Mage', '_app', $appMock);

		TestHelper::callNonPublicFunction(
			$this->currentMock,
			'dispatchCartDataEvent',
			array(
				$eventName,
				$quote,
				&$cartSubmissionData
			)
		);
		$this->assertSame($alternativeSubmissionData, $cartSubmissionData);

		TestHelper::setNonPublicProperty('Mage', '_app', $previousApp);
	}

	/**
	 * Provides all checkout types
	 *
	 * @return array containing checkout type
	 */
	public function checkoutTypesProvider()
	{
		return array(
			array('checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN),
			array('checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE),
			array('checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE),
			array('checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE),
			array('checkoutType' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT),
		);
	}

	/**
	 * @test
	 * @throws ReflectionException
	 */
	public function addShippingForAdmin_ifQuoteIsVirtual()
	{
		$quoteMock = $this->getMockBuilder(Mage_Sales_Model_Quote::class)
			->setMethods(array('isVirtual'))
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->getMock();

		$quoteMock->method('isVirtual')
			->willReturn(1);

		$cartSubmissionData = array();
		$result = TestHelper::callNonPublicFunction(
			$this->currentMock,
			'addShippingForAdmin',
			array(
				null,
				&$cartSubmissionData,
				null,
				null,
				$quoteMock
			)
		);

		$this->assertEquals(
			$cartSubmissionData,
			array('shipments' => array(array(
				'tax_amount' => 0,
				'service' => 'No Shipping Required',
				'reference' => 'noshipping',
				'cost' => 0
			)))
		);
		$this->assertEquals(0, $result);
	}
}