<?php

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Order
 */
class Bolt_Boltpay_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Dummy Bolt transaction reference */
    const REFERENCE = 'AAAA-BBBB-CCCC-DDDD';

    /** @var int Dummy order increment id */
    const ORDER_INCREMENT_ID = 123456;

    /** @var int Dummy coupon code */
    const COUPON_CODE = 11235813;

    /** @var int Dummy parent quote id */
    const PARENT_QUOTE_ID = 456;

    /** @var int Dummy immutable quote id */
    const IMMUTABLE_QUOTE_ID = 457;

    /** @var int Dummy customer id */
    const CUSTOMER_ID = 1;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Order';

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Mage_Sales_Model_Quote Mocked instance of immutable quote
     */
    private $immutableQuoteMock;

    /**
     * @var MockObject|Mage_Sales_Model_Quote Mocked instance of parent quote
     */
    private $parentQuoteMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Order Mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Sales_Model_Order Mocked instance of Magento order model
     */
    private $orderMock;

    /**
     * @var MockObject|Mage_Sales_Model_Order_Payment Mocked instance of Magento order payment model
     */
    private $paymentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject Mocked instance of Bolt shipping and tax model
     */
    private $boltShippingAndTax;

    /**
     * @var Mage_Core_Model_Config_Element Original event configuration that we will stub to nothing
     */
    private static $originalBoltBeforeCommitEvent;

    /**
     * Initialize benchmarking and remove events
     *
     * @throws ReflectionException if unable to remove events
     * @throws Mage_Core_Exception if unable to clean registry
     */
    public static function setUpBeforeClass()
    {
        TestHelper::clearRegistry();
        Mage::getModel('boltpay/observer')->initializeBenchmarkProfiler();

        $events = TestHelper::getNonPublicProperty(Mage::app(), '_events');
        static::$originalBoltBeforeCommitEvent = $events['global']['sales_model_service_quote_submit_before'];
        $events['global']['sales_model_service_quote_submit_before'] = false;
        TestHelper::setNonPublicProperty(Mage::app(), '_events', $events);
    }

    /**
     * Setup common test dependencies, called before each test
     *
     * @throws Exception if test class name is not set
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper'))->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array(
                    'logException',
                    'notifyException',
                    'logWarning',
                    'fetchTransaction',
                    'getImmutableQuoteIdFromTransaction',
                    'setCustomerSessionByQuoteId',
                    'getExtraConfig',
                    'collectTotals',
                    'doFilterEvent',
                    'addBreadcrumb',
                )
            )
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(
                array(
                    'getStatus',
                    'setQuoteId',
                    'getQuoteId',
                    'save',
                    'cancel',
                    'setStatus',
                    'delete',
                    'getId',
                    'getQuote',
                    'getPayment',
                    'getGrandTotal',
                    'setTaxAmount',
                    'getTaxAmount',
                    'setBaseTaxAmount',
                    'getBaseTaxAmount',
                    'setGrandTotal',
                    'setBaseGrandTotal',
                    'getBaseGrandTotal',
                    'setCreatedAt',
                    'getCreatedAt',
                    'loadByIncrementId',
                    'isObjectNew',
                )
            )
            ->getMock();
        $this->paymentMock = $this->getClassPrototype('sales/order_payment')
            ->setMethods(
                array(
                    'setAdditionalInformation',
                    'save',
                    'getMethodInstance',
                    'getMethod',
                )
            )
            ->getMock();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $quoteMockMethods = array(
            'delete',
            'getParentQuoteId',
            'isObjectNew',
            'getAllItems',
            'getItemsCount',
            'getIsActive',
            'setIsActive',
            'save',
            'loadByIdWithoutStore',
            'getAppliedRuleIds',
            'getTransaction',
            'getTotals',
            'getItemById',
            'getBaseSubtotal',
            'getBaseSubtotalWithDiscount',
            'getCouponCode',
            'getShippingAddress',
            'isVirtual',
            'setTotalsCollectedFlag',
            'prepareRecurringPaymentProfiles',
            'setInventoryProcessed',
            'getPayment'
        );
        $this->immutableQuoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods($quoteMockMethods)->getMock();
        $this->parentQuoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods($quoteMockMethods)->getMock();
        $this->boltShippingAndTax = $this->getClassPrototype('boltpay/shippingAndTax')->getMock();
    }

    /**
     * Restore original values that were substituted
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        TestHelper::restoreOriginals();
        Mage::getSingleton('checkout/cart')->truncate();
        Mage::app()->setCurrentStore('default');
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
    }

    /**
     * Restores events
     */
    public static function tearDownAfterClass()
    {
        $events = TestHelper::getNonPublicProperty(Mage::app(), '_events');
        $events['global']['sales_model_service_quote_submit_before'] = static::$originalBoltBeforeCommitEvent;
        TestHelper::setNonPublicProperty(Mage::app(), '_events', $events);
    }

    /**
     * @test
     * whether flags are correctly set after an email is sent and that no exceptions are thrown in the process
     *
     * @covers ::sendOrderEmail
     */
    public function sendOrderEmail_ifEmailNotSentAlready_queuesNewOrderEmail()
    {
        /** @var Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');
        /** @var MockObject|Mage_Sales_Model_Order $order */
        $order = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(array('getPayment', 'addStatusHistoryComment', 'queueNewOrderEmail'))
            ->getMock();
        $orderPayment = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')
            ->setMethods(array('save'))
            ->enableOriginalConstructor()
            ->getMock();

        $order->method('getPayment')->willReturn($orderPayment);
        $order->setIncrementId(187);
        $history = Mage::getModel('sales/order_status_history');
        $order->expects($this->once())->method('queueNewOrderEmail');
        $order->expects($this->once())->method('addStatusHistoryComment')->willReturn($history);
        $this->assertNull($history->getIsCustomerNotified());

        try {
            $orderModel->sendOrderEmail($order);
        } catch (Exception $e) {
            $this->fail('An exception was thrown while sending the email');
        }

        $this->assertTrue($history->getIsCustomerNotified());
    }

    /**
     * @test
     * that if an exception is thrown during the email queueing/sending it is only logged
     *
     * @covers ::sendOrderEmail
     */
    public function sendOrderEmail_ifEmailQueueThrowsException_logsException()
    {
        /** @var MockObject|Mage_Sales_Model_Order $orderMock */
        $orderMock = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(array('getEmailSent', 'queueNewOrderEmail'))
            ->getMock();
        $exception = new Exception('Unable to queue email');
        $orderMock->expects($this->once())->method('queueNewOrderEmail')
            ->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')
            ->with(new Exception('Failed to send order email', 0, $exception));

        $this->currentMock->sendOrderEmail($orderMock);
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::setBoltUserId}
     *
     * @return array
     */
    private function setBoltUserIdSetUp()
    {
        $boltUserId = 911;
        /** @var MockObject|Mage_Customer_Model_Customer $customerMock */
        $customerMock = $this->getClassPrototype('customer/customer')
            ->setMethods(array('getBoltUserId', 'setBoltUserId', 'save'))->getMock();

        /** @var MockObject|Mage_Sales_Model_Quote $quoteMock */
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(array('getCustomer'))->getMock();

        $quoteMock->method('getCustomer')->willReturn($customerMock);

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');
        $session->setBoltUserId($boltUserId);

        $this->assertEquals($boltUserId, $session->getBoltUserId());

        $customerMock->method('getBoltUserId')->willReturn(null);
        $customerMock->expects($this->once())->method('setBoltUserId')->with($boltUserId);
        return array($customerMock, $quoteMock, $session);
    }

    /**
     * @test
     * that setBoltUserId successfully associates Bolt customer ID with the Magento customer
     *
     * @covers ::setBoltUserId
     *
     * @throws ReflectionException if setBoltUserId method doesn't exist
     */
    public function setBoltUserId_whenCustomerBoltIdIsNull_setsCustomerBoltUserIdFromSession()
    {
        list($customerMock, $quoteMock, $session) = $this->setBoltUserIdSetUp();
        $customerMock->expects($this->once())->method('save');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'setBoltUserId',
            array($quoteMock)
        );

        $this->assertEmpty($session->getBoltUserId());
    }

    /**
     * @test
     * that setBoltUserId only logs exception if its thrown during customer saving process
     *
     * @covers ::setBoltUserId
     *
     * @throws ReflectionException if setBoltUserId method doesn't exist
     */
    public function setBoltUserId_ifCustomerCannotBeSaved_logsException()
    {
        list($customerMock, $quoteMock, $session) = $this->setBoltUserIdSetUp();

        $exception = new Exception('Unable to save customer');
        $customerMock->expects($this->once())->method('save')->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'setBoltUserId',
            array($quoteMock)
        );

        $this->assertEmpty($session->getBoltUserId());
    }

    /**
     * Prepares fixtures for validateTotals tests
     *
     * @param int   $discountAmount the Bolt total discount amount in cents
     * @param float $magentoSubtotal the Magento cart total amount in dollars without the discount included
     * @param float $magentoSubtotalWithDiscount the Magento cart total amount in dollars with the discount included
     * @param int   $priceFaultTolerance the allowed difference between Bolt and Magento totals in cents
     *
     * @return MockObject[]|stdClass[]|Mage_Sales_Model_Quote[]|Bolt_Boltpay_Helper_Data[] An array of mocks
     * used in the consuming test. Namely: "mockBoltHelper","mockImmutableQuote","mockTransaction"
     */
    private function validateTotalsSetUp($discountAmount, $magentoSubtotal, $magentoSubtotalWithDiscount, $priceFaultTolerance = 1)
    {
        $mockTransaction = new stdClass();
        $mockTransaction->order->cart->items = array();
        $mockTransaction->order->cart->discount_amount->amount = $discountAmount;

        $this->boltHelperMock->method('getExtraConfig')
            ->willReturnCallback(
                function ($configName) use ($mockTransaction, $priceFaultTolerance) {
                    $mockTransaction->shouldDoTaxTotalValidation = false;
                    $mockTransaction->shouldDoShippingTotalValidation = false;
                    return $priceFaultTolerance;
                }
            );
        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('priceFaultTolerance');

        /** @var Mage_Sales_Model_Quote|MockObject $mockImmutableQuote */
        $mockImmutableQuote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getTotals', 'getBaseSubtotal', 'getBaseSubtotalWithDiscount'))->getMock();

        $magentoTotalsMock = array();
        $mockImmutableQuote->method('getTotals')->willReturn($magentoTotalsMock);
        $mockImmutableQuote->expects($this->once())->method('getTotals');

        $mockImmutableQuote->method('getBaseSubtotal')->willReturn($magentoSubtotal);
        $mockImmutableQuote->expects($this->once())->method('getBaseSubtotal');

        $mockImmutableQuote->method('getBaseSubtotalWithDiscount')->willReturn($magentoSubtotalWithDiscount);
        $mockImmutableQuote->expects($this->once())->method('getBaseSubtotalWithDiscount');

        return array(
            "mockBoltHelper"     => $this->boltHelperMock,
            "mockImmutableQuote" => $mockImmutableQuote,
            "mockTransaction"    => $mockTransaction
        );
    }

    /**
     * @test
     * that when the discount price is the same at Bolt and Magento the validation passes
     *
     * @covers ::validateTotals
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_withDiscountAccurate_determinesThatTotalsAreValid()
    {
        $mocks = $this->validateTotalsSetUp(25, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->never())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($mockImmutableQuote, $mockTransaction)
        );
    }

    /**
     * @test
     * that when the discount price is different at Bolt than Magento WITHIN the price fault tolerance,
     * only a warning is logged
     *
     * @covers ::validateTotals
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_whenDiscountDiffersWithinTolerance_logsWarning()
    {
        $mocks = $this->validateTotalsSetUp(24, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->once())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($mockImmutableQuote, $mockTransaction)
        );
    }

    /**
     * @test
     * that when the discount price is different at Bolt than Magento BEYOND the price fault tolerance,
     * an order creation exception with discount mismatch message is thrown
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Discount total has changed
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_wheDiscountDiffersBeyondTolerance_throwsException()
    {

        $mocks = $this->validateTotalsSetUp(20, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->never())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($mockImmutableQuote, $mockTransaction)
        );
    }

    /**
     * @test
     * that when the discount price is different at Bolt than Magento BEYOND the price fault tolerance
     * with a coupon being used, a mismatch exception is thrown with discount amount changed message
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessageRegExp  /.*Discount amount has changed.*BOLT10POFF.*?/
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_ifCouponDiscountDiffersBeyondTolerance_throwsException()
    {

        $mocks = $this->validateTotalsSetUp(20, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockImmutableQuote->setCouponCode('BOLT10POFF');
        $mockBoltHelper->expects($this->never())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($mockImmutableQuote, $mockTransaction)
        );
    }

    /**
     * @test
     * that when the product price is different at Bolt than Magento BEYOND the price fault tolerance
     * a mismatch exception is thrown that includes the product id, Bolt price and Magento price
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001004
     * @expectedExceptionMessage {"product_id": "1001", "old_price": "20000", "new_price": "24600"}
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_ifItemProductPriceChanges_throwsException()
    {
        $transaction = new stdClass();
        $transaction->order->cart->items = array(
            (object)array('reference' => 1, 'total_amount' => (object)array('amount' => 20000))
        );

        $this->immutableQuoteMock->expects($this->once())->method('getItemById')->with(1)
            ->willReturn(
                Mage::getModel(
                    'sales/quote_item',
                    array('row_total_with_discount' => 15, 'calculation_price' => 123, 'qty' => 2, 'product_id' => 1001)
                )
            );

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     *
     * @covers ::validateTotals
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_ifItemProductPriceDoesNotChange_notThrowsException()
    {
        $transaction = new stdClass();
        $transaction->order->cart->items = array(
            (object)array('reference' => 1, 'unit_price' => 460, 'total_amount' => (object)array('amount' => 2300))
        );

        $this->immutableQuoteMock->expects($this->once())->method('getTotals')
            ->willReturnCallback(
                function () use ($transaction) {
                    //disable validations we are not interested in this test
                    $transaction->shouldDoTaxTotalValidation = false;
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = true;
                }
            );

        $this->immutableQuoteMock->expects($this->once())->method('getItemById')->with(1)
            ->willReturn(
                Mage::getModel(
                    'sales/quote_item',
                    array('row_total_with_discount' => 0, 'calculation_price' => 4.6035, 'qty' => 5, 'product_id' => 1001)
                )
            );

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     * that when the tax total amount is different at Bolt than Magento BEYOND the price fault tolerance
     * a mismatch exception is thrown that includes the mismatch reason, Bolt tax amount and Magento tax amount
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001003
     * @expectedExceptionMessage {"reason": "Tax amount has changed", "old_value": "2000", "new_value": "2600"}
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_withTaxTotalMismatchBeyondTolerance_throwsException()
    {
        $transaction = new stdClass();
        $transaction->order->cart->tax_amount->amount = 2000;

        $this->immutableQuoteMock->expects($this->once())->method('getTotals')
            ->willReturn(array('tax' => new Varien_Object(array('value' => 26))));
        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('priceFaultTolerance')
            ->willReturn(1);

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     * that when the tax total amount is different at Bolt than Magento UNDER the price fault tolerance
     * a warning is logged containing both Magento and Bolt tax amounts
     *
     * @covers ::validateTotals
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_withTaxTotalMismatchUnderTolerance_logsAndNotifiesWarning()
    {
        $transaction = new stdClass();
        $transaction->order->cart->tax_amount->amount = 45601;

        $this->immutableQuoteMock->expects($this->once())->method('getTotals')
            ->willReturn(array('tax' => new Varien_Object(array('value' => 456))));
        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('priceFaultTolerance')
            ->willReturn(1);
        $message = 'Tax differed by 1 cents.  Bolt: 45601 | Magento: 45600';
        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);
        $this->boltHelperMock->expects($this->once())->method('notifyException')
            ->with(new Exception($message), array(), 'warning')
            ->willReturnCallback(
                function () use ($transaction) {
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = true;
                }
            );

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     * that when the shipping total amount is different at Bolt than Magento BEYOND the price fault tolerance
     * a mismatch exception is thrown that includes the mismatch reason and both Bolt and Magento shipping amounts
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001008
     * @expectedExceptionMessage {"reason": "Shipping total has changed", "old_value": "40000", "new_value": "45000"}
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_whenShippingTotalsMismatchBeyondTolerance_throwsException()
    {
        $transaction = new stdClass();
        $transaction->order->cart->shipping_amount->amount = 40000;

        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('priceFaultTolerance')
            ->willReturn(1);

        $this->immutableQuoteMock->expects($this->once())->method('getTotals')
            ->willReturnCallback(
                function () use ($transaction) {
                    //disable validations we are not interested in this test
                    $transaction->shouldDoTaxTotalValidation = false;
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = false;
                    $transaction->shouldDoDiscountTotalValidation = false;
                    $transaction->shouldDoShippingTotalValidation = true;
                }
            );

        $this->immutableQuoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $this->immutableQuoteMock->expects($this->once())->method('getShippingAddress')
            ->willReturn(
                Mage::getModel(
                    'sales/quote_address',
                    array('shipping_amount' => 465, 'base_shipping_discount_amount' => 15)
                )
            );

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     * that when the shipping total amount is different at Bolt than Magento BEYOND the price fault tolerance
     * a warning is logged that includes the mismatch reason and both Bolt and Magento shipping amounts
     *
     * @covers ::validateTotals
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     */
    public function validateTotals_whenShippingTotalsMismatchUnderTolerance_logsAndNotifiesWarning()
    {
        $transaction = new stdClass();
        $transaction->order->cart->shipping_amount->amount = 45001;

        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('priceFaultTolerance')
            ->willReturn(1);

        $this->immutableQuoteMock->expects($this->once())->method('getTotals')
            ->willReturnCallback(
                function () use ($transaction) {
                    //disable validations we are not interested in this test
                    $transaction->shouldDoTaxTotalValidation = false;
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = false;
                    $transaction->shouldDoDiscountTotalValidation = false;
                    $transaction->shouldDoShippingTotalValidation = true;
                }
            );

        $this->immutableQuoteMock->expects($this->once())->method('isVirtual')->willReturn(false);
        $this->immutableQuoteMock->expects($this->once())->method('getShippingAddress')
            ->willReturn(
                Mage::getModel(
                    'sales/quote_address',
                    array('shipping_amount' => 465, 'base_shipping_discount_amount' => 15)
                )
            );

        $message = 'Shipping differed by 1 cents.  Bolt: 45001 | Magento: 45000';
        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);
        $this->boltHelperMock->expects($this->once())->method('notifyException')
            ->with(new Exception($message), array(), 'warning')
            ->willReturnCallback(
                function () use ($transaction) {
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = true;
                }
            );

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateTotals',
            array($this->immutableQuoteMock, $transaction)
        );
    }

    /**
     * @test
     * that validateTotals loads quote item by product id if is_bolt_pdp flag is set on quote
     * by expecting exception message to be thrown due to different prices betwen product and transaction item
     *
     * @covers ::validateTotals
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001004
     * @expectedExceptionMessage "old_price": "23200", "new_price": "13200"
     *
     * @throws ReflectionException if validateTotals method doesn't exist
     * @throws Mage_Core_Exception if unable to add product to cart
     * @throws Exception if unable to create dummy product
     */
    public function validateTotals_fromProductPageCheckout_loadsCartItemByProductIdAndThrowsException()
    {
        $productId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            uniqid('validate_totals_ppc'),
            array('price' => 132)
        );
        $transaction = new stdClass();
        $transaction->order->cart->items = array(
            (object)array('reference' => $productId, 'total_amount' => (object)array('amount' => 23200))
        );
        $cart = Mage::getModel('checkout/cart', array('quote' => Mage::getModel('sales/quote')));
        $cart->addProduct($productId, 1);
        $quote = $cart->getQuote();
        $quote->getShippingAddress()->setPaymentMethod('boltpay');
        $quote->getPayment()->importData(array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE));
        $quote->setData('is_bolt_pdp', 1)->save();
        try {
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'validateTotals',
                array($quote, $transaction)
            );
        } catch (Exception $e) {
            Bolt_Boltpay_ProductProvider::deleteDummyProduct($productId);
            throw $e;
        }
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::createOrder}
     *
     * @return array containing mock instance, product id, parent quote id, immutable quote id and transaction
     *
     * @throws Exception if unable to create dummy objects
     */
    private function createOrderSetUp()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'setOrderUserNote',
                    'associateOrderToCustomerWhenPlacingOnPDP',
                    'validateCartSessionData',
                    'validateCoupons',
                    'validateTotals'
                )
            )
            ->getMock();

        $productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('createOrderTest'));
        $parentQuoteId = Bolt_Boltpay_CouponHelper::createDummyQuote(array('store_id' => 1));
        $immutableQuoteId = Bolt_Boltpay_CouponHelper::createDummyQuote(
            array('store_id' => 1, 'parent_quote_id' => $parentQuoteId),
            null
        );

        $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuoteId);
        $immutableQuote->addProduct(Mage::getModel('catalog/product')->load($productId), 1)
            ->save();
        $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($parentQuoteId);
        $parentQuote->addProduct(Mage::getModel('catalog/product')->load($productId), 1)
            ->save();

        $transaction = new stdClass();
        $transaction->order->cart->order_reference = $parentQuoteId;
        $transaction->order->cart->display_id = $parentQuote->getReservedOrderId() . '|' . $immutableQuoteId;
        $transaction->order->cart->billing_address = (object)array(
            'email_address' => 'test@bolt.com',
            'first_name'    => 'First Name',
            'last_name'     => 'Last Name',
        );
        $transaction->order->cart->shipments[0] = (object)array(
            'reference'        => 'flatrate_flatrate',
            'shipping_address' => (object)array(
                'street_address1' => 'Test street 123',
                'street_address2' => 'Additional street 007',
                'street_address3' => null,
                'street_address4' => null,
                'first_name'      => 'TesterFn',
                'last_name'       => 'TesterLn',
                'locality'        => 'Los Angeles',
                'region'          => 'CA',
                'postal_code'     => '10011',
                'country_code'    => 'US',
                'phone'           => '+1 123 111 3333',
                'phone_number'    => '+1 123 111 3333',
            )
        );
        $transaction->order->cart->total_amount->amount = 1583;
        $transaction->order->cart->tax_amount->amount = 83;
        $transaction->order->cart->shipping_amount->amount = 500;
        return array($currentMock, $productId, $parentQuote, $immutableQuote, $transaction);

    }

    /**
     * @test
     * that createOrder will throw OrderCreationException when not provided with Bolt transaction reference
     * and Pre Auth Creation parameter is set to false
     *
     * @covers ::createOrder
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Bolt transaction reference is missing in the Magento order creation process.
     */
    public function createOrder_withoutReferenceInNonPreAuthContext_throwsException()
    {
        $message = 'Bolt transaction reference is missing in the Magento order creation process.';
        $origException = new Exception($message);

        $this->boltHelperMock->expects($this->once())->method('logException')->with($origException);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($origException);

        $this->currentMock->createOrder(null, null, false);
    }

    /**
     * @test
     * that createOrder successfully creates new order when provided with sufficient transaction data
     *
     * @covers ::createOrder
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created objects
     * @throws Bolt_Boltpay_OrderCreationException from method tested on creation failure
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_withValidRequest_createsOrder()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();
        $transaction->order->user_note = 'Test user note';
        $currentMock->expects($this->once())->method('setOrderUserNote')
            ->with($this->isInstanceOf('Mage_Sales_Model_Order'), '[CUSTOMER NOTE] Test user note');

        $order = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);
        $this->assertInstanceOf('Mage_Sales_Model_Order', $order);
        $this->assertCount(1, $order->getAllVisibleItems());
        $this->assertEquals($productId, $order->getAllVisibleItems()[0]->getProductId());
        $this->assertArraySubset(
            array(
                'customer_email'  => 'test@bolt.com',
                'shipping_method' => 'flatrate_flatrate'
            ),
            $order->getData()
        );

        $this->createOrderTearDown($order, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * @test
     * that createOrder returns existing order if one already exists with quote reserved id as increment id and
     * its quote id matches immutable quote id
     *
     * @covers ::createOrder
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created dummy models
     * @throws Bolt_Boltpay_OrderCreationException if unable to create order
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_whenPreExistingOrderFoundWithMatchingQuoteId_returnsExistingOrder()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();

        TestHelper::stubModel('sales/order', $this->orderMock);
        $this->orderMock->expects($this->once())->method('loadByIncrementId')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->orderMock->expects($this->once())->method('getQuoteId')->willReturn($immutableQuote->getId());

        $order = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);
        $this->assertSame($this->orderMock, $order);

        $this->createOrderTearDown($order, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * @test
     * that createOrder re-generates reserved order id if there is already an order with the same increment id but
     * its quote id doesn't match increment id
     *
     * @covers ::createOrder
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created dummy models
     * @throws Bolt_Boltpay_OrderCreationException if unable to create order
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_whenPreExistingOrderFoundWithNonMatchingQuoteId_createsNewOrder()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $oldCurrentMock
         * @var int                                 $oldProductId
         * @var Mage_Sales_Model_Quote              $oldParentQuote
         * @var Mage_Sales_Model_Quote              $oldImmutableQuote
         * @var object                              $oldTransaction
         */
        list($oldCurrentMock, $oldProductId, $oldParentQuote, $oldImmutableQuote, $oldTransaction) = $this->createOrderSetUp();
        $preExistingOrder = $oldCurrentMock->createOrder(self::REFERENCE, null, false, $oldTransaction);

        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();
        $parentQuote->setReservedOrderId($preExistingOrder->getIncrementId())->save();
        $newOrder = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);

        $this->assertNotEquals($preExistingOrder->getId(), $newOrder->getId());
        $this->assertNotEquals($preExistingOrder->getIncrementId(), $newOrder->getIncrementId());
        $this->assertNotEquals(
            $preExistingOrder->getIncrementId(),
            $parentQuote->loadByIdWithoutStore($parentQuote->getId())->getReservedOrderId()
        );

        $this->createOrderTearDown($preExistingOrder, $oldProductId, $oldImmutableQuote->getId(), $oldParentQuote->getId());
        $this->createOrderTearDown($newOrder, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * @test
     * that if quote submit throws a {@see Bolt_Boltpay_OrderCreationException} it's re-thrown
     *
     * @covers ::createOrder
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created objects
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_withSubmissionException_rethrowsException()
    {
        $exception = new Bolt_Boltpay_OrderCreationException('Expected exception');
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();

        $serviceQuoteMock = $this->getClassPrototype('sales/service_quote')->setMethods(array())->getMock();
        $serviceQuoteMock->expects($this->once())->method('submitAll')->willThrowException($exception);
        TestHelper::stubModel('sales/service_quote', $serviceQuoteMock);

        try {
            $currentMock->createOrder(self::REFERENCE, null, false, $transaction);
        } catch (Exception $e) {
            $this->createOrderTearDown(null, $productId, $immutableQuote->getId(), $parentQuote->getId());
            throw $e;
        }

    }

    /**
     * @test
     * that if bolt_is_pdp parameter set to true on immutable quote and customer is logged in
     * order is associated to customer
     *
     * @covers ::createOrder
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created dummy models
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws Bolt_Boltpay_OrderCreationException if unable to create order
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_forCustomerFromProductPage_associatesOrderToCustomer()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();
        $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuote->getId());
        $immutableQuote->setData('is_bolt_pdp', 1)->save();

        $customerSessionMock = $this->getClassPrototype('customer/session')
            ->setMethods(array('isLoggedIn'))->getMock();
        $customerSessionMock->method('isLoggedIn')->willReturn(true);
        TestHelper::stubSingleton('customer/session', $customerSessionMock);

        $currentMock->expects($this->once())->method('associateOrderToCustomerWhenPlacingOnPDP');

        $order = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);

        $this->createOrderTearDown($order, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * @test
     * that create order successfully creates order when transaction provided is in legacy format
     * having shipping method name in service field
     *
     * @covers ::createOrder
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created dummy models
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws Bolt_Boltpay_OrderCreationException if unable to create order
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_withLegacyShipmentReferenceInService_createsOrder()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();
        $transaction->order->cart->shipments[0]->reference = null;
        $transaction->order->cart->shipments[0]->service = 'Flat Rate - Fixed';

        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data', false)
            ->setMethods(array('collectTotals'))->enableProxyingToOriginalMethods()->getMock();
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $boltHelperMock->method('collectTotals')
            ->with(
                $this->callback(
                    function ($immutableQuote) {
                        $immutableQuote->getShippingAddress()->setCollectShippingRates(true);
                        return true;
                    }
                ),
                $this->anything()
            );

        $order = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);
        $this->assertInstanceOf('Mage_Sales_Model_Order', $order);
        $this->assertCount(1, $order->getAllVisibleItems());
        $this->assertEquals($productId, $order->getAllVisibleItems()[0]->getProductId());
        $this->assertArraySubset(
            array(
                'customer_email'  => 'test@bolt.com',
                'shipping_method' => 'flatrate_flatrate'
            ),
            $order->getData()
        );

        $this->createOrderTearDown($order, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * @test
     * if shipping method is not defined a warning is logged
     *
     * @covers ::createOrder
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete created dummy models
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws Bolt_Boltpay_OrderCreationException if unable to create order
     * @throws Exception from test setup if unable to create dummy objects
     */
    public function createOrder_whenShippingMethodIsNotFound_logsWarning()
    {
        /**
         * @var MockObject|Bolt_Boltpay_Model_Order $currentMock
         * @var int                                 $productId
         * @var Mage_Sales_Model_Quote              $parentQuote
         * @var Mage_Sales_Model_Quote              $immutableQuote
         * @var object                              $transaction
         */
        list($currentMock, $productId, $parentQuote, $immutableQuote, $transaction) = $this->createOrderSetUp();
        $transaction->order->cart->shipments[0]->reference = null;
        $transaction->order->cart->shipments[0]->service = null;

        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data', false)
            ->setMethods(array('logWarning', 'notifyException'))->enableProxyingToOriginalMethods()->getMock();
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $boltHelperMock->expects($this->once())->method('logWarning')->with('Shipping method not found');
        $boltHelperMock->expects($this->exactly(2))->method('notifyException')
            ->withConsecutive(
                array(
                    new Exception('Shipping method not found'),
                    new PHPUnit_Framework_Constraint_ArraySubset(array())
                )
            );

        $order = $currentMock->createOrder(self::REFERENCE, null, false, $transaction);
        $this->assertInstanceOf('Mage_Sales_Model_Order', $order);
        $this->assertCount(1, $order->getAllVisibleItems());
        $this->assertEquals($productId, $order->getAllVisibleItems()[0]->getProductId());
        $this->assertArraySubset(
            array(
                'customer_email'  => 'test@bolt.com',
                'shipping_method' => 'flatrate_flatrate'
            ),
            $order->getData()
        );

        $this->createOrderTearDown($order, $productId, $immutableQuote->getId(), $parentQuote->getId());
    }

    /**
     * Delete created product, order and quotes
     *
     * @param Mage_Sales_Model_Order $order to be deleted
     * @param int                    $productId to be deleted
     * @param int                    $immutableQuoteId to be deleted
     * @param int                    $parentQuoteId to be deleted
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete
     */
    private function createOrderTearDown(Mage_Sales_Model_Order $order, $productId, $immutableQuoteId, $parentQuoteId)
    {
        if ($order instanceof Mage_Sales_Model_Order) {
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }

        Bolt_Boltpay_ProductProvider::deleteDummyProduct($productId);
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($immutableQuoteId);
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($parentQuoteId);
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::validateCartSessionData}
     *
     * @return MockObject|Bolt_Boltpay_Model_Order mocked instance of the class tested
     *
     * @throws Exception if test class name is not defined
     */
    private function validateCartSessionDataSetUp()
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        return $currentMock;
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if immutable quote id provided was
     * not located
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Cart does not exist with reference
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception
     */
    public function validateCartSessionData_whenImmutableQuoteIsNotFound_throwsException()
    {
        $isImmutableQuoteNew = true;
        $immutableQuoteId = 123;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->boltHelperMock->expects($this->once())->method('getImmutableQuoteIdFromTransaction')->willReturn(
            $immutableQuoteId
        );
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $transaction = new stdClass();
        $transaction->order->cart->display_id = $immutableQuoteId;
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                $transaction,
                false
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if
     * parent quote is empty and in the pre-auth context
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Cart is empty
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception from test setup if tested class name is not set
     */
    public function validateCartSessionData_inPreAuthWithEmptyParentQuote_throwsException()
    {
        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('getItemsCount')->willReturn(0);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                true
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if immutable quote provided is not saved
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Cart has expired
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception from test setup if tested class name is not set
     */
    public function validateCartSessionData_whenParentQuoteIsNotFound_throwsException()
    {
        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('isObjectNew')->willReturn(true);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                false
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if any product in cart is not salable
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage The product is not purchasable
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception from test setup if tested class name is not set
     */
    public function validateCartSessionData_whenAnyProductIsNotSalable_throwsException()
    {
        $unsalableProductMock = $this->getClassPrototype('Mage_Catalog_Model_Product')->getMock();
        $salableProductMock1 = $this->getClassPrototype('Mage_Catalog_Model_Product')->getMock();
        $salableProductMock2 = $this->getClassPrototype('Mage_Catalog_Model_Product')->getMock();

        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $this->immutableQuoteMock->expects($this->once())->method('getAllItems')
            ->willReturn(
                array(
                    Mage::getModel('sales/quote_item', array('product' => $salableProductMock1)),
                    Mage::getModel('sales/quote_item', array('product' => $unsalableProductMock)),
                    Mage::getModel('sales/quote_item', array('product' => $salableProductMock2))
                )
            );
        $unsalableProductMock->expects($this->once())->method('isSaleable')->willReturn(false);
        $salableProductMock1->expects($this->once())->method('isSaleable')->willReturn(true);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                false
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if one of the products in quote is out of stock
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001005
     * @expectedExceptionMessage {"product_id": "1", "available_quantity": 0, "needed_quantity": 2}
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception from test setup if tested class name is not set
     */
    public function validateCartSessionData_whenProductIsOutOfStock_throwsException()
    {
        $productMock = $this->getClassPrototype('Mage_Catalog_Model_Product')->getMock();
        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $this->immutableQuoteMock->expects($this->once())->method('getAllItems')
            ->willReturn(array(Mage::getModel('sales/quote_item', array('product' => $productMock, 'qty' => 2))));
        $productMock->method('isSaleable')->willReturn(false);
        $productMock->expects($this->atLeastOnce())->method('getId')->willReturn(1);
        $stockItemMock = $this->getClassPrototype('cataloginventory/stock_item')
            ->setMethods(array('loadByProduct', 'getQty', 'getMinQty', 'checkQty', 'getIsInStock'))->getMock();
        $stockItemMock->expects($this->once())->method('loadByProduct')->willReturnSelf();
        $stockItemMock->expects($this->once())->method('getIsInStock')->willReturn(false);
        $stockItemMock->expects($this->never())->method('getQty');
        $stockItemMock->expects($this->never())->method('getMinQty');
        $stockItemMock->expects($this->never())->method('checkQty');
        TestHelper::stubModel('cataloginventory/stock_item', $stockItemMock);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                false
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData throws OrderCreationException if one of the products in quote is out of stock
     *
     * @covers ::validateCartSessionData
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001005
     * @expectedExceptionMessage {"product_id": "1", "available_quantity": 1, "needed_quantity": 2}
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Exception from test setup if tested class name is not set
     */
    public function validateCartSessionData_whenProductHasInsufficientQty_throwsException()
    {
        $productMock = $this->getClassPrototype('Mage_Catalog_Model_Product')->getMock();
        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $this->immutableQuoteMock->expects($this->once())->method('getAllItems')
            ->willReturn(array(Mage::getModel('sales/quote_item', array('product' => $productMock, 'qty' => 2))));
        $productMock->method('isSaleable')->willReturn(false);
        $productMock->expects($this->atLeastOnce())->method('getId')->willReturn(1);
        $stockItemMock = $this->getClassPrototype('cataloginventory/stock_item')
            ->setMethods(array('loadByProduct', 'getQty', 'getMinQty', 'checkQty', 'getIsInStock'))->getMock();
        $stockItemMock->expects($this->once())->method('loadByProduct')->willReturnSelf();
        $stockItemMock->expects($this->once())->method('getIsInStock')->willReturn(true);
        $stockItemMock->expects($this->once())->method('getQty')->willReturn(1);
        $stockItemMock->expects($this->once())->method('getMinQty')->willReturn(0);
        $stockItemMock->expects($this->once())->method('checkQty')->willReturn(false);
        TestHelper::stubModel('cataloginventory/stock_item', $stockItemMock);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                false
            )
        );
    }

    /**
     * @test
     * that validateCartSessionData returns valid for cart with only
     * salable, in stock products
     *
     * @covers ::validateCartSessionData
     *
     * @throws ReflectionException if validateCartSessionData doesn't exist
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
     * @throws Exception if unable to create dummy product
     */
    public function validateCartSessionData_withValidProduct_completesWithoutException()
    {
        $dummyProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            uniqid('validateCartSessionData'),
            array(),
            1
        );
        $isImmutableQuoteNew = false;
        $currentMock = $this->validateCartSessionDataSetUp();
        $this->immutableQuoteMock->expects($this->once())->method('isObjectNew')->willReturn($isImmutableQuoteNew);
        $this->parentQuoteMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $this->immutableQuoteMock->expects($this->once())->method('getAllItems')
            ->willReturn(
                array(
                    Mage::getModel(
                        'sales/quote_item',
                        array(
                            'product' => Mage::getModel('catalog/product')
                                ->load($dummyProductId)
                                ->setData('is_salable', 1),
                            'qty'     => 1
                        )
                    )
                )
            );
        TestHelper::callNonPublicFunction(
            $currentMock,
            'validateCartSessionData',
            array(
                $this->immutableQuoteMock,
                $this->parentQuoteMock,
                new stdClass(),
                false
            )
        );
        Bolt_Boltpay_ProductProvider::deleteDummyProduct($dummyProductId);
    }

    /**
     * @test
     * that associateOrderToCustomerWhenPlacingOnPDP sets order customer details from current customer session
     *
     * @covers ::associateOrderToCustomerWhenPlacingOnPDP
     *
     * @throws Mage_Core_Exception if unable to stub customer session singleton
     * @throws ReflectionException if associateOrderToCustomerWhenPlacingOnPDP method doesn't exist
     * @throws Exception if test class name is not set
     */
    public function associateOrderToCustomerWhenPlacingOnPDP_always_setsCustomerDetails()
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(null)->getMock();
        $dummyCustomer = Mage::getModel(
            'customer/customer',
            array(
                'entity_id' => 123456,
                'email'     => 'test@bolt.com',
                'firstname' => 'First Name',
                'lastname'  => 'Last Name',
                'group_id'  => 1
            )
        );
        $sessionMock = $this->getClassPrototype('customer/session')->setMethods(array('getCustomer'))->getMock();
        $sessionMock->method('getCustomer')->willReturn($dummyCustomer);
        TestHelper::stubSingleton('customer/session', $sessionMock);

        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(
                array(
                    'loadByIncrementId',
                    'setCustomerId',
                    'setCustomerEmail',
                    'setCustomerFirstname',
                    'setCustomerLastname',
                    'setCustomerIsGuest',
                    'setCustomerGroupId',
                    'save'
                )
            )
            ->getMock();

        $orderMock->expects($this->once())->method('loadByIncrementId')->with(self::ORDER_INCREMENT_ID)->willReturnSelf(
        );
        $orderMock->expects($this->once())->method('setCustomerId')->with($dummyCustomer->getId())->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomerEmail')->with(
            $dummyCustomer->getEmail()
        )->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomerFirstname')->with(
            $dummyCustomer->getFirstname()
        )->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomerLastname')->with(
            $dummyCustomer->getLastname()
        )->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomerIsGuest')->with(0)->willReturnSelf();
        $orderMock->expects($this->once())->method('setCustomerGroupId')->with(
            $dummyCustomer->getGroupId()
        )->willReturnSelf();
        $orderMock->expects($this->once())->method('save');

        TestHelper::stubModel('sales/order', $orderMock);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'associateOrderToCustomerWhenPlacingOnPDP',
            array(
                self::ORDER_INCREMENT_ID
            )
        );
    }

    /**
     * @test
     * that getQuoteById returns quote from the database if a quote exists with provided id
     *
     * @covers ::getQuoteById
     *
     * @throws Exception if unable to create dummy quote
     */
    public function getQuoteById_withExistingQuoteId_returnsQuoteFromDatabase()
    {
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $quote = $this->currentMock->getQuoteById($quoteId);
        $this->assertInstanceOf('Mage_Sales_Model_Quote', $quote);
        $this->assertFalse($quote->isObjectNew());
        $this->assertSame($quoteId, $quote->getId());
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * @test
     * that getQuoteById returns empty quote object if there is no quote with provided id
     *
     * @covers ::getQuoteById
     */
    public function getQuoteById_withRandomQuoteId_returnsEmptyQuoteObject()
    {
        $quote = $this->currentMock->getQuoteById(mt_rand());
        $this->assertInstanceOf('Mage_Sales_Model_Quote', $quote);
        $this->assertTrue($quote->isObjectNew());
    }

    /**
     * @test
     * that getOrderByParentQuoteId returns order that is associated with provided quote id via its immutable quote
     *
     * @covers ::getOrderByParentQuoteId
     *
     * @throws Exception if tested class name is not defined
     */
    public function getOrderByParentQuoteId()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getQuoteById', 'getOrderByQuoteId'))->getMock(
        );
        $currentMock->expects($this->once())->method('getQuoteById')->with(self::PARENT_QUOTE_ID)
            ->willReturn($this->parentQuoteMock);
        $this->parentQuoteMock->expects($this->once())->method('getParentQuoteId')->willReturn(
            self::IMMUTABLE_QUOTE_ID
        );

        $currentMock->expects($this->once())->method('getOrderByQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->orderMock);

        $this->assertSame($this->orderMock, $currentMock->getOrderByParentQuoteId(self::PARENT_QUOTE_ID));
    }

    /**
     * @test
     * that setOrderUserNote adds order status history comment that is visible on front
     *
     * @covers ::setOrderUserNote
     */
    public function setOrderUserNote_always_addsStatusHistoryCommentToOrder()
    {
        $userNote = 'test';
        /** @var MockObject|Mage_Sales_Model_Order $orderMock */
        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(
                array(
                    'addStatusHistoryComment',
                    'setIsVisibleOnFront',
                    'setIsCustomerNotified',
                    'save',
                )
            )
            ->getMock();
        $orderMock->expects($this->once())->method('addStatusHistoryComment')->with($userNote)->willReturnSelf();
        $orderMock->expects($this->once())->method('setIsVisibleOnFront')->with(true)->willReturnSelf();
        $orderMock->expects($this->once())->method('setIsCustomerNotified')->with(false)->willReturnSelf();
        $orderMock->expects($this->once())->method('save')->with()->willReturnSelf();
        $this->assertSame($orderMock, $this->currentMock->setOrderUserNote($orderMock, $userNote));
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::isBoltOrder}
     *
     * @param string $paymentMethod to be set as returned orders payment method
     *
     * @return MockObject|Mage_Sales_Model_Order instance with payment method set to $paymentMethod parameter
     */
    private function isBoltOrderSetUp($paymentMethod)
    {
        $orderMock = $this->getClassPrototype('sales/order')->setMethods(array('getPayment'))->getMock();
        $paymentMock = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')->setMethods(array('getMethod'))
            ->getMock();
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $paymentMock->method('getMethod')->willReturn($paymentMethod);
        return $orderMock;
    }

    /**
     * @test
     * that isBoltOrder successfully determines if order is a Bolt order based on orders payment method code
     *
     * @covers ::isBoltOrder
     *
     * @dataProvider isBoltOrder_withVariousOrderPaymentMethodsProvider
     *
     * @param string $paymentMethod to be used as order payment method
     * @param bool   $isBoltOrder flag whether order should be detected as Bolt order
     */
    public function isBoltOrder_withVariousOrderPaymentMethods_determinesWhetherOrderIsOwnedByBolt($paymentMethod, $isBoltOrder)
    {
        $orderMock = $this->isBoltOrderSetUp($paymentMethod);
        $this->assertSame($isBoltOrder, $this->currentMock->isBoltOrder($orderMock));
    }

    /**
     * Data provider for {@see isBoltOrder_withVariousOrderPaymentMethods_determinesWhetherOrderIsOwnedByBolt}
     *
     * @return array[] containing payment method code and whether order should be considered Bolt order
     */
    public function isBoltOrder_withVariousOrderPaymentMethodsProvider()
    {
        return array(
            array('paymentMethod' => Bolt_Boltpay_Model_Payment::METHOD_CODE, 'isBoltOrder' => true),
            array('paymentMethod' => 'BoLtPaY', 'isBoltOrder' => true),
            array('paymentMethod' => 'checkmo', 'isBoltOrder' => false),
            array('paymentMethod' => '', 'isBoltOrder' => false),
            array('paymentMethod' => 'paypal', 'isBoltOrder' => false),
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::reactivateUsedPromotion}
     * Creates dummy order containing provided coupon code, applied rule ids and customer id
     *
     * @param string $couponCode to be assigned to order
     * @param string $appliedRuleIds to be assigned to order
     * @param int    $customerId to be assigned to order
     *
     * @return Mage_Sales_Model_Order[]|Bolt_Boltpay_Model_Coupon[]|MockObject[]
     *
     * @throws ReflectionException if unable to stub Coupon model
     */
    private function reactivateUsedPromotionSetUp($couponCode, $appliedRuleIds = '', $customerId = 0)
    {
        $order = Mage::getModel(
            'sales/order',
            array(
                'coupon_code'      => $couponCode,
                'applied_rule_ids' => $appliedRuleIds,
                'customer_id'      => $customerId
            )
        );
        $boltCouponMock = $this->getClassPrototype('boltpay/coupon')->getMock();
        TestHelper::stubModel('boltpay/coupon', $boltCouponMock);
        return array($order, $boltCouponMock);
    }

    /**
     * @test
     * that reactivateUsedPromotion decreases provided coupon and rules usage times
     *
     * @covers ::reactivateUsedPromotion
     *
     * @throws ReflectionException if unable to stub model
     */
    public function reactivateUsedPromotion_withCouponCodeAndAppliedRuleIds_decreasesCouponAndRuleTimesUsed()
    {
        /**
         * @var Mage_Sales_Model_Order $order
         * @var MockObject|Bolt_Boltpay_Model_Coupon $boltCouponMock
         */
        list($order, $boltCouponMock) = $this->reactivateUsedPromotionSetUp(
            self::COUPON_CODE,
            '52,68,79,80,105',
            self::CUSTOMER_ID
        );
        $dummyCoupon = Mage::getModel('salesrule/coupon');
        $boltCouponMock->expects($this->once())->method('decreaseCouponTimesUsed')->with(self::COUPON_CODE)->willReturn(
            $dummyCoupon
        );
        $boltCouponMock->expects($this->once())->method('decreaseCustomerCouponTimesUsed')
            ->with(self::CUSTOMER_ID, $dummyCoupon);
        $boltCouponMock->expects($this->exactly(5))->method('decreaseCustomerRuleTimesUsed')
            ->withConsecutive(
                array(self::CUSTOMER_ID, 52),
                array(self::CUSTOMER_ID, 68),
                array(self::CUSTOMER_ID, 79),
                array(self::CUSTOMER_ID, 80),
                array(self::CUSTOMER_ID, 105)
            );
        $this->currentMock->reactivateUsedPromotion($order);
    }

    /**
     * @test
     * that if an exception is thrown by Coupon model it is only logged
     *
     * @covers ::reactivateUsedPromotion
     *
     * @throws ReflectionException if unable to stub model
     */
    public function reactivateUsedPromotion_withExceptionOnCouponTimesUsedDecrease_logsException()
    {
        /**
         * @var Mage_Sales_Model_Order $order
         * @var MockObject|Bolt_Boltpay_Model_Coupon $boltCouponMock
         */
        list($order, $boltCouponMock) = $this->reactivateUsedPromotionSetUp(self::COUPON_CODE);
        $exception = new Exception('Unable to save coupon');
        $boltCouponMock->expects($this->once())->method('decreaseCouponTimesUsed')->with(self::COUPON_CODE)
            ->willThrowException($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->currentMock->reactivateUsedPromotion($order);
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::removePreAuthOrder}
     *
     * @param bool $isBoltOrder whether order should be considered a Bolt order
     * @param bool $keepPreAuthOrders extra config flag
     *
     * @return MockObject|Bolt_Boltpay_Model_Order
     *
     * @throws ReflectionException if unable to stub model
     * @throws Exception if test class name is not defined
     */
    private function removePreAuthOrderSetUp($isBoltOrder = true, $keepPreAuthOrders = false)
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'isBoltOrder',
                    'getParentQuoteFromOrder',
                    'getQuoteFromOrder',
                    'boltHelper',
                    'reactivateUsedPromotion'
                )
            )->getMock();
        $currentMock->method('isBoltOrder')->with($this->orderMock)->willReturn($isBoltOrder);
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $currentMock->method('getParentQuoteFromOrder')->with($this->orderMock)->willReturn($this->parentQuoteMock);
        $currentMock->method('getQuoteFromOrder')->with($this->orderMock)->willReturn($this->immutableQuoteMock);
        $this->boltHelperMock->method('getExtraConfig')->willReturnMap(
            array(
                array('keepPreAuthOrders', array(), $keepPreAuthOrders)
            )
        );
        $inventoryObserver = $this->getClassPrototype('cataloginventory/observer')
            ->setMethods(array('reindexQuoteInventory'))->getMock();
        TestHelper::stubModel('cataloginventory/observer', $inventoryObserver);

        $event = new Varien_Event(array('quote' => $this->immutableQuoteMock));
        $observer = new Varien_Event_Observer();
        $observer->setName('Bolt_Failed_Payment_Observer')->setEvent($event);
        $event->addObserver($observer);

        $inventoryObserver->expects($this->once())->method('reindexQuoteInventory')->with($observer);

        if (!$keepPreAuthOrders) {
            $this->parentQuoteMock->expects($this->once())->method('setIsActive')->with(true)->willReturnSelf();
            $this->parentQuoteMock->expects($this->once())->method('save');
            $this->orderMock->expects($this->once())->method('delete')->willReturnCallback(
                function () {
                    //assert that current store id during the delete call is admin
                    $this->assertEquals(Mage_Core_Model_App::ADMIN_STORE_ID, Mage::app()->getStore()->getId());
                }
            );
            $currentMock->expects($this->once())->method('reactivateUsedPromotion')->with($this->orderMock);
        } else {
            $this->parentQuoteMock->expects($this->never())->method('setIsActive')->with(true)->willReturnSelf();
            $this->parentQuoteMock->expects($this->never())->method('save');
            $this->orderMock->expects($this->never())->method('delete');
            $currentMock->expects($this->never())->method('reactivateUsedPromotion')->with($this->orderMock);
        }

        return $currentMock;
    }

    /**
     * @test
     * that removePreAuthOrder properly removes the pre-auth order when configured not to keep them
     *
     * @covers ::removePreAuthOrder
     *
     * @throws Exception from test setup
     */
    public function removePreAuthOrder_orderInProcessingAndDontKeepOrders_cancelsAndRemovesOrder()
    {
        $currentMock = $this->removePreAuthOrderSetUp();
        $this->orderMock->expects($this->once())->method('getStatus')->willReturn(
            Mage_Sales_Model_Order::STATE_PROCESSING
        );
        $this->orderMock->expects($this->once())->method('setQuoteId')->with(null)->willReturnSelf();
        $this->orderMock->expects($this->exactly(2))->method('save');
        $this->orderMock->expects($this->once())->method('cancel')->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setStatus')->with('canceled_bolt')->willReturnSelf();

        $paymentMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Payment')
            ->setMethods(array('setMethod', 'save'))
            ->getMock();
        $paymentMock->expects($this->once())->method('setMethod')->with(null)->willReturnSelf();
        $paymentMock->expects($this->once())->method('save')->willReturnSelf();
        $this->immutableQuoteMock->expects($this->once())->method('getPayment')->willReturn($paymentMock);

        $currentMock->removePreAuthOrder($this->orderMock);
    }

    /**
     * @test
     * that removePreAuthOrder doesn't remove the pre-auth order when configured to keep them
     *
     * @covers ::removePreAuthOrder
     *
     * @throws Exception from test setup
     */
    public function removePreAuthOrder_orderStatusCanceledAndKeepOrders_doesNotRemoveOrder()
    {
        $currentMock = $this->removePreAuthOrderSetUp(true, true);
        $this->orderMock->expects($this->once())->method('getStatus')
            ->willReturn(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED);
        $this->orderMock->expects($this->never())->method('setQuoteId')->with(null)->willReturnSelf();
        $this->orderMock->expects($this->never())->method('save');
        $this->orderMock->expects($this->never())->method('cancel')->willReturnSelf();
        $this->orderMock->expects($this->never())->method('setStatus')->with('canceled_bolt')->willReturnSelf();
        $this->immutableQuoteMock->expects($this->never())->method('getPayment');

        $currentMock->removePreAuthOrder($this->orderMock);
    }

    /**
     * @test
     * that getRatesDebuggingData returns debug data in concatenated string format
     *
     * @covers ::getRatesDebuggingData
     * @throws ReflectionException
     */
    public function getRatesDebuggingData_whenProvidedWithRates_returnsConcatenatedDebugExport()
    {
        $rates = array(
            Mage::getModel('sales/quote_address_rate', array('entity_id' => 5)),
            Mage::getModel('sales/quote_address_rate', array('entity_id' => 15)),
            Mage::getModel('sales/quote_address_rate', array('entity_id' => 30)),
        );

        $result = TestHelper::callNonPublicFunction($this->currentMock, 'getRatesDebuggingData', array($rates));

        $this->assertEquals(
            "array('entity_id'=>5,)array('entity_id'=>15,)array('entity_id'=>30,)",
            preg_replace('/\s*/', '', $result)
        );
    }

    /**
     * @test
     * that getParentQuoteFromOrder returns parent quote for order by getting (immutable) quote for order
     * and then loading its parent
     *
     * @covers ::getParentQuoteFromOrder
     *
     * @throws Exception if test class name is not defined
     */
    public function getParentQuoteFromOrder_withValidOrder_returnsParentQuote()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getQuoteFromOrder'))->getMock();
        $currentMock->expects($this->once())->method('getQuoteFromOrder')->with($this->orderMock)
            ->willReturn($this->immutableQuoteMock);
        $this->immutableQuoteMock->expects($this->once())->method('getParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        TestHelper::stubModel('sales/quote', $this->parentQuoteMock);
        $this->parentQuoteMock->expects($this->once())->method('loadByIdWithoutStore')->with(self::PARENT_QUOTE_ID)
            ->willReturnSelf();
        $this->assertSame($this->parentQuoteMock, $currentMock->getParentQuoteFromOrder($this->orderMock));
    }

    /**
     * @test
     * that getQuoteFromOrder returns order quote by loading order quote id
     *
     * @covers ::getQuoteFromOrder
     *
     * @throws ReflectionException
     */
    public function getQuoteFromOrder_withValidOrder_returnsQuoteFromOrder()
    {
        $this->orderMock->expects($this->once())->method('getQuoteId')->willReturn(self::PARENT_QUOTE_ID);
        TestHelper::stubModel('sales/quote', $this->parentQuoteMock);
        $this->parentQuoteMock->expects($this->once())->method('loadByIdWithoutStore')->with(self::PARENT_QUOTE_ID)
            ->willReturnSelf();
        $this->assertSame($this->parentQuoteMock, $this->currentMock->getQuoteFromOrder($this->orderMock));
    }

    /**
     * @test
     * that getOrderByQuoteId returns order by provided quote id
     *
     * @covers ::getOrderByQuoteId
     *
     * @throws Exception if unable to create/delete dummy product or dummy order
     */
    public function getOrderByQuoteId_existingOrder_returnsOrderFromDatabase()
    {
        $dummyProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('ordergetorderbyquoteid'));
        $dummyOrder = Bolt_Boltpay_OrderHelper::createDummyOrder($dummyProductId);
        $actualOrder = $this->currentMock->getOrderByQuoteId($dummyOrder->getQuoteId());
        $this->assertInstanceOf('Mage_Sales_Model_Order', $actualOrder);
        $this->assertEquals($dummyOrder->getId(), $actualOrder->getId());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($dummyOrder);
        Bolt_Boltpay_ProductProvider::deleteDummyProduct($dummyProductId);
    }

    /**
     * @test
     * that getOrderByQuoteId returns new order object if existing cannot be found
     *
     * @covers ::getOrderByQuoteId
     *
     * @throws Exception if unable to create/delete dummy product or dummy order
     */
    public function getOrderByQuoteId_orderNotFound_returnsNewEmptyOrderObject()
    {
        $actualOrder = $this->currentMock->getOrderByQuoteId(mt_rand());
        $this->assertInstanceOf('Mage_Sales_Model_Order', $actualOrder);
        $this->assertTrue($actualOrder->isObjectNew());
    }

    /**
     * Setup method for test covering {@see Bolt_Boltpay_Model_Order::validateCoupons}
     * Stubs return values for Rule and Coupon model loading with provided maps
     *
     * @param array $ruleConfig determines the returned value of {@see Mage_SalesRule_Model_Rule::load}
     * @param array $couponConfig determines the returned value of {@see Mage_SalesRule_Model_Coupon::load}
     *
     * @return MockObject[]|Mage_SalesRule_Model_Rule[]|Mage_SalesRule_Model_Coupon[] mocked instance of Coupon and Rule models
     *
     * @throws ReflectionException if unable to stub models
     */
    private function validateCouponsSetUp($ruleConfig = array(), $couponConfig = array())
    {
        $ruleModelMock = $this->getClassPrototype('salesrule/rule')->getMock();
        $couponModelMock = $this->getClassPrototype('salesrule/coupon')->getMock();
        $ruleModelMock->method('load')->with($ruleConfig['ruleId'])->willReturn($ruleConfig['rule']);
        $couponModelMock->method('load')->with($couponConfig['reference'], 'code')->willReturn($couponConfig['coupon']);
        TestHelper::stubModel('salesrule/rule', $ruleModelMock);
        TestHelper::stubModel('salesrule/coupon', $couponModelMock);
        return array($ruleModelMock, $couponModelMock);
    }

    /**
     * @test
     * that validateCoupons sets flag for skipping discount and shipping validation totals
     * when one of the applied rules affects shipping
     *
     * @covers ::validateCoupons
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     */
    public function validateCoupons_whenRuleAppliesToShipping_setsSkipValidationFlag()
    {
        $ruleConfig = array(
            'ruleId' => '1',
            'rule' => Mage::getModel(
                'salesrule/rule',
                array('apply_to_shipping' => true)
            )
        );
        $this->validateCouponsSetUp($ruleConfig);
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(1);
        $transaction = new stdClass();
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertTrue($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that validateCoupons sets flag for skipping discount and shipping validation totals
     * when one of the applied rules is by percent and tax discount is enabled
     *
     * @covers ::validateCoupons
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     * @throws Mage_Core_Model_Store_Exception
     */
    public function validateCoupons_ifDiscountAppliesToTaxAndRuleIsByPercent_setsSkipValidationFlag()
    {
        $ruleConfig = array(
            'ruleId' => '1',
            'rule' => Mage::getModel(
                'salesrule/rule',
                array('apply_to_shipping' => false, 'simple_action' => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            )
        );
        $this->validateCouponsSetUp($ruleConfig);
        TestHelper::stubConfigValue('tax/calculation/discount_tax', true);
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(1);
        $transaction = new stdClass();
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertTrue($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that validateCoupons throws OrderCreationException if one of the provided coupons does not exist
     *
     * @covers ::validateCoupons
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001007
     * @expectedExceptionMessage {"discount_code": "11235813"}
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     */
    public function validateCoupons_whenCodeDoesNotExist_throwsException()
    {
        $this->validateCouponsSetUp(
            $ruleConfig =
                array( 'ruleId' => '25', 'rule' => Mage::getModel('salesrule/rule')),
            $couponConfig =
                array( 'reference' => self::COUPON_CODE, 'coupon' => Mage::getModel('salesrule/coupon'))
        );
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(25);
        $transaction = new stdClass();
        $transaction->order->cart->discounts = array(
            (object)array('reference' => self::COUPON_CODE)
        );
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertTrue($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that validateCoupons throws OrderCreationException if one of the provided coupons has expired
     *
     * @covers ::validateCoupons
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001006
     * @expectedExceptionMessage {"reason": "This coupon has expired", "discount_code": "11235813"}
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     */
    public function validateCoupons_withExpiredCoupon_throwsException()
    {
        $this->validateCouponsSetUp(
            $ruleConfig =
                array(
                    'ruleId' => '17',
                    'rule' => Mage::getModel(
                        'salesrule/rule',
                        array('to_date' => Mage::getSingleton('core/date')->date(null, '-10 days'))
                    )
                ),
            $couponConfig =
                array(
                    "reference" => self::COUPON_CODE,
                    "coupon" => Mage::getModel('salesrule/coupon', array('coupon_id' => 1, 'rule_id' => 17))
                )
        );
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(17);
        $transaction = new stdClass();
        $transaction->order->cart->discounts = array(
            (object)array('reference' => self::COUPON_CODE)
        );
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertTrue($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that validateCoupons throws OrderCreationException if one of the provided coupons to not
     * meet the designated pre-conditions
     *
     * @covers ::validateCoupons
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001006
     * @expectedExceptionMessage {"reason": "Coupon criteria was not met.", "discount_code": "11235813"}
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     */
    public function validateCoupons_withUnmetRule_throwsException()
    {

        $this->validateCouponsSetUp(
            $ruleConfig =
                array(
                    'ruleId' => '24',
                    'rule' => Mage::getModel(
                        'salesrule/rule',
                        array('is_active' => 0)
                    )
                ),
            $couponConfig =
                array(
                    "reference" => self::COUPON_CODE,
                    "coupon" => Mage::getModel('salesrule/coupon', array('coupon_id' => 1, 'rule_id' => 24))
                )
        );
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(24);
        $transaction = new stdClass();
        $transaction->order->cart->discounts = array(
            (object)array('reference' => self::COUPON_CODE)
        );
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertTrue($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that if all coupons are valid - validation passes successfully
     *
     * @covers ::validateCoupons
     *
     * @throws ReflectionException if validateCoupons method doesn't exist
     */
    public function validateCoupons_couponsValid_validationSucceeds()
    {
        $this->validateCouponsSetUp(
            $ruleConfig =
                array(
                    'ruleId' => '1',
                    'rule' => Mage::getModel(
                        'salesrule/rule',
                        array('to_date' => Mage::getSingleton('core/date')->date(null, '+1 days'))
                    )
                ),
            $couponConfig =
                array(
                    "reference" => self::COUPON_CODE,
                    "coupon" => Mage::getModel('salesrule/coupon', array('coupon_id' => 1, 'rule_id' => 1))
                )
        );
        $this->immutableQuoteMock->method('getAppliedRuleIds')->willReturn(1);
        $transaction = new stdClass();
        $transaction->order->cart->discounts = array(
            (object)array('reference' => self::COUPON_CODE),
        );
        $this->immutableQuoteMock->expects($this->exactly(2))->method('getCouponCode')->willReturn(self::COUPON_CODE);
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCoupons',
            array($this->immutableQuoteMock, $transaction)
        );
        $this->assertFalse($transaction->shouldSkipDiscountAndShippingTotalValidation);
    }

    /**
     * @test
     * that removeOrderTimeStamps sets updated_at and created_at fields in the database to null for provided order
     *
     * @covers ::removeOrderTimeStamps
     *
     * @throws Exception if unable to create/delete dummy order and dummy product
     */
    public function removeOrderTimeStamps_ifOrderExists_removesTimestamps()
    {
        $dummyProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('ordergetorderbyquoteid'));
        $dummyOrder = Bolt_Boltpay_OrderHelper::createDummyOrder($dummyProductId);
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'removeOrderTimeStamps',
            array($dummyOrder)
        );
        /** @var Mage_Sales_Model_Order $dummyOrder */
        $dummyOrder = Mage::getModel('sales/order')->load($dummyOrder->getId());
        $this->assertNull($dummyOrder->getCreatedAt());
        $this->assertNull($dummyOrder->getUpdatedAt());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($dummyOrder);
        Bolt_Boltpay_ProductProvider::deleteDummyProduct($dummyProductId);

    }

    /**
     * @test
     * that removeOrderTimeStamps only logs exceptions thrown when executing database query
     *
     * @covers ::removeOrderTimeStamps
     *
     * @throws ReflectionException if removeOrderTimeStamps method doesn't exist
     * @throws Exception if unable to stub singleton
     */
    public function removeOrderTimeStamps_queryThrowsException_logsException()
    {
        $resourceMock = $this->getClassPrototype('core/resource')
            ->setMethods(array('getConnection', 'getTableName'))->getMock();
        $writeConnectionMock = $this->getClassPrototype('Magento_Db_Adapter_Pdo_Mysql')
            ->setMethods(array('query'))->getMock();
        $resourceMock->method('getConnection')->with('core_write')->willReturn($writeConnectionMock);
        $dbException = new Zend_Db_Adapter_Exception('Expected exception');
        $writeConnectionMock->method('query')->willThrowException($dbException);
        TestHelper::stubSingleton('core/resource', $resourceMock);

        $this->boltHelperMock->expects($this->once())->method('notifyException')
            ->with($dbException, array(), 'warning');
        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($dbException->getMessage());
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'removeOrderTimeStamps',
            array(Mage::getModel('sales/order', array('entity_id' => '1')))
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Order::validateBeforeOrderCommit}
     *
     * @param Mage_Sales_Model_Order $order to be packed into event
     * @param object                 $transaction to be returned from immutable quote
     * @return Varien_Event_Observer parameter containing order to be passed to event observer
     */
    private function validateBeforeOrderCommitSetUp($order, $transaction)
    {
        $event = new Varien_Event(array('order' => $order));
        $observer = new Varien_Event_Observer();
        $observer->setName('Bolt_Failed_Payment_Observer')->setEvent($event);
        $event->addObserver($observer);
        $this->immutableQuoteMock->method('getTransaction')->willReturn($transaction);
        $this->orderMock->method('getQuote')->willReturn($this->immutableQuoteMock);
        $this->boltHelperMock->method('getExtraConfig')->with('priceFaultTolerance')->willReturn(1);
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        return $observer;
    }

    /**
     * @test
     * that if order object provided to validateBeforeOrderCommit is considered empty an exception is thrown
     *
     * @covers ::validateBeforeOrderCommit
     *
     * @expectedException Exception
     * @expectedExceptionMessage Order was not able to be saved
     *
     * @throws Exception if order is empty
     */
    public function validateBeforeOrderCommit_orderIsEmpty_throwsException()
    {
        $transaction = new stdClass();
        $observer = $this->validateBeforeOrderCommitSetUp(null, $transaction);
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that if payment method is not Bolt - validation is skipped
     *
     * @covers ::validateBeforeOrderCommit
     *
     * @throws Bolt_Boltpay_OrderCreationException from method tested if the bottom line price total differs by allowed tolerance
     */
    public function validateBeforeOrderCommit_ifPaymentMethodIsNotBolt_skipValidation()
    {
        $transaction = new stdClass();
        $transaction->order->cart->total_amount->amount = 500;
        $this->orderMock->expects($this->atLeastOnce())->method('getPayment')
            ->willReturn(
                Mage::getModel('sales/order_payment', array('method' => 'checkmo'))
            );
        $this->orderMock->expects($this->never())->method('getGrandTotal')->willReturn(25);
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, $transaction);
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that validateBeforeOrderCommit throws an exception if a Bolt order
     * is attempted from route that is not a hook nor in admin scope
     *
     * @covers ::validateBeforeOrderCommit
     *
     * @expectedException Exception
     * @expectedExceptionMessage Order creation with Boltpay not allowed for this path
     */
    public function validateBeforeOrderCommit_whenBoltOrderNotFromHookNorFromAdmin_throwsException()
    {
        $transaction = new stdClass();
        $this->paymentMock->expects($this->atLeastOnce())->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, $transaction);
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        Mage::app()->setCurrentStore('default');
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that validateBeforeOrderCommit does not throw exception if a Bolt order
     * is attempted from route that is in admin scope
     *
     * @covers ::validateBeforeOrderCommit
     */
    public function validateBeforeOrderCommit_whenBoltOrderFromAdmin_continuesValidation()
    {
        $transaction = new stdClass();
        $this->paymentMock->expects($this->atLeastOnce())->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, $transaction);
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        Mage::app()->setCurrentStore('admin');
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that if transaction is empty - validation is skipped
     *
     * @covers ::validateBeforeOrderCommit
     */
    public function validateBeforeOrderCommit_ifTransactionIsEmpty_skipsValidation()
    {
        $this->paymentMock->expects($this->atLeastOnce())->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $this->orderMock->expects($this->never())->method('getGrandTotal');
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, null);
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that validateBeforeOrderCommit method throws {@see Bolt_Boltpay_OrderCreationException} if totals are not matched
     * between Bolt and Magento higher than tolerance amount
     *
     * @covers ::validateBeforeOrderCommit
     *
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionCode 2001003
     * @expectedExceptionMessage {"reason": "Grand total has changed", "old_value": "500", "new_value": "2500"}
     */
    public function validateBeforeOrderCommit_whenTotalsMismatchBeyondTolerance_throwsException()
    {
        $transaction = new stdClass();
        $transaction->order->cart->total_amount->amount = 500;
        $this->orderMock->expects($this->once())->method('getGrandTotal')->willReturn(25);
        $this->paymentMock->expects($this->atLeastOnce())->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, $transaction);
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that if totals mismatch is inside tolerance totals are adjusted by the mismatch difference
     *
     * @covers ::validateBeforeOrderCommit
     */
    public function validateBeforeOrderCommit_whenTotalsMismatchBelowTolerance_correctsTotals()
    {
        $transaction = new stdClass();
        $transaction->order->cart->total_amount->amount = 500;
        $this->paymentMock->expects($this->atLeastOnce())->method('getMethod')
            ->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $this->orderMock->expects($this->once())->method('getGrandTotal')->willReturn(4.99);
        $this->orderMock->expects($this->once())->method('getTaxAmount')->willReturn(0.99);
        $this->orderMock->expects($this->once())->method('getBaseTaxAmount')->willReturn(0.99);
        $this->orderMock->expects($this->once())->method('setTaxAmount')->with(1)->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setBaseTaxAmount')->with(1)->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setGrandTotal')->with(5)->willReturnSelf();
        $this->orderMock->expects($this->once())->method('setBaseGrandTotal')->with(5)->willReturnSelf();
        $observer = $this->validateBeforeOrderCommitSetUp($this->orderMock, $transaction);
        $this->currentMock->validateBeforeOrderCommit($observer);
    }

    /**
     * @test
     * that receiveOrder activates received order and sets Bolt user id by default for order
     * with the {@see Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING} status
     *
     * @covers ::receiveOrder
     * @covers ::activateOrder
     *
     * @throws Mage_Core_Exception from method tested if there is a problem retrieving the bolt transaction reference from the payload
     * @throws Exception if test class name is not defined
     */
    public function receiveOrder_forBoltPendingPaymentOrders_activatesOrderAndSetsBoltUserId()
    {
        $payload = new stdClass();
        $payload->notification_type = Bolt_Boltpay_Model_Payment::HOOK_TYPE_PENDING;

        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getQuoteFromOrder', 'getParentQuoteFromOrder', 'sendOrderEmail', 'boltHelper'))
            ->getMock();
        $currentMock->method('getQuoteFromOrder')->with($this->orderMock)->willReturn($this->immutableQuoteMock);
        $currentMock->method('getParentQuoteFromOrder')->with($this->orderMock)->willReturn($this->parentQuoteMock);
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        
        $this->orderMock->expects($this->once())->method('getCreatedAt')->willReturn(null);
        $this->orderMock->expects($this->once())->method('setCreatedAt')->with($this->anything())->willReturnSelf();
        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->parentQuoteMock->expects($this->once())->method('setIsActive')->with(false)->willReturnSelf();
        $this->parentQuoteMock->expects($this->once())->method('save')->willReturnSelf();

        $this->immutableQuoteMock->expects($this->once())->method('setTotalsCollectedFlag')->with(true)
            ->willReturnSelf();
        $this->immutableQuoteMock->expects($this->once())->method('prepareRecurringPaymentProfiles');
        $this->immutableQuoteMock->expects($this->atLeastOnce())->method('setInventoryProcessed')->with(true);

        $this->paymentMock->expects($this->once())->method('setAdditionalInformation')->willReturnSelf();
        $this->paymentMock->expects($this->once())->method('save')->willReturnSelf();
        $currentMock->receiveOrder($this->orderMock, $payload);
    }

    /**
     * @test
     * if an exception occurs during order activation process during 3rd-party submission
     * event handling, the problem is logged and activation continues
     * @see Bolt_Boltpay_Helper_BugsnagTrait
     *
     * @covers ::activateOrder
     *
     * @throws ReflectionException if activateOrder method doesn't exist
     * @throws Exception if test class name is not defined
     */
    public function activateOrder_withSubmitEventProblems_logsAndNotifiesException()
    {
        $exception = new Exception('Expected exception');
        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getParentQuoteFromOrder', 'getQuoteFromOrder', 'boltHelper', 'sendOrderEmail'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->orderMock->expects($this->once())->method('getCreatedAt')->willReturn(null);
        $this->orderMock->expects($this->once())->method('setCreatedAt')->with($this->anything())->willReturnSelf();
        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->parentQuoteMock->expects($this->once())->method('setIsActive')->with(0)->willReturnSelf();
        $this->parentQuoteMock->expects($this->once())->method('save')->willReturnSelf();
        $this->parentQuoteMock->expects($this->once())->method('delete');

        $this->paymentMock->expects($this->once())->method('setAdditionalInformation')->willReturnSelf();
        $this->paymentMock->expects($this->once())->method('save')->willReturnSelf();

        $currentMock->method('getParentQuoteFromOrder')->with($this->orderMock)->willReturn($this->parentQuoteMock);
        $currentMock->method('getQuoteFromOrder')->with($this->orderMock)->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        $currentMock->expects($this->once())->method('sendOrderEmail')->with($this->orderMock);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'activateOrder',
            array(
                $this->orderMock,
                (object)array('notification_type' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING)
            )
        );
    }

    /**
     * @test
     * if an exception occurs during order activation process during parent quote removal,
     * the problem is logged and activation continues
     * @see Bolt_Boltpay_Helper_BugsnagTrait
     *
     * @covers ::activateOrder
     *
     * @throws ReflectionException if activateOrder method doesn't exist
     * @throws Exception if test class name is not defined
     */
    public function activateOrder_withParentQuoteRemovalProblems_logsAndNotifiesException()
    {
        $exception = new Exception('Expected exception');
        $exception2 = new Exception('Second expected exception');

        /** @var MockObject|Bolt_Boltpay_Model_Order $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getParentQuoteFromOrder', 'getQuoteFromOrder', 'boltHelper', 'sendOrderEmail'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->orderMock->expects($this->once())->method('getCreatedAt')->willReturn(null);
        $this->orderMock->expects($this->once())->method('setCreatedAt')->with($this->anything())->willReturnSelf();
        $this->orderMock->expects($this->once())->method('save')->willReturnSelf();

        $this->paymentMock->expects($this->once())->method('setAdditionalInformation')->willReturnSelf();
        $this->paymentMock->expects($this->once())->method('save')->willReturnSelf();

        $currentMock->method('getQuoteFromOrder')->with($this->orderMock)->willThrowException($exception);
        $currentMock->method('getParentQuoteFromOrder')->with($this->orderMock)->willThrowException($exception2);

        $this->boltHelperMock->expects($this->exactly(2))->method('notifyException')->withConsecutive(
            $exception,
            $exception2
        );
        $this->boltHelperMock->expects($this->exactly(2))->method('logException')->withConsecutive(
            $exception,
            $exception2
        );

        $currentMock->expects($this->once())->method('sendOrderEmail')->with($this->orderMock);

        TestHelper::callNonPublicFunction(
            $currentMock,
            'activateOrder',
            array(
                $this->orderMock,
                (object)array('notification_type' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING)
            )
        );
    }

    /**
     * @test
     *
     * @covers ::applyShipmentToQuoteFromTransaction
     *
     * @throws ReflectionException
     */
    public function applyShipmentToQuoteFromTransaction_withVirtualCart_doesNotApplyShipment()
    {
        $this->boltShippingAndTax->expects($this->never())->method('applyBoltAddressData');
        TestHelper::stubModel('boltpay/shippingAndTax', $this->boltShippingAndTax);

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'applyShipmentToQuoteFromTransaction',
            array(
                $this->immutableQuoteMock,
                new stdClass(),
                true
            )
        );

        TestHelper::restoreModel('boltpay/shippingAndTax');
    }

    /**
     * @test
     *
     * @covers ::applyShipmentToQuoteFromTransaction
     *
     * @throws ReflectionException
     */
    public function applyShipmentToQuoteFromTransaction_withNoShippingMethodCode_callsNotifyExceptionAndLogWarning()
    {
        $shippingAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
            ->setMethods(array('getAllShippingRates'))
            ->getMock();
        $shippingAddressMock->method('getAllShippingRates')->willReturn(array());
        $this->immutableQuoteMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->boltShippingAndTax->expects($this->once())->method('applyBoltAddressData');
        $this->boltShippingAndTax->expects($this->never())->method('applyShippingRate');
        TestHelper::stubModel('boltpay/shippingAndTax', $this->boltShippingAndTax);
        $this->boltHelperMock->expects($this->once())->method('logWarning');
        $this->boltHelperMock->expects($this->once())->method('notifyException');

        $transaction = new stdClass();
        $transaction->order = new stdClass();
        $transaction->order->cart = new stdClass();
        $transaction->order->cart->shipments = array(new stdClass());
        $transaction->order->cart->shipments[0]->service = 'fake service';
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'applyShipmentToQuoteFromTransaction',
            array(
                $this->immutableQuoteMock,
                $transaction,
                false
            )
        );

        TestHelper::restoreModel('boltpay/shippingAndTax');
    }

    /**
     * @test
     *
     * @covers ::applyShipmentToQuoteFromTransaction
     */
    public function applyShipmentToQuoteFromTransaction_withNoReferencePassedIn_fallsBackToService()
    {
        $rateMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address_Rate')
            ->setMethods(array('getCarrierTitle', 'getCarrier', 'getMethodTitle', 'getMethod'))
            ->getMock();
        $rateMock->method('getCarrierTitle')->willReturn('Fedex');
        $rateMock->method('getCarrier')->willReturn('fedex');
        $rateMock->method('getMethodTitle')->willReturn('Ground');
        $rateMock->method('getMethod')->willReturn('ground');
        $shippingAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
            ->setMethods(array('getAllShippingRates'))
            ->getMock();
        $shippingAddressMock->method('getAllShippingRates')->willReturn(array($rateMock));
        $this->immutableQuoteMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $this->boltShippingAndTax->expects($this->once())->method('applyBoltAddressData');
        $this->boltShippingAndTax->expects($this->once())->method('applyShippingRate')
            ->with($this->immutableQuoteMock, 'fedex_ground', true);
        TestHelper::stubModel('boltpay/shippingAndTax', $this->boltShippingAndTax);

        $transaction = new stdClass();
        $transaction->order = new stdClass();
        $transaction->order->cart = new stdClass();
        $transaction->order->cart->shipments = array(new stdClass());
        $transaction->order->cart->shipments[0]->service = 'Fedex - Ground';
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'applyShipmentToQuoteFromTransaction',
            array(
                $this->immutableQuoteMock,
                $transaction,
                false
            )
        );

        TestHelper::restoreModel('boltpay/shippingAndTax');
    }

    /**
     * @test
     *
     * @covers ::applyShipmentToQuoteFromTransaction
     *
     * @throws ReflectionException
     */
    public function applyShipmentToQuoteFromTransaction_withShippingMethodCode_callsApplyShippingRate()
    {
        $this->boltShippingAndTax->expects($this->once())->method('applyBoltAddressData');
        $this->boltShippingAndTax->expects($this->once())->method('applyShippingRate')
            ->with($this->immutableQuoteMock, 'ground', false);
        TestHelper::stubModel('boltpay/shippingAndTax', $this->boltShippingAndTax);

        $transaction = new stdClass();
        $transaction->order = new stdClass();
        $transaction->order->cart = new stdClass();
        $transaction->order->cart->shipments = array(new stdClass());
        $transaction->order->cart->shipments[0]->reference = 'ground';
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'applyShipmentToQuoteFromTransaction',
            array(
                $this->immutableQuoteMock,
                $transaction,
                true
            )
        );

        TestHelper::restoreModel('boltpay/shippingAndTax');
    }
}