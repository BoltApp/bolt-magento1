<?php
require_once('TestHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;
/**
 * Class Bolt_Boltpay_Model_ObserverTest
 */
class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Observer';

    /**
     * @var Bolt_Boltpay_Model_Observer  The mocked instance the test class
     */
    private $testClassMock;


    /**
     * @var MockObject|Bolt_Boltpay_Model_FeatureSwitch
     */
    private $featureSwitchMock;

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * Generate dummy products for testing purposes
     * @inheritdoc
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'));
    }

    /**
     * Delete dummy products after the test
     * @inheritdoc
     */
    public static function tearDownAfterClass()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @inheritdoc
     */
    public function testCheckObserverClass()
    {
        $observer = Mage::getModel('boltpay/Observer');

        $this->assertInstanceOf('Bolt_Boltpay_Model_Observer', $observer);
    }

    /**
     * @inheritdoc
     */
    public function testAddMessageWhenCapture()
    {
        $incrementId = '100000001';
        $order = $this->getMockBuilder('Mage_Sales_Model_Order')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(['getIncrementId'])
            ->getMock();

        $order->method('getIncrementId')
            ->will($this->returnValue($incrementId));

        $orderPayment = $this->getMockBuilder('Mage_Sales_Model_Order_Payment')
            ->setMethods(['getMethod', 'getOrder'])
            ->enableOriginalConstructor()
            ->getMock();

        $orderPayment
            ->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $orderPayment->method('getOrder')
            ->willReturn($order);

        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $orderPayment));

        $this->assertEquals('Magento Order ID: "'.$incrementId.'".', $orderPayment->getData('prepared_message'));
    }

    /**
     * @inheritdoc
     */
    public function testGetInvoiceItemsFromShipment()
    {
        $this->testClassMock = $this->getTestClassPrototype()->setMethods(null)->getMock();

        $orderItem = $this->getMockBuilder('Mage_Sales_Model_Order_Item')
            ->setMethods(['getQtyOrdered','getQtyInvoiced','canInvoice'])
            ->getMock();
        $orderItem->method('getQtyOrdered')->willReturn(3);
        $orderItem->method('getQtyInvoiced')->willReturn(1);
        $orderItem->method('canInvoice')->willReturn(true);

        $shipmentItem = $this->getMockBuilder('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(['getOrderItem','getOrderItemId','getQty'])
            ->getMock();
        $shipmentItem->method('getOrderItem')->willReturn($orderItem);
        $shipmentItem->method('getOrderItemId')->willReturn('100000001');
        $shipmentItem->method('getQty')->willReturn(3);

        $shipment = $this->getMockBuilder('Mage_Sales_Model_Order_Shipment')
            ->setMethods(['getAllItems'])
            ->getMock();
        $shipment->method('getAllItems')->willReturn([$shipmentItem]);

        $expected = ['100000001'=> 2];
        $result = TestHelper::callNonPublicFunction($this->testClassMock, 'getInvoiceItemsFromShipment', [$shipment]);

        $this->assertEquals($expected,$result);
    }


    /**
     * @inheritdoc
     *
     * @param $quotePaymentId
     * @param $quote
     * @param $method
     * @return false|Mage_Core_Model_Abstract
     * @throws Varien_Exception
     */
    private function _createQuotePayment($quotePaymentId, $quote, $method)
    {
        $quotePayment = Mage::getModel('sales/quote_payment');
        $quotePayment->setMethod($method);
        $quotePayment->setId($quotePaymentId);
        $quotePayment->setQuote($quote);
        $quotePayment->save();

        return $quotePayment;
    }

    /**
     * @inheritdoc
     *
     * @param $productId
     * @param $quantity
     * @return Mage_Core_Model_Abstract
     * @throws Varien_Exception
     */
    private function _createGuestCheckout($productId, $quantity)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $cart = Mage::getSingleton('checkout/cart');
        $param = array(
            'product' => self::$productId,
            'qty' => 4
        );
        $cart->addProduct($product, $param);
        $cart->save();

        $checkout = Mage::getSingleton('checkout/type_onepage');
        $addressData = array(
            'firstname' => 'Vagelis',
            'lastname' => 'Bakas',
            'street' => 'Sample Street 10',
            'city' => 'Somewhere',
            'postcode' => '123456',
            'telephone' => '123456',
            'country_id' => 'US',
            'region_id' => 12, // id from directory_country_region table
        );
        $checkout->initCheckout();
        $checkout->saveCheckoutMethod('guest');
        $checkout->getQuote()->getBillingAddress()->addData($addressData);

        $shippingAddress = $checkout->getQuote()->getShippingAddress()->addData($addressData);
        $shippingAddress
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate')
            ->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        $checkout->getQuote()->getPayment()->importData(array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE));
        $checkout->getQuote()->collectTotals()->save();

        $checkout = Mage::getSingleton('checkout/type_onepage');

        $checkout->initCheckout();

        $quoteItem = Mage::getModel('sales/quote_item')
            ->setProduct($product)
            ->setQty($quantity)
            ->setSku($product->getSku())
            ->setName($product->getName())
            ->setWeight($product->getWeight())
            ->setPrice($product->getPrice());

        $checkout->getQuote()
            ->addItem($quoteItem);


        $checkout->getQuote()->collectTotals()->save();
        $checkout->saveCheckoutMethod('guest');
        $checkout->saveShippingMethod('flatrate_flatrate');

        return $cart;
    }

    /**
     * Update Feature Switches if necessary
     *
     * event: controller_front_init_before
     */
    public function updateFeatureSwitches()
    {
        if (Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches) {
            Mage::getSingleton("boltpay/featureSwitch")->updateFeatureSwitches();
        }
    }

    private function updateFeatureSwitches_setup()
    {
        $this->testClassMock = $this->getTestClassPrototype()->setMethods(null)->getMock();
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('updateFeatureSwitches'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
    }

    /**
     * @test
     * When update feature switches flag is true we should call necessary method
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_whenUpdateFeatureSwitchesFlagIsTrue_callNecessaryMethod()
    {
        $this->updateFeatureSwitches_setup();
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = true;
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches');
        $this->testClassMock->updateFeatureSwitches();

        Bolt_Boltpay_TestHelper::restoreSingleton('boltpay/featureSwitch');
    }

    /**
     * @test
     * When update feature switches flag is false we shouldn't do anything
     *
     * @covers ::updateFeatureSwitches
     */
    public function updateFeatureSwitches_whenUpdateFeatureSwitchesFlagIsFalse_doNothing()
    {
        $this->updateFeatureSwitches_setup();
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = false;
        $this->featureSwitchMock->expects($this->never())->method('updateFeatureSwitches');
        $this->testClassMock->updateFeatureSwitches();

        Bolt_Boltpay_TestHelper::restoreSingleton('boltpay/featureSwitch');
    }
}