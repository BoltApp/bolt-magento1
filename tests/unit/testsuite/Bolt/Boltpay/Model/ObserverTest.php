<?php
require_once('TestHelper.php');
require_once('MockingTrait.php');
require_once('OrderHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Observer
 */
class Bolt_Boltpay_Model_ObserverTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int Dummy order id */
    const ORDER_ID = 123456;
    /** @var int Dummy order increment id */
    const INCREMENT_ID = 100000001;
    /** @var int Dummy immutable quote id */
    const IMMUTABLE_QUOTE_ID = 456;
    /** @var int Dummy parent quote id */
    const QUOTE_ID = 455;
    /** @var string Dummy Bolt transaction reference */
    const TRANSACTION_REFERENCE = 'AAAA-BBBB-CCCC-DDDD';

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Observer';

    /**
     * @var MockObject|Bolt_Boltpay_Model_Observer  The mocked instance the test class
     */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_FeatureSwitch
     */
    private $featureSwitchMock;

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of the Bolt helper
     */
    private $boltHelperMock;

    /**
     * Setup mock of the observer and Bolt helper
     * @inheritdoc
     *
     * @throws Exception if test class name is not defined
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper'))
            ->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array(
                    'getExtraConfig',
                    'logException',
                    'logWarning',
                    'notifyException',
                    'verify_hook',
                    'fetchTransaction'
                )
            )
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
    }

    /**
     * Generate dummy products for testing purposes
     * @inheritdoc
     *
     * @throws Exception if unable to create dummy product
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'), array(), 100);
    }

    /**
     * Delete dummy products after the test
     * @inheritdoc
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
     */
    public static function tearDownAfterClass()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Restore originals for stubbed  values
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that Mage::getModel('boltpay/observer') returns instance of the observer
     *
     * @coversNothing
     */
    public function __construct_always_createsInstanceOfTheModel()
    {
        $observer = Mage::getModel('boltpay/observer');
        $this->assertInstanceOf('Bolt_Boltpay_Model_Observer', $observer);
    }

    /**
     * @test
     * that initializeBenchmarkProfiler initializes benchmark function on controller_front_init_before event
     * and that benchmark function calls {@see Bolt_Boltpay_Helper_LoggerTrait::logBenchmark}
     * If benchmark was already initialized in previous tests only validates benchmarking functionality
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @covers ::initializeBenchmarkProfiler
     *
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws ReflectionException if benchmark function is not defined
     */
    public function initializeBenchmarkProfiler_ifNotInitialized_initializesBenchmarkProfile()
    {
        $label = 'Test Label';
        $shouldLogIndividually = false;
        $shouldIncludeInFullLog = true;
        $shouldFlushFullLog = false;
        $this->currentMock->initializeBenchmarkProfiler();

        $this->assertTrue(function_exists('benchmark'));
        $this->assertTrue(Mage::registry('initializedBenchmark'));
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('logBenchmark'))
            ->getMock();
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        //verify signature to prevent fatal errors
        $benchmarkFunctionReflection = new ReflectionFunction('benchmark');
        $this->assertEquals(
            array(
                'label',
                'shouldLogIndividually',
                'shouldIncludeInFullLog',
                'shouldFlushFullLog',
            ),
            array_map(
                function ($param) {
                    /** @var ReflectionParameter $param */
                    return $param->getName();
                },
                $benchmarkFunctionReflection->getParameters()
            )
        );
        $boltHelperMock->expects($this->once())->method('logBenchmark')
            ->with($label, $shouldLogIndividually, $shouldIncludeInFullLog, $shouldFlushFullLog);
        benchmark($label, $shouldLogIndividually, $shouldIncludeInFullLog, $shouldFlushFullLog);
    }

    /**
     * Stubs boltpay/featureSwitch model for tests covering {@see Bolt_Boltpay_Model_Observer::updateFeatureSwitches}
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    private function updateFeatureSwitchesSetUp()
    {
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('updateFeatureSwitches'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
    }

    /**
     * @test
     * that updateFeatureSwitches calls {@see Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * if should update feature switches flag is set to true
     *
     * @covers ::updateFeatureSwitches
     *
     * @throws Mage_Core_Exception if unable to stub or restore feature switch singleton
     */
    public function updateFeatureSwitches_whenUpdateFeatureSwitchesFlagIsTrue_updatesFeatureSwitches()
    {
        $this->updateFeatureSwitchesSetUp();
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = true;
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches');
        $this->currentMock->updateFeatureSwitches();
    }

    /**
     * @test
     * that updateFeatureSwitches doesn't call {@see Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * if should update feature switches flag is set to false
     *
     * @covers ::updateFeatureSwitches
     *
     * @throws Mage_Core_Exception if unable to stub or restore feature switch singleton
     */
    public function updateFeatureSwitches_whenUpdateFeatureSwitchesFlagIsFalse_doesNotUpdateFeatureSwitches()
    {
        $this->updateFeatureSwitchesSetUp();
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = false;
        $this->featureSwitchMock->expects($this->never())->method('updateFeatureSwitches');
        $this->currentMock->updateFeatureSwitches();
    }

    /**
     * @test
     * that logFullBenchmarkProfile on controller_front_send_response_after event flushes full benchmark log
     *
     * @covers ::logFullBenchmarkProfile
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function logFullBenchmarkProfile_always_flushesFullBenchmarkLog()
    {
        $this->currentMock->initializeBenchmarkProfiler();
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('logBenchmark'))
            ->getMock();
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $boltHelperMock->expects($this->once())->method('logBenchmark')
            ->with(null, false, false, true);
        $this->currentMock->logFullBenchmarkProfile();;
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Observer::clearShoppingCartExceptPPCOrder}
     *
     * @param string $checkoutType to be set as checkoutType request parameter
     *
     * @return MockObject mocked instance of Mage::helper('checkout/cart')
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    private function clearShoppingCartExceptPPCOrderSetUp($checkoutType)
    {
        $cartHelperMock = $this->getClassPrototype('checkout/cart')
            ->setMethods(array('getCart', 'truncate', 'save'))->getMock();
        TestHelper::stubHelper('checkout/cart', $cartHelperMock);
        Mage::app()->getRequest()->setParam('checkoutType', $checkoutType);
        return $cartHelperMock;
    }

    /**
     * @test
     * that clearShoppingCartExceptPPCOrder on checkout_onepage_controller_success_action event
     * does not clear shopping cart and sets session quote id from parameters if checkoutType param is product-page
     *
     * @covers ::clearShoppingCartExceptPPCOrder
     *
     * @throws Mage_Core_Exception from setup if unable to stub helper
     */
    public function clearShoppingCartExceptPPCOrder_ifPPCOrder_doesNotClearCartAndSetsSessionQuoteId()
    {
        $cartHelperMock = $this->clearShoppingCartExceptPPCOrderSetUp(
            Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE
        );
        $cartHelperMock->expects($this->never())->method('getCart');
        Mage::dispatchEvent('checkout_onepage_controller_success_action');
        $this->clearShoppingCartExceptPPCOrderTearDown();
    }

    /**
     * @test
     * that clearShoppingCartExceptPPCOrder on checkout_onepage_controller_success_action event
     * does clears shopping cart if checkoutType param is not product-page
     *
     * @covers ::clearShoppingCartExceptPPCOrder
     *
     * @throws Mage_Core_Exception from setup if unable to stub helper
     */
    public function clearShoppingCartExceptPPCOrder_ifNotPPCOrder_clearsCart()
    {
        $cartHelperMock = $this->clearShoppingCartExceptPPCOrderSetUp('');
        $cartHelperMock->expects($this->once())->method('getCart')->willReturnSelf();
        $cartHelperMock->expects($this->once())->method('truncate')->willReturnSelf();
        $cartHelperMock->expects($this->once())->method('save')->willReturnSelf();
        Mage::dispatchEvent('checkout_onepage_controller_success_action');
        $this->clearShoppingCartExceptPPCOrderTearDown();
    }

    /**
     * Tear down for tests covering {@see Bolt_Boltpay_Model_Observer::clearShoppingCartExceptPPCOrder}
     */
    private function clearShoppingCartExceptPPCOrderTearDown()
    {
        Mage::getSingleton('checkout/session')->setQuoteId(null);
        Mage::app()->getRequest()->setParams(array('checkoutType' => null, 'session_quote_id' => null));
    }

    /**
     * @test
     * that clearCartCacheOnOrderCanceled clears cached cart data on session on controller_action_predispatch event
     * if session quote has parent quote id
     *
     * @covers ::clearCartCacheOnOrderCanceled
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function clearCartCacheOnOrderCanceled_withParentQuoteIdAndQuoteActive_clearsCartCache()
    {
        $quoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods(array('getParentQuoteId', 'getIsActive', 'setParentQuoteId', 'save'))
            ->getMock();
        $quoteMock->expects($this->once())->method('getParentQuoteId')->willReturn(455);
        $quoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $quoteMock->expects($this->once())->method('setParentQuoteId')->with(null)->willReturnSelf();
        $quoteMock->expects($this->once())->method('save');
        $sessionMock = $this->getClassPrototype('core/session')
            ->setMethods(array('unsCachedCartData', 'getQuote'))
            ->getMock();
        $sessionMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        TestHelper::stubSingleton('checkout/session', $sessionMock);
        TestHelper::stubSingleton('core/session', $sessionMock);

        Mage::app()->addEventArea('frontend');
        Mage::dispatchEvent(
            'controller_action_predispatch',
            array(
                'controller_action' => new Mage_Core_Controller_Front_Action(
                    Mage::app()->getRequest(),
                    Mage::app()->getResponse()
                )
            )
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Model_Observer::setSuccessSessionData}
     *
     * @return MockObject|Mage_Checkout_Model_Session mocked instance of checkout session
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    private function setSuccessSessionDataSetUp()
    {
        $checkoutSessionMock = $this->getClassPrototype('checkout/session')
            ->setMethods(
                array(
                    'getQuote',
                    'setLastQuoteId',
                    'setLastSuccessQuoteId',
                    'setLastOrderId',
                    'setLastRealOrderId',
                    'setLastRecurringProfileIds',
                    'clearHelperData',
                )
            )->getMock();
        TestHelper::stubSingleton('checkout/session', $checkoutSessionMock);
        return $checkoutSessionMock;
    }

    /**
     * @test
     * that setSuccessSessionData notifies OrderCreationException and returns control to Magento
     * if hook verification fails
     *
     * @covers ::setSuccessSessionData
     *
     * @throws Exception from tested method if quote totals have not been properly collected
     */
    public function setSuccessSessionData_whenHookVerificationFails_orderCreationExceptionIsLogged()
    {
        $checkoutSessionMock = $this->setSuccessSessionDataSetUp();
        Mage::app()->getRequest()->setParam('bolt_payload', 'test');
        $this->boltHelperMock->expects($this->once())->method('verify_hook')->willReturn(false);
        $exception = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
        );
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception, array(), 'warning');
        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($exception->getMessage());
        $checkoutSessionMock->expects($this->never())->method('clearHelperData');
        $checkoutSessionMock->expects($this->never())->method('setLastQuoteId');
        $checkoutSessionMock->expects($this->never())->method('setLastSuccessQuoteId');
        $checkoutSessionMock->expects($this->never())->method('setLastOrderId');
        $checkoutSessionMock->expects($this->never())->method('setLastRealOrderId');
        $checkoutSessionMock->expects($this->never())->method('setLastRecurringProfileIds');
        $this->currentMock->setSuccessSessionData(new Varien_Event_Observer());
        Mage::app()->getRequest()->clearParams();
    }

    /**
     * @test
     * that setSuccessSessionData sets session data related to previous order from session quote
     *
     * @covers ::setSuccessSessionData
     *
     * @throws Exception from tested method if quote totals have not been properly collected
     */
    public function setSuccessSessionData_whenBoltPayloadInParamsAndHookIsVerified_setsSuccessSessionData()
    {
        $checkoutSessionMock = $this->setSuccessSessionDataSetUp();
        Mage::app()->getRequest()->setParams(
            array(
                'bolt_payload' => 'test',
                'lastQuoteId' => self::IMMUTABLE_QUOTE_ID,
                'lastSuccessQuoteId' => self::IMMUTABLE_QUOTE_ID,
                'lastOrderId' => self::ORDER_ID,
                'lastRealOrderId' => self::INCREMENT_ID,
            )
        );
        $checkoutSessionMock->expects($this->once())->method('getQuote')
            ->willReturn(
                Mage::getModel(
                    'sales/quote',
                    array('entity_id' => self::QUOTE_ID, 'parent_quote_id' => self::IMMUTABLE_QUOTE_ID)
                )
            );
        $immutableQuoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods(array('loadByIdWithoutStore', 'collectTotals', 'prepareRecurringPaymentProfiles'))
            ->getMock();
        $immutableQuoteMock->expects($this->once())->method('loadByIdWithoutStore')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $immutableQuoteMock->expects($this->once())->method('collectTotals')->willReturnSelf();
        $immutableQuoteMock->expects($this->once())->method('prepareRecurringPaymentProfiles')->willReturn(
            array(
                Mage::getModel('sales/recurring_profile', array('profile_id' => 234)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 678)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 876)),
            )
        );
        TestHelper::stubModel('sales/quote', $immutableQuoteMock);
        $this->boltHelperMock->expects($this->once())->method('verify_hook')->willReturn(true);
        $this->boltHelperMock->expects($this->never())->method('notifyException');
        $this->boltHelperMock->expects($this->never())->method('logWarning');
        $checkoutSessionMock->expects($this->once())->method('clearHelperData');
        $checkoutSessionMock->expects($this->once())->method('setLastQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastSuccessQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastOrderId')->with(self::ORDER_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRealOrderId')->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRecurringProfileIds')
            ->with(array(234, 678, 876))->willReturnSelf();
        $this->currentMock->setSuccessSessionData(new Varien_Event_Observer());
        Mage::app()->getRequest()->clearParams();
    }

    /**
     * @test
     * that setSuccessSessionData sets session data related to previous order for orphaned or legacy transactions
     * by retrieving required information from transaction
     *
     * @covers ::setSuccessSessionData
     *
     * @throws Exception from tested method if quote totals have not been properly collected
     */
    public function setSuccessSessionData_whenBoltTransactionInParams_setsSuccessSessionData()
    {
        $checkoutSessionMock = $this->setSuccessSessionDataSetUp();
        Mage::app()->getRequest()->setParam('bolt_transaction_reference', self::TRANSACTION_REFERENCE);
        $boltOrderMock = $this->getClassPrototype('boltpay/order')
            ->setMethods(array('getQuoteById'))->getMock();
        TestHelper::stubModel('boltpay/order', $boltOrderMock);

        $dummyTransaction = new stdClass();
        $dummyTransaction->order->cart->display_id = self::INCREMENT_ID . '|' . self::IMMUTABLE_QUOTE_ID;
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with(self::TRANSACTION_REFERENCE)
            ->willReturn($dummyTransaction);

        $immutableQuoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods(array('collectTotals', 'prepareRecurringPaymentProfiles'))
            ->getMock();
        $immutableQuoteMock->expects($this->once())->method('collectTotals')->willReturnSelf();
        $immutableQuoteMock->expects($this->once())->method('prepareRecurringPaymentProfiles')->willReturn(
            array(
                Mage::getModel('sales/recurring_profile', array('profile_id' => 234)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 678)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 876)),
            )
        );
        $boltOrderMock->expects($this->once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($immutableQuoteMock);

        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('loadByIncrementId', 'isObjectNew', 'getId'))
            ->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with(self::INCREMENT_ID)->willReturnSelf();
        $orderMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $orderMock->expects($this->once())->method('getId')->willReturn(self::ORDER_ID);
        TestHelper::stubModel('sales/order', $orderMock);
        $checkoutSessionMock->expects($this->once())->method('clearHelperData');
        $checkoutSessionMock->expects($this->once())->method('setLastQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastSuccessQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastOrderId')->with(self::ORDER_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRealOrderId')->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRecurringProfileIds')
            ->with(array(234, 678, 876))->willReturnSelf();
        $this->currentMock->setSuccessSessionData(new Varien_Event_Observer());
        Mage::app()->getRequest()->clearParams();
    }

    /**
     * @test
     * that setSuccessSessionData sets session data related to previous order for orphaned or legacy transactions
     * by retrieving required information from transaction and using maximum value for order id if order is not found
     *
     * @covers ::setSuccessSessionData
     *
     * @throws Exception from tested method if quote totals have not been properly collected
     */
    public function setSuccessSessionData_forLegacyOrderIsNotCreated_setsSuccessSessionDataWithPseudoOrderId()
    {
        $checkoutSessionMock = $this->setSuccessSessionDataSetUp();
        Mage::app()->getRequest()->setParam('bolt_transaction_reference', self::TRANSACTION_REFERENCE);
        $boltOrderMock = $this->getClassPrototype('boltpay/order')
            ->setMethods(array('getQuoteById'))->getMock();
        TestHelper::stubModel('boltpay/order', $boltOrderMock);

        $dummyTransaction = new stdClass();
        $dummyTransaction->order->cart->display_id = self::INCREMENT_ID . '|' . self::IMMUTABLE_QUOTE_ID;
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with(self::TRANSACTION_REFERENCE)
            ->willReturn($dummyTransaction);

        $immutableQuoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods(array('collectTotals', 'prepareRecurringPaymentProfiles'))
            ->getMock();
        $immutableQuoteMock->expects($this->once())->method('collectTotals')->willReturnSelf();
        $immutableQuoteMock->expects($this->once())->method('prepareRecurringPaymentProfiles')->willReturn(
            array(
                Mage::getModel('sales/recurring_profile', array('profile_id' => 234)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 678)),
                Mage::getModel('sales/recurring_profile', array('profile_id' => 876)),
            )
        );
        $boltOrderMock->expects($this->once())->method('getQuoteById')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($immutableQuoteMock);

        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('loadByIncrementId', 'isObjectNew', 'getId'))
            ->getMock();
        $orderMock->expects($this->once())->method('loadByIncrementId')->with(self::INCREMENT_ID)->willReturnSelf();
        $orderMock->expects($this->once())->method('isObjectNew')->willReturn(true);
        $orderMock->expects($this->never())->method('getId')->willReturn(self::ORDER_ID);
        TestHelper::stubModel('sales/order', $orderMock);
        $checkoutSessionMock->expects($this->once())->method('clearHelperData');
        $checkoutSessionMock->expects($this->once())->method('setLastQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastSuccessQuoteId')->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastOrderId')->with(Bolt_Boltpay_Model_Order::MAX_ORDER_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRealOrderId')->with(self::INCREMENT_ID)
            ->willReturnSelf();
        $checkoutSessionMock->expects($this->once())->method('setLastRecurringProfileIds')
            ->with(array(234, 678, 876))->willReturnSelf();
        $this->currentMock->setSuccessSessionData(new Varien_Event_Observer());
        Mage::app()->getRequest()->clearParams();
    }

    /**
     * @test
     * that setSuccessSessionData does not modify session if both bolt_transaction_reference and bolt_payload
     * are not present in the request
     *
     * @covers ::setSuccessSessionData
     *
     * @throws Exception from tested method if quote totals have not been properly collected
     */
    public function setSuccessSessionData_ifRequiredParamsAreEmpty_doesNotModifySession()
    {
        $checkoutSessionMock = $this->setSuccessSessionDataSetUp();
        Mage::app()->getRequest()->setParam('bolt_payload', null);
        Mage::app()->getRequest()->setParam('bolt_transaction_reference', null);
        $this->boltHelperMock->expects($this->never())->method('verify_hook');
        $this->boltHelperMock->expects($this->never())->method('notifyException');
        $this->boltHelperMock->expects($this->never())->method('logWarning');
        $checkoutSessionMock->expects($this->never())->method('clearHelperData');
        $checkoutSessionMock->expects($this->never())->method('setLastQuoteId');
        $checkoutSessionMock->expects($this->never())->method('setLastSuccessQuoteId');
        $checkoutSessionMock->expects($this->never())->method('setLastOrderId');
        $checkoutSessionMock->expects($this->never())->method('setLastRealOrderId');
        $checkoutSessionMock->expects($this->never())->method('setLastRecurringProfileIds');
        $this->currentMock->setSuccessSessionData(new Varien_Event_Observer());
        Mage::app()->getRequest()->clearParams();
    }

    /**
     * @test
     * that addMessageWhenCapture sets prepared message containing order increment id to order payment
     * on sales_order_payment_capture event if payment method is Bolt
     *
     * @covers ::addMessageWhenCapture
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function addMessageWhenCapture_ifPaymentMethodIsBolt_setsPaymentPreparedMessage()
    {
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        $orderPayment = $order->getPayment();
        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $orderPayment));
        $this->assertEquals(
            'Magento Order ID: "' . $order->getIncrementId() . '".',
            $orderPayment->getData('prepared_message')
        );
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that addMessageWhenCapture leaves order payment prepared message unchanged on sales_order_payment_capture event
     * if payment method is not Bolt
     *
     * @covers ::addMessageWhenCapture
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function addMessageWhenCapture_ifPaymentMethodIsNotBolt_setsPaymentPreparedMessage()
    {
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, array(), 'checkmo');
        $orderPayment = $order->getPayment();
        $preparedMessageBeforeCapture = $orderPayment->getData('prepared_message');
        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $orderPayment));
        $this->assertEquals(
            $preparedMessageBeforeCapture,
            $orderPayment->getData('prepared_message')
        );
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that hidePreAuthOrders doesn't add filter to order grid collection on sales_order_grid_collection_load_before event
     * if displayPreAuthOrders extra-config is enabled
     *
     * @covers ::hidePreAuthOrders
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function hidePreAuthOrders_whenDisplayPreAuthOrdersEnabled_doesNotFilterPreAuthOrders()
    {
        $orderGridCollectionMock = $this->getClassPrototype('Mage_Sales_Model_Resource_Order_Grid_Collection')
            ->setMethods(array('addFieldToFilter'))
            ->getMock();
        $orderGridCollectionMock->expects($this->never())->method('addFieldToFilter');
        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('displayPreAuthOrders')
            ->willReturn(true);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        Mage::dispatchEvent(
            'sales_order_grid_collection_load_before',
            array('order_grid_collection' => $orderGridCollectionMock)
        );
    }

    /**
     * @test
     * that hidePreAuthOrders adds filter to order grid collection on sales_order_grid_collection_load_before event
     * if displayPreAuthOrders extraconfig is not enabled
     * filter makes sure that orders with 'pending_bolt' and 'canceled_bolt' statuses are not displayed
     *
     * @covers ::hidePreAuthOrders
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function hidePreAuthOrders_whenDisplayPreAuthOrdersDisabled_filtersOutPreAuthOrders()
    {
        $orderGridCollectionMock = $this->getClassPrototype('Mage_Sales_Model_Resource_Order_Grid_Collection')
            ->setMethods(array('addFieldToFilter'))
            ->getMock();
        $orderGridCollectionMock->expects($this->once())->method('addFieldToFilter')->with(
            'main_table.status',
            array(
                'nin' => array(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
                )
            )
        );
        $this->boltHelperMock->expects($this->once())->method('getExtraConfig')->with('displayPreAuthOrders')
            ->willReturn(false);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        Mage::dispatchEvent(
            'sales_order_grid_collection_load_before',
            array('order_grid_collection' => $orderGridCollectionMock)
        );
    }

    /**
     * @test
     * that the flag for Bolt order being placed is set to true for Bolt orders after the order is created
     *
     * @covers ::markThatBoltOrderWasJustPlaced
     */
    public function markThatBoltOrderWasJustPlaced_forBoltOrders_flagsOrderAsJustPlaced()
    {
        Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced = false;
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(
            self::$productId,
            $qty = 2,
            $paymentMethod = 'boltpay',
            $overrideNormalOrderStateAndStatusSetByObservers = false
        ); # triggers markThatBoltOrderWasJustPlaced
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that the flag for Bolt order being placed remains false for non-Bolt orders after the order is created
     *
     * @covers ::markThatBoltOrderWasJustPlaced
     */
    public function markThatBoltOrderWasJustPlaced_forNonBoltOrders_keepsOrderJustPlacedFlagAsFalse()
    {
        Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced = false;
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(
            self::$productId,
            $qty = 2,
            $paymentMethod = 'checkmo',
            $overrideNormalOrderStateAndStatusSetByObservers = false
        ); # triggers markThatBoltOrderWasJustPlaced

        $this->assertFalse(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that safeguardPreAuthStatus allows for state and status when directive comes from
     * a hook request and the request in not after the order has just been place
     *
     * @covers ::safeguardPreAuthStatus
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function safeguardPreAuthStatus_whenFromHook_setsStatus()
    {
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $order->getStatus());
        $this->assertFalse(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus);

        Bolt_Boltpay_Helper_Data::$fromHooks = true;

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING)
            ->save();  # triggers event that invokes safeguardPreAuthStatus

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that safeguardPreAuthStatus overrides state and status with original values if the
     * hook request has recently created the order.  This represents the case when third party plugins
     * attempt to change our pre-auth status
     *
     * @covers ::safeguardPreAuthStatus
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function safeguardPreAuthStatus_whenFromHookAfterOrderJustPlaced_blocksStatusChange()
    {
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(
            self::$productId,
            $qty = 2,
            $paymentMethod = 'boltpay',
            $overrideNormalOrderStateAndStatusSetByObservers = false
        );
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $order->getStatus());
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        $this->assertFalse(Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus);

        Bolt_Boltpay_Helper_Data::$fromHooks = true;

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PROCESSING)
            ->save();  # triggers safeguardPreAuthStatus
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $order->getStatus());

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that safeguardPreAuthStatus does not block status change if the
     * directive comes outside of a hook request and original state and status are
     * not Bolt preauth
     *
     * @covers ::safeguardPreAuthStatus
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function safeguardPreAuthStatus_whenNotFromHookAndStatusNotPreAuth_setsStatus()
    {
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);

        $order->setState(
            Mage_Sales_Model_Order::STATE_PROCESSING,
            true
        )->save();

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());
        $this->assertFalse(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus);

        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        $order->setState(Mage_Sales_Model_Order::STATE_NEW, 'pending' )
            ->save(); # triggers safeguardPreAuthStatus
        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertEquals('pending', $order->getStatus());

        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)
            ->save(); # triggers safeguardPreAuthStatus
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that safeguardPreAuthStatus blocks state and status change if initiated from
     * outside a hook request and the order is has preauth state and status
     *
     * @covers ::safeguardPreAuthStatus
     *
     * @dataProvider safeguardPreAuthStatus_whenNotFromHookAndStatusIsPreAuthProvider
     *
     * @param string $preAuthState  original value of order state
     * @param string $preAuthStatus original value of order status
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function safeguardPreAuthStatus_whenNotFromHookAndStatusIsPreAuth_overridesStatusToOriginalValue(
        $preAuthState,
        $preAuthStatus
    )
    {
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);

        if ($preAuthStatus === Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
                )->save();
        }

        $this->assertFalse(Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced);
        $this->assertTrue(Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus);
        $this->assertEquals($preAuthState, $order->getState());
        $this->assertEquals($preAuthStatus, $order->getStatus());

        /////////////////////////////////////////////////////////////////////////////////
        /// We'll use verbose assertions to emphasize that the status is not
        /// changing from preauth despite attempting to save a new status from a
        ///  context outside of webhooks
        /////////////////////////////////////////////////////////////////////////////////
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        $order->setState(Mage_Sales_Model_Order::STATE_NEW, 'pending' )->save();
        $this->assertNotEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertNotEquals('pending', $order->getStatus());
        $this->assertEquals($preAuthState, $order->getState());
        $this->assertEquals($preAuthStatus, $order->getStatus());

        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true)->save();
        $this->assertNotEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getState());
        $this->assertNotEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getStatus());
        $this->assertEquals($preAuthState, $order->getState());
        $this->assertEquals($preAuthStatus, $order->getStatus());
        /////////////////////////////////////////////////////////////////////////////////

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Data provider for {@see safeguardPreAuthStatus_whenNotFromHookAndStatusIsPreAuth_overridesStatusToOriginalValue}
     *
     * @return array containing [preAuthState, preAuthStatus]
     */
    public function safeguardPreAuthStatus_whenNotFromHookAndStatusIsPreAuthProvider()
    {
        return array(
            array(
                'preAuthState' => Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                'preAuthStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING
            ),
            array(
                'preAuthState' => Mage_Sales_Model_Order::STATE_CANCELED,
                'preAuthStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
            ),
        );
    }

    /**
     * @test
     * that validateBeforeOrderCommit executes {@see Bolt_Boltpay_Model_Order::validateBeforeOrderCommit}
     * on sales_model_service_quote_submit_before event
     *
     * @covers ::validateBeforeOrderCommit
     *
     * @throws ReflectionException if unable to stub model
     * @throws Mage_Core_Exception if unable to delete dummy order
     */
    public function validateBeforeOrderCommit_always_validatesOrderBeforeCommit()
    {
        $boltOrderModelMock = $this->getClassPrototype('boltpay/order')
            ->setMethods(array('validateBeforeOrderCommit'))
            ->getMock();
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($order->getQuoteId());
        TestHelper::stubModel('boltpay/order', $boltOrderModelMock);
        $boltOrderModelMock->expects($this->once())->method('validateBeforeOrderCommit')->with(
            $this->callback(
                function ($observer) use ($quote, $order) {
                    /** @var Varien_Event_Observer $observer */
                    $this->assertEquals($order, $observer->getEvent()->getOrder());
                    $this->assertEquals($quote, $observer->getEvent()->getQuote());
                    return true;
                }
            )
        );
        Mage::dispatchEvent(
            'sales_model_service_quote_submit_before',
            array(
                'order' => $order,
                'quote' => $quote
            )
        );
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that createInvoiceAfterCreatingShipment doesn't create invoice if order is not Bolt
     *
     * @covers ::createInvoiceAfterCreatingShipment
     *
     * @throws Mage_Core_Exception if unable to create dummy order
     * @throws Exception if test class name is not defined
     */
    public function createInvoiceAfterCreatingShipment_withNonBoltOrder_doesNotCreateInvoice()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Observer $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getInvoiceItemsFromShipment'))->getMock();
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1, 'checkmo');
        $shipment = Mage::getModel('sales/order_shipment', array('order' => $order));
        $currentMock->expects($this->never())->method('getInvoiceItemsFromShipment');
        $currentMock->createInvoiceAfterCreatingShipment(
            new Varien_Event_Observer(array('event' => new Varien_Event(array('shipment' => $shipment))))
        );
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that createInvoiceAfterCreatingShipment doesn't create invoice if auto_create_invoice_after_creating_shipment
     * is disabled
     *
     * @covers ::createInvoiceAfterCreatingShipment
     *
     * @throws Mage_Core_Exception if unable to stub config value or create dummy order
     * @throws Exception if test class name is not defined
     */
    public function createInvoiceAfterCreatingShipment_withAutoCreateInvoiceDisabled_doesNotCreateInvoice()
    {
        TestHelper::stubConfigValue('payment/boltpay/auto_create_invoice_after_creating_shipment', 0);
        /** @var MockObject|Bolt_Boltpay_Model_Observer $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getInvoiceItemsFromShipment'))->getMock();
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1);
        $shipment = Mage::getModel('sales/order_shipment', array('order' => $order));
        $currentMock->expects($this->never())->method('getInvoiceItemsFromShipment');
        $currentMock->createInvoiceAfterCreatingShipment(
            new Varien_Event_Observer(array('event' => new Varien_Event(array('shipment' => $shipment))))
        );
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that createInvoiceAfterCreatingShipment creates invoice on sales_order_shipment_save_after event
     * if order is Bolt and invoice generation is enabled
     *
     * @covers ::createInvoiceAfterCreatingShipment
     *
     * @throws ReflectionException if unable to stub model
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     * @throws Mage_Core_Exception if unable to create dummy order
     * @throws Exception if unable to save shipment
     */
    public function createInvoiceAfterCreatingShipment_whenAutoInvoiceEnabledAndOrderIsBolt_createsInvoiceOnline()
    {
        $boltPaymentMock = $this->getClassPrototype('boltpay/payment')
            ->setMethods(array('capture'))->getMock();
        $boltPaymentMock->expects($this->once())->method('capture');
        TestHelper::stubModel('boltpay/payment', $boltPaymentMock);
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        TestHelper::stubModel('boltpay/payment', $boltPaymentMock);
        TestHelper::stubConfigValue('payment/boltpay/auto_create_invoice_after_creating_shipment', 1);
        $shipment = $order->prepareShipment();
        $shipment->register();
        /** Dispatches sales_order_shipment_save_after event */
        $shipment->save();
        $this->assertTrue((bool)$order->hasInvoices());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that createInvoiceAfterCreatingShipment catches and logs the exception if it occurs during invoice creation
     *
     * @covers ::createInvoiceAfterCreatingShipment
     *
     * @throws ReflectionException if unable to stub model
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     * @throws Mage_Core_Exception if unable to create dummy order
     * @throws Exception if unable to save shipment
     */
    public function createInvoiceAfterCreatingShipment_ifExceptionOccursDuringInvoiceCreation_exceptionIsLogged()
    {
        $boltPaymentMock = $this->getClassPrototype('boltpay/payment')
            ->setMethods(array('capture'))->getMock();
        $exception = new Exception('Unable to capture');
        $boltPaymentMock->expects($this->once())->method('capture')->willThrowException($exception);
        TestHelper::stubModel('boltpay/payment', $boltPaymentMock);
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        TestHelper::stubModel('boltpay/payment', $boltPaymentMock);
        TestHelper::stubConfigValue('payment/boltpay/auto_create_invoice_after_creating_shipment', 1);

        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $shipment = $order->prepareShipment();
        $shipment->register();
        $shipment->save();
        $this->assertTrue((bool)$order->hasInvoices());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that getInvoiceItemsFromShipment returns item quantities to be invoiced from provided shipment
     *
     * @covers ::getInvoiceItemsFromShipment
     *
     * @throws ReflectionException if getInvoiceItemsFromShipment method is not defined
     * @throws Exception if test class name is not defined
     */
    public function getInvoiceItemsFromShipment_withItemWithApplicableQtyToInvoice_returnsInvoicedItems()
    {
        $this->currentMock = $this->getTestClassPrototype()->setMethods(null)->getMock();

        $orderItem = $this->getClassPrototype('Mage_Sales_Model_Order_Item')
            ->setMethods(array('getQtyOrdered', 'getQtyInvoiced', 'canInvoice'))
            ->getMock();
        $orderItem->method('getQtyOrdered')->willReturn(3);
        $orderItem->method('getQtyInvoiced')->willReturn(1);
        $orderItem->method('canInvoice')->willReturn(true);

        $shipmentItem = $this->getClassPrototype('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(array('getOrderItem', 'getOrderItemId', 'getQty'))
            ->getMock();
        $shipmentItem->method('getOrderItem')->willReturn($orderItem);
        $shipmentItem->method('getOrderItemId')->willReturn('12345');
        $shipmentItem->method('getQty')->willReturn(3);

        $shipment = $this->getClassPrototype('Mage_Sales_Model_Order_Shipment')
            ->setMethods(array('getAllItems'))
            ->getMock();
        $shipment->method('getAllItems')->willReturn(array($shipmentItem));

        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getInvoiceItemsFromShipment',
            array($shipment)
        );

        $this->assertEquals(array('12345' => 2), $result);
    }

    /**
     * @test
     * that getApplicableQtyToInvoice returns false if order item of the provided shipment item cannot be invoiced
     * @see Mage_Sales_Model_Order_Item::canInvoice returns false
     *
     * @covers ::getApplicableQtyToInvoice
     *
     * @throws ReflectionException if getApplicableQtyToInvoice method is not defined
     */
    public function getApplicableQtyToInvoice_whenOrderItemCannotBeInvoiced_returnsFalse()
    {
        $shipmentItemMock = $this->getClassPrototype('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(array('getOrderItem', 'canInvoice'))
            ->getMock();
        $shipmentItemMock->expects($this->once())->method('getOrderItem')->willReturnSelf();
        $shipmentItemMock->expects($this->once())->method('canInvoice')->willReturn(false);
        $this->assertFalse(
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getApplicableQtyToInvoice',
                array($shipmentItemMock)
            )
        );
    }

    /**
     * @test
     * that getApplicableQtyToInvoice returns maximum quantity to invoice if it's lower than qty shipped
     *
     * @covers ::getApplicableQtyToInvoice
     *
     * @throws ReflectionException if getApplicableQtyToInvoice method is not defined
     */
    public function getApplicableQtyToInvoice_whenShippedQtyIsLargerThanMaximumToInvoice_returnsMaxQtyToInvoice()
    {
        $shipmentItemMock = $this->getClassPrototype('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(array('getOrderItem', 'canInvoice', 'getQty'))
            ->getMock();
        $orderItemMock = $this->getClassPrototype('Mage_Sales_Model_Order_Item')
            ->setMethods(array('canInvoice', 'getQtyOrdered', 'getQtyInvoiced'))
            ->getMock();
        $shipmentItemMock->expects($this->once())->method('getOrderItem')->willReturn($orderItemMock);
        $shipmentItemMock->expects($this->once())->method('getQty')->willReturn(555);
        $orderItemMock->expects($this->once())->method('canInvoice')->willReturn(true);
        $orderItemMock->expects($this->once())->method('getQtyOrdered')->willReturn(456);
        $orderItemMock->expects($this->once())->method('getQtyInvoiced')->willReturn(222);
        $this->assertEquals(
            234,
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getApplicableQtyToInvoice',
                array($shipmentItemMock)
            )
        );
    }

    /**
     * @test
     * that getApplicableQtyToInvoice returns quantity shipped if it's lower than maximum qty to invoice
     *
     * @covers ::getApplicableQtyToInvoice
     *
     * @throws ReflectionException if getApplicableQtyToInvoice method is not defined
     */
    public function getApplicableQtyToInvoice_whenMaximumToInvoiceIsLargerThanShippedQty_returnsShippedQty()
    {
        $shipmentItemMock = $this->getClassPrototype('Mage_Sales_Model_Order_Shipment_Item')
            ->setMethods(array('getOrderItem', 'canInvoice', 'getQty'))
            ->getMock();
        $orderItemMock = $this->getClassPrototype('Mage_Sales_Model_Order_Item')
            ->setMethods(array('canInvoice', 'getQtyOrdered', 'getQtyInvoiced'))
            ->getMock();
        $shipmentItemMock->expects($this->once())->method('getOrderItem')->willReturn($orderItemMock);
        $shipmentItemMock->expects($this->once())->method('getQty')->willReturn(555);
        $orderItemMock->expects($this->once())->method('canInvoice')->willReturn(true);
        $orderItemMock->expects($this->once())->method('getQtyOrdered')->willReturn(1000);
        $orderItemMock->expects($this->once())->method('getQtyInvoiced')->willReturn(222);
        $this->assertEquals(
            555,
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getApplicableQtyToInvoice',
                array($shipmentItemMock)
            )
        );
    }

    /**
     * @test
     * that clearShoppingCartExceptPPCOrder clears the cart for non-product-page checkouts
     *
     * @dataProvider clearShoppingCartExceptPPCOrderDataProvider
     *
     * @covers ::clearShoppingCartExceptPPCOrder
     *
     * @param string $checkoutType to be set as checkoutType request parameter
     * @param bool   $shouldTruncate flag whether clearShoppingCartExceptPPCOrder should clear the cart
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function clearShoppingCartExceptPPCOrder_withVariousCheckoutTypes_clearsCartExceptForPPC($checkoutType, $shouldTruncate)
    {
        $checkoutCartHelperMock = $this->getClassPrototype('Mage_Checkout_Helper_Cart')
            ->setMethods(array('getCart', 'truncate', 'save'))
            ->getMock();
        if ($shouldTruncate) {
            $checkoutCartHelperMock->expects($this->once())->method('getCart')->willReturnSelf();
            $checkoutCartHelperMock->expects($this->once())->method('truncate')->willReturnSelf();
            $checkoutCartHelperMock->expects($this->once())->method('save');
        } else {
            $checkoutCartHelperMock->expects($this->never())->method('getCart');
        }

        Mage::app()->getRequest()->setParam('checkoutType', $checkoutType);
        TestHelper::stubHelper('checkout/cart', $checkoutCartHelperMock);
        Mage::getModel('boltpay/observer')->clearShoppingCartExceptPPCOrder();

        Mage::app()->getRequest()->setParam('checkoutType', null);
        TestHelper::restoreHelper('checkout/cart');
    }

    /**
     * Data provider for {@see clearShoppingCartExceptPPCOrder_withVariousCheckoutTypes_clearsCartExceptForPPC}
     *
     * @return array containing checkout type and whether clearCartCacheOnOrderCanceled should clear the cart
     */
    public function clearShoppingCartExceptPPCOrderDataProvider()
    {
        return array(
            array(
                'checkoutType'   => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE,
                'shouldTruncate' => false,
            ),
            array(
                'checkoutType'   => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT,
                'shouldTruncate' => true,
            ),
            array(
                'checkoutType'   => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
                'shouldTruncate' => true,
            ),
            array(
                'checkoutType'   => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE,
                'shouldTruncate' => true,
            ),
            array(
                'checkoutType'   => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                'shouldTruncate' => true,
            ),
        );
    }
}