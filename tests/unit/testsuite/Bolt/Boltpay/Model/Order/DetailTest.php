<?php

require_once('OrderHelper.php');
require_once('TestHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Order_Detail
 */
class Bolt_Boltpay_Model_Order_DetailTest extends PHPUnit_Framework_TestCase
{
    /** @var string Dummy transaction reference */
    private static $reference;

    /** @var Mage_Sales_Model_Order|null Dummy order */
    private static $order;

    /** @var int Dummy product id */
    private static $productId;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data
     */
    private $boltHelperMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Order_Detail
     */
    private $currentMock;

    /**
     * Setup test dependencies common to each test
     */
    public static function setUpBeforeClass()
    {
        self::$reference = uniqid('TRNX_');
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'), array(), 20);
        self::$order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        self::$order->getPayment()->setLastTransId(self::$reference)->save();
    }

    /**
     * Configure test dependencies
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order_Detail')
            ->setMethods()
            ->getMock();

        $this->boltHelperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('fetchTransaction', 'logException', 'getItemImageUrl'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        self::$order->getPayment()->setMethod('boltpay');
    }

    /**
     * Restore stubbed values
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * Delete dummy order
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_OrderHelper::deleteDummyOrder(self::$order);
    }

    /**
     * @test
     * initializing using Bolt transaction id loads order from the database using order payment
     *
     * @covers ::init
     * @covers ::initWithPayment
     *
     * @throws Exception if unable to initialize
     */
    public function init_withValidPaymentReference_loadsOrderFromDatabase()
    {
        $this->currentMock->init(self::$reference);

        /** @var Mage_Sales_Model_Order $paymentOrder */
        $paymentOrder = Bolt_Boltpay_TestHelper::getNonPublicProperty(
            $this->currentMock,
            'order'
        );
        $this->assertNotNull($paymentOrder);
        $this->assertEquals(
            self::$order->getId(),
            $paymentOrder->getId()
        );
    }

    /**
     * @test
     * initializing using Bolt transaction id loads order from the database using order increment id
     *
     * @covers ::init
     * @covers ::initByReference
     *
     * @throws Exception if unable to initialize
     */
    public function init_withoutExistingOrderPayment_loadsOrderBasedOnTransaction()
    {
        $reference = uniqid();
        $transaction = json_decode(
            json_encode(array('order' => array('cart' => array('display_id' => self::$order->getIncrementId()))))
        );
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with($reference)
            ->willReturn($transaction);
        $this->currentMock->init($reference);
        /** @var Mage_Sales_Model_Order $paymentOrder */
        $paymentOrder = Bolt_Boltpay_TestHelper::getNonPublicProperty(
            $this->currentMock,
            'order'
        );
        $this->assertNotNull($paymentOrder);
        $this->assertEquals(
            self::$order->getId(),
            $paymentOrder->getId()
        );
    }

    /**
     * @test
     * that init throws exception if unable to initialize
     *
     * @covers ::init
     * @covers ::initWithPayment
     * @covers ::initByReference
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage No payment found
     */
    public function init_withoutExistingPaymentAndOrder_throwsException()
    {
        $reference = uniqid();
        $transaction = json_decode(
            json_encode(array('order' => array('cart' => array('display_id' => null))))
        );
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with($reference)
            ->willReturn($transaction);
        $this->currentMock->init($reference);
    }

    /**
     * @test
     * that init payment method throws exception when order related to payment doesn't exist
     *
     * @covers ::initWithPayment
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessageRegExp /No order found with ID of -8547/
     */
    public function initWithPayment_withOrderMissingForPayment_throwsException()
    {
        $payment = Mage::getModel('sales/order_payment')->setId(-9998 )->setData('parent_id', -8547 );
        $this->currentMock->initWithPayment($payment);
    }

    /**
     * @test
     * that generateOrderDetail will generate order details array if provided with valid order
     *
     * @covers ::generateOrderDetail
     * @covers ::validateOrderDetail
     * @covers ::addOrderDetails
     * @covers ::addOrderReference
     * @covers ::addDisplayId
     * @covers ::addCurrency
     * @covers ::addItemDetails
     * @covers ::addTotals
     * @covers ::addTotalAmount
     * @covers ::addTaxAmount
     *
     * @throws ReflectionException if class doesn't have order property
     * @throws Mage_Core_Exception if order validation fails
     */
    public function generateOrderDetail_forValidOrder_returnsOrderDetailsArray()
    {
        $this->setCurrentOrder(self::$order);
        $generatedData = $this->currentMock->generateOrderDetail();
        $this->assertEquals(self::$order->getQuoteId(), $generatedData['order_reference']);
        $this->assertEquals(self::$order->getIncrementId(), $generatedData['display_id']);
        $this->assertEquals(self::$order->getOrderCurrencyCode(), $generatedData['currency']);
        $this->assertEquals((int)round(self::$order->getGrandTotal() * 100), $generatedData['total_amount']);
        $this->assertEquals((int)round(self::$order->getTaxAmount() * 100), $generatedData['tax_amount']);
    }

    /**
     * @test
     * that generateOrderDetails doesn't generate order details if validation fails, returns old data instead
     *
     * @covers ::generateOrderDetail
     *
     * @throws ReflectionException if class doesn't have generatedData property
     * @throws Mage_Core_Exception if order details validation fails
     */
    public function generateOrderDetail_whenOrderDetailValidationReturnsFalse_willReturnExistingDataFromProperty()
    {
        /** @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Order_Detail $currentMock */
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order_Detail')
            ->setMethods(array('validateOrderDetail', 'addOrderDetails', 'addItemDetails', 'addTotals'))
            ->getMock();

        $currentMock->expects($this->once())->method('validateOrderDetail')->willReturn(false);
        $currentMock->expects($this->never())->method('addOrderDetails');
        $currentMock->expects($this->never())->method('addItemDetails');
        $currentMock->expects($this->never())->method('addTotals');

        $generatedData = array('order_reference' => 1);
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $currentMock,
            'generatedData',
            $generatedData
        );
        $this->assertEquals($generatedData, $currentMock->generateOrderDetail());
    }

    /**
     * Setup dependencies for testing {@see Bolt_Boltpay_Model_Order_Detail::validateOrderDetail}}
     * Configures current order with provided id and payment method
     *
     * @param int|null    $id to set in order
     * @param string|null $paymentMethod to set in order
     * @throws ReflectionException if unable to set current order
     */
    protected function validateOrderDetailSetUp($id = null, $paymentMethod = null)
    {
        $order = Mage::getModel('sales/order');
        $order->setId($id)->setPayment(Mage::getModel('sales/order_payment')->setMethod($paymentMethod));
        $this->setCurrentOrder($order);
    }

    /**
     * @test
     * that validateOrderDetail will throw exception if order payment method is not boltpay
     *
     * @covers ::validateOrderDetail
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Payment method is not 'boltpay'
     *
     * @throws ReflectionException from setup if unable to set order
     */
    public function validateOrderDetail_whenOrderPaymentMethodIsNotBoltpay_throwsException()
    {
        self::$order->getPayment()->setMethod('checkmo');
        $this->setCurrentOrder(self::$order);

        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateOrderDetail'
        );
    }

    /**
     * @test
     * that validateOrderDetail will throw exception if order doesn't have id
     *
     * @covers ::validateOrderDetail
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage No order found
     *
     * @throws ReflectionException from setup if unable to set order
     */
    public function validateOrderDetail_whenOrderHasNoId_throwsException()
    {
        $this->validateOrderDetailSetUp(null, 'boltpay');
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateOrderDetail'
        );
    }

    /**
     * Setup dependencies for testing {@see Bolt_Boltpay_Model_Order_Detail::getGeneratedDiscounts}}
     * Sets current order with discount description and amount
     *
     * @param int    $discountAmount
     * @param string $discountDescription
     * @throws ReflectionException if unable to set order
     */
    private function getGeneratedDiscountsSetUp($discountAmount = 0, $discountDescription = '')
    {
        $order = Mage::getModel('sales/order');
        $order->setDiscountAmount($discountAmount)->setDiscountDescription($discountDescription);
        $this->setCurrentOrder($order);
    }

    /**
     * @test
     * that getGeneratedDiscounts returns discount data if order has discount
     *
     * @covers ::getGeneratedDiscounts
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedDiscounts_whenOrderHasDiscount_returnsDiscountData()
    {
        $this->getGeneratedDiscountsSetUp(100, 'Test discount');
        $this->assertEquals(
            array(
                array(
                    'amount'      => 10000,
                    'description' => 'Test discount',
                    'type'        => 'fixed_amount',
                )
            ),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedDiscounts'
            )
        );
    }

    /**
     * @test
     * that getGeneratedDiscounts returns empty array if order discount is 0
     *
     * @covers ::getGeneratedDiscounts
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedDiscounts_whenOrderDoesntHaveDiscount_returnsEmptyArray()
    {
        $this->getGeneratedDiscountsSetUp(0);
        $this->assertEmpty(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedDiscounts'
            )
        );
    }

    /**
     * @test
     * that addBillingAddress calls getGeneratedBillingAddress and adds its output to generatedData array
     *
     * @covers ::addBillingAddress
     *
     * @throws ReflectionException if class doesn't have addBillingAddress method or generatedData property
     */
    public function addBillingAddress_withNewInstance_generatesBillingAddressAndAddsToGeneratedData()
    {
        $dummyBillingAddressData = array(
            'street_address1' => 'Test Street',
            'first_name'      => 'Test',
            'last_name'       => 'Test',
            'locality'        => 'Test City',
            'region'          => 'Test Region',
            'postal_code'     => '11000',
            'country_code'    => 'US'
        );
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order_Detail')
            ->setMethods(array('getGeneratedBillingAddress'))
            ->getMock();
        $currentMock->expects($this->once())->method('getGeneratedBillingAddress')
            ->willReturn($dummyBillingAddressData);
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'addBillingAddress');
        $generatedData = Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'generatedData');

        $this->assertArrayHasKey('billing_address', $generatedData);
        $this->assertEquals($dummyBillingAddressData, $generatedData['billing_address']);
    }

    /**
     * @test
     * that addShipments calls getGeneratedShipments and adds its output to generatedData array
     *
     * @covers ::addShipments
     *
     * @throws ReflectionException if class doesn't have addShipments method or generatedData property
     */
    public function addShipments_withNewInstance_generatesShipmentsAndAddsToGeneratedData()
    {
        $dummyShipmentsData = array(
            array(
                'shipping_address' => array(),
                'tax_amount'       => 0,
                'service'          => 'flatrate',
                'carrier'          => 'flatrate',
                'reference'        => 'flatrate',
                'cost'             => (int)1000,
            )
        );
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order_Detail')
            ->setMethods(array('getGeneratedShipments'))
            ->getMock();
        $currentMock->expects($this->once())->method('getGeneratedShipments')
            ->willReturn($dummyShipmentsData);
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'addShipments');
        $generatedData = Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'generatedData');

        $this->assertArrayHasKey('shipments', $generatedData);
        $this->assertEquals($dummyShipmentsData, $generatedData['shipments']);
    }

    /**
     * @test
     * that addDiscounts calls getGeneratedDiscounts and adds its output to generatedData array
     *
     * @covers ::addDiscounts
     *
     * @throws ReflectionException if class doesn't have addDiscounts method or generatedData property
     */
    public function addDiscounts_withNewInstance_generatesDiscountsAndAddsToGeneratedData()
    {
        $dummyDiscountsData = array(
            array(
                'amount'      => 1000,
                'description' => 'Discount',
                'type'        => 'fixed_amount',
            )
        );
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order_Detail')
            ->setMethods(array('getGeneratedDiscounts'))
            ->getMock();
        $currentMock->expects($this->once())->method('getGeneratedDiscounts')
            ->willReturn($dummyDiscountsData);
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'addDiscounts');
        $generatedData = Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'generatedData');

        $this->assertArrayHasKey('discounts', $generatedData);
        $this->assertEquals($dummyDiscountsData, $generatedData['discounts']);
    }

    /**
     * Setup dependencies for testing {@see Bolt_Boltpay_Model_Order_Detail::getGeneratedBillingAddress}}
     * Sets current order with provided address as billing address
     *
     * @param Mage_Sales_Model_Order_Address|null $address
     * @throws ReflectionException if unable to set order
     */
    private function getGeneratedBillingAddressSetUp($address = null)
    {
        $order = Mage::getModel('sales/order');
        if ($address !== null) {
            $order->setBillingAddress($address);
        }

        $this->setCurrentOrder($order);
    }

    /**
     * @test
     * that getGeneratedBillingAddress returns address data array if all required fields are present
     *
     * @covers ::getGeneratedBillingAddress
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedBillingAddress_withAllRequiredAddressFields_returnsAddressDataArray()
    {
        $address = Mage::getModel('sales/order_address')
            ->setFirstname('Test')
            ->setLastname('Test')
            ->setRegion('Test Region')
            ->setCountryId('US')
            ->setPostcode('11000')
            ->setStreet('Test Street')
            ->setCity('Test City');
        $this->getGeneratedBillingAddressSetUp($address);
        $this->assertArraySubset(
            array(
                'street_address1' => 'Test Street',
                'first_name'      => 'Test',
                'last_name'       => 'Test',
                'locality'        => 'Test City',
                'region'          => 'Test Region',
                'postal_code'     => '11000',
                'country_code'    => 'US'
            ),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedBillingAddress'
            )
        );
    }

    /**
     * @test
     * that getGeneratedBillingAddress returns false when order doesn't have billing address
     *
     * @covers ::getGeneratedBillingAddress
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedBillingAddress_withoutBillingAddress_returnsFalse()
    {
        $this->getGeneratedBillingAddressSetUp();
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedBillingAddress'
            )
        );
    }

    /**
     * @test
     * that getGeneratedBillingAddress returns false when order address is missing one of the required fields
     *
     * @covers ::getGeneratedBillingAddress
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedBillingAddress_withBillingAddressMissingRequiredFields_returnsFalse()
    {
        $address = Mage::getModel('sales/order_address');
        $this->getGeneratedBillingAddressSetUp($address);
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedBillingAddress'
            )
        );
    }

    /**
     * Setup dependencies for testing {@see Bolt_Boltpay_Model_Order_Detail::getGeneratedShipments}}
     * Sets current order with provided address as shipping address
     *
     * @param Mage_Sales_Model_Order_Address|null $address to be used as shipping address
     * @param string                              $method to be set as shipping method
     * @param string                              $description of the shipping method
     * @param int                                 $cost of the shipping method
     * @param int                                 $tax amount of the shipping method
     * @throws ReflectionException
     */
    private function getGeneratedShipmentsSetUp($address = null, $method = '', $description = '', $cost = 0, $tax = 0)
    {
        $order = Mage::getModel('sales/order');
        if ($address !== null) {
            $order->setShippingAddress($address);
        }
        $order->setShippingMethod($method)->setShippingDescription($description)->setShippingAmount($cost)
            ->setShippingTaxAmount($tax);

        $this->setCurrentOrder($order);
    }

    /**
     * @test
     * that getGeneratedShipments returns false when order doesn't have shipping address
     *
     * @covers ::getGeneratedShipments
     *
     * @throws ReflectionException if unable to set order
     */
    public function getGeneratedShipments_shippingAddressNotSet_returnsFalse()
    {
        $this->getGeneratedShipmentsSetUp();
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getGeneratedShipments'
            )
        );
    }

    /**
     * @test
     * that getGeneratedShipments for shipping addresses outside of USA and CA reads region from city if not set
     *
     * @covers ::getGeneratedShipments
     *
     * @throws ReflectionException if the model doesn't have getGeneratedShipments method
     */
    public function getGeneratedShipments_shippingAddressNotFromNAandHasNoRegion_readsRegionFromCity()
    {
        $address = Mage::getModel('sales/order_address');
        $city = 'Mexico City';
        $address->setRegion(null)->setCountryId('MX')->setCity($city);
        $this->getGeneratedShipmentsSetUp($address);
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getGeneratedShipments'
        );
        $this->assertEquals(
            $city,
            $result[0]['shipping_address']['region']
        );
    }

    /**
     * @test
     * that getGeneratedShipments returns expected shipment data array
     *
     * @covers ::getGeneratedShipments
     *
     * @throws ReflectionException if the model doesn't have getGeneratedShipments method
     */
    public function getGeneratedShipments_withValidShippingMethodAndAddress_returnsShipmentDataArray()
    {
        $address = Mage::getModel('sales/order_address');

        $shippingMethod = 'flatrate_flatrate';
        $shippingDescription = 'Flat Rate';
        $shippingCost = 10;
        $shippingTaxAmount = 2;
        $this->getGeneratedShipmentsSetUp(
            $address,
            $shippingMethod,
            $shippingDescription,
            $shippingCost,
            $shippingTaxAmount
        );
        $generatedShipments = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getGeneratedShipments'
        );
        $this->assertCount(1, $generatedShipments);
        $generatedShipment = reset($generatedShipments);
        $this->assertArraySubset(
            array(
                'tax_amount' => $shippingTaxAmount * 100,
                'service'    => $shippingDescription,
                'carrier'    => $shippingMethod,
                'reference'  => $shippingMethod,
                'cost'       => $shippingCost * 100
            ),
            $generatedShipment
        );
    }

    /**
     * Setup dependencies for testing {@see Bolt_Boltpay_Model_Order_Detail::getGeneratedItems}}
     * Sets _items property for current order to stub result of {{@see Mage_Sales_Model_Order::getAllVisibleItems}}
     *
     * @param array $items to return on getAllVisibleItems call
     * @param null  $quoteId to set in order
     * @throws ReflectionException if order doesn't have _items property
     */
    private function getGeneratedItemsSetUp($items, $quoteId = null)
    {
        $order = Mage::getModel('sales/order');
        $order->setQuoteId($quoteId);
        Bolt_Boltpay_TestHelper::setNonPublicProperty($order, '_items', $items);
        $this->setCurrentOrder($order);
    }

    /**
     * @test
     * that getGeneratedItems returns expected item data array
     *
     * @covers ::getGeneratedItems
     *
     * @throws ReflectionException if unable to setUp or call method
     */
    public function getGeneratedItems_withValidOrderItems_returnsOrderItemDataArray()
    {
        $qty = 2;
        $imageUrl = 'http://';
        $quoteId = 123;
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        $orderItem = Mage::getModel('sales/order_item')
            ->setProductId($product->getId())
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice($product->getPrice())
            ->setQtyOrdered($qty);
        $this->getGeneratedItemsSetUp(array($orderItem), $quoteId);

        $this->boltHelperMock->expects($this->once())->method('getItemImageUrl')->with($orderItem)
            ->willReturn($imageUrl);

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getGeneratedItems'
        );
        $this->assertCount(1, $result);
        $item = $result[0];

        $this->assertArraySubset(
            array(
                'reference'    => $quoteId,
                'image_url'    => $imageUrl,
                'name'         => $product->getName(),
                'sku'          => $product->getSku(),
                'description'  => $product->getDescription(),
                'total_amount' => ($product->getPrice() * $orderItem->getQtyOrdered()) * 100,
                'unit_price'   => $product->getPrice() * 100,
                'type'         => Bolt_Boltpay_Model_Order_Detail::ITEM_TYPE_PHYSICAL,
                'quantity'     => $qty
            ),
            $item
        );
    }

    /**
     * @test
     * that getGeneratedItems returns appropriate type for virtual products and trims description to length of 8182
     *
     * @covers ::getGeneratedItems
     *
     * @throws ReflectionException if unable to setUp or call method
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
     */
    public function getGeneratedItems_withVirtualOrderItemAndLongDescription_returnsOrderItemDataArrayWithDigitalTypeAndTrimmedDescription()
    {
        $description = str_repeat('test', 2500);
        $productId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            'TEST_VIRTUAL_PRODUCT',
            array(
                'type_id'     => Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
                'description' => $description
            )
        );
        $orderItem = Mage::getModel('sales/order_item')
            ->setProductId($productId);
        $this->getGeneratedItemsSetUp(array($orderItem));

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getGeneratedItems'
        );
        $this->assertCount(1, $result);
        $item = $result[0];
        $this->assertEquals(Bolt_Boltpay_Model_Order_Detail::ITEM_TYPE_DIGITAL, $item['type']);
        $this->assertEquals(substr($description, 0, 8182), $item['description']);
        Bolt_Boltpay_ProductProvider::deleteDummyProduct($productId);
    }

    /**
     * Set order property of tested class mocked instance
     *
     * @param Mage_Sales_Model_Order $order to set as the property
     * @throws ReflectionException if the class is missing order property
     */
    private function setCurrentOrder($order)
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'order',
            $order
        );
    }
}
