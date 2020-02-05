<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');
require_once('CouponHelper.php');
require_once('OrderHelper.php');

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
     */
    public static function setUpBeforeClass()
    {
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
    public function sendOrderEmail_emailNotSentAlready_queuesNewOrderEmail()
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
    public function sendOrderEmail_emailQueueThrowsException_logsException()
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
    public function setBoltUserId_customerBoltIdIsNull_setsCustomerBoltUserIdFromSession()
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
    public function setBoltUserId_customerCannotBeSaved_logsException()
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
    public function validateTotals_discountDiffersWithinTolerance_logsWarning()
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
    public function validateTotals_discountDiffersBeyondTolerance_throwsOrderCreationException()
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
    public function validateTotals_couponDiscountDiffersBeyondTolerance_throwsOrderCreationException()
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
    public function validateTotals_itemProductPriceUpdated_throwsOrderCreationException()
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
    public function validateTotals_taxTotalMismatchBeyondTolerance_throwsOrderCreationException()
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
    public function validateTotals_taxTotalMismatchUnderTolerance_logsAndNotifiesWarning()
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
    public function validateTotals_shippingTotalsMismatchBeyondTolerance_throwsOrderCreationException()
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
    public function validateTotals_shippingTotalsMismatchUnderTolerance_logsAndNotifiesWarning()
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
}