<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Payment
 */
class Bolt_Boltpay_Model_PaymentTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Dummy Bolt Merchant Transaction ID */
    const BOLT_MERCHANT_TRANSACTION_ID = 'TAiHkkqzKc1Zi';

    /** @var string Dummy Bolt Transaction Reference */
    const BOLT_TRANSACTION_REFERENCE = '92XB-GBX4-T49L';

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Model_Payment';

    /**
     * @var int|null Dummy product ID
     */
    private static $productId = null;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Payment Mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of the Bolt helper
     */
    private $boltHelperMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Exception if test class name is not defined
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'isAdminArea', 'getInfoInstance'))
            ->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('transmit', 'canUseBolt', 'notifyException', 'logWarning', 'fetchTransaction'))
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
    }

    /**
     * Generate dummy product data used for creating test orders
     *
     * @throws Exception if unable to create dummy product
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'), array(), 20);
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
    }

    /**
     * Delete dummy products after the test
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Restore original stubbed values
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * @test
     * constants and enabled/disabled features
     *
     * @covers ::__construct
     */
    public function __construct_always_createsInstance()
    {
        $instance = new Bolt_Boltpay_Model_Payment();
        $this->assertEquals('Credit & Debit Card', Bolt_Boltpay_Model_Payment::TITLE);
        $this->assertEquals('boltpay', $instance->getCode());

        // All the features that are enabled
        $this->assertTrue($instance->canAuthorize());
        $this->assertTrue($instance->canCapture());
        $this->assertTrue($instance->canRefund());
        $this->assertTrue($instance->canUseCheckout());
        $this->assertTrue($instance->canFetchTransactionInfo());
        $this->assertTrue($instance->canEdit());
        $this->assertTrue($instance->canRefundPartialPerInvoice());
        $this->assertTrue($instance->canCapturePartial());
        $this->assertTrue($instance->canUseInternal());
        $this->assertTrue($instance->isInitializeNeeded());

        // All the features that are disabled
        $this->assertFalse($instance->canUseForMultishipping());
        $this->assertFalse($instance->canCreateBillingAgreement());
        $this->assertFalse($instance->isGateway());
        $this->assertFalse($instance->canManageRecurringProfiles());
        $this->assertFalse($instance->canOrder());
    }

    /**
     * @test
     * that transitions are allowed from on hold all states if executed from webhook
     *
     * @covers ::__construct
     */
    public function __construct_notFromHook_allowTransitionFromHoldToAll()
    {
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        $currentInstance = new Bolt_Boltpay_Model_Payment();
        $_validStateTransitions = $this->readAttribute($currentInstance, '_validStateTransitions');
        $this->assertEquals(
            array(Bolt_Boltpay_Model_Payment::TRANSACTION_ALL_STATES),
            $_validStateTransitions[Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD]
        );
    }

    /**
     * @test
     * that initialize sets state to new and status to pending if in admin area
     *
     * @covers ::initialize
     *
     * @throws Exception if test class name is not defined
     */
    public function initialize_inAdminArea_setStateToNewAndStatusToPending()
    {
        $stateObject = new Varien_Object();
        $this->currentMock->method('isAdminArea')->willReturn(true);
        $this->currentMock->initialize('', $stateObject);
        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $stateObject->getState());
        $this->assertEquals('pending', $stateObject->getStatus());
    }

    /**
     * @test
     * that initialize sets state to pending and status to pending payment if not in admin area
     *
     * @covers ::initialize
     *
     * @throws Exception if test class name is not defined
     */
    public function initialize_notInAdminArea_setStateToPendingAndStatusToPendingBolt()
    {
        $stateObject = new Varien_Object();
        $this->currentMock->method('isAdminArea')->willReturn(false);
        $this->currentMock->initialize('', $stateObject);
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $stateObject->getState());
        $this->assertEquals('pending_bolt', $stateObject->getStatus());
    }

    /**
     * @test
     * that isAdminArea returns true if current store is admin
     *
     * @covers ::isAdminArea
     */
    public function isAdminArea_inAdminStore_returnsTrue()
    {
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        $this->assertTrue(Mage::getModel('boltpay/payment')->isAdminArea());
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::DEFAULT_CODE);
    }

    /**
     * @test
     * that isAdminArea returns true if current design area is adminhtml
     *
     * @covers ::isAdminArea
     */
    public function isAdminArea_withDesignAreaAdminhtml_returnsTrue()
    {
        Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);
        $this->assertTrue(Mage::getModel('boltpay/payment')->isAdminArea());
        Mage::getDesign()->setArea(Mage_Core_Model_Design_Package::DEFAULT_AREA);
    }

    /**
     * @test
     * that isAdminArea returns false if current store is not admin and design area is not adminhtml
     *
     * @covers ::isAdminArea
     */
    public function isAdminArea_whenStoreNotAdminNorDesignAreaAdminhtml_returnsFalse()
    {
        Mage::getDesign()->setArea(Mage_Core_Model_Design_Package::DEFAULT_AREA);
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::DEFAULT_CODE);
        $this->assertFalse(Mage::getModel('boltpay/payment')->isAdminArea());
    }

    /**
     * @test
     * that getConfigData return null if skip payment enabled and field parameter is allowspecific
     *
     * @covers ::getConfigData
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getConfigData_ifSkipPaymentEnableAndFieldAllowSpecific_returnsNull()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/skip_payment', 1);

        $result = $this->currentMock->getConfigData('allowspecific');

        $this->assertNull($result);
    }

    /**
     * @test
     * that getConfigData return null if skip payment enabled and field parameter is specificcountry
     *
     * @covers ::getConfigData
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getConfigData_ifSkipPaymentEnableAndSpecificCountryField_returnsNull()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/skip_payment', 1);

        $result = $this->currentMock->getConfigData('specificcountry');

        $this->assertNull($result);
    }

    /**
     * @test
     * that getConfigData returns {@see Bolt_Boltpay_Model_Payment::TITLE_ADMIN} constant for title if in admin area
     *
     * @covers ::getConfigData
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getConfigData_inAdminAreaWithFieldTitle_returnsAdminTitleConstant()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/skip_payment', 1);

        $this->currentMock->expects($this->once())->method('isAdminArea')->willReturn(true);

        $field = 'title';
        $result = $this->currentMock->getConfigData($field);

        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TITLE_ADMIN,
            $result,
            'ADMIN_TITLE field does not match'
        );
    }

    /**
     * @test
     * that getConfigData returns {@see Bolt_Boltpay_Model_Payment::TITLE} constant for title if not in admin area
     *
     * @covers ::getConfigData
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getConfigData_notAdminAreaWithFieldTitle_returnsTitleConstant()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/skip_payment', 1);

        $this->currentMock->expects($this->once())->method('isAdminArea')->willReturn(false);

        $result = $this->currentMock->getConfigData('title');

        $this->assertEquals(Bolt_Boltpay_Model_Payment::TITLE, $result, 'TITLE field does not match');
    }

    /**
     * @test
     * that getConfigData returns config value for provided field
     *
     * @covers ::getConfigData
     *
     * @throws Mage_Core_Model_Store_Exception if unable to sub config
     */
    public function getConfigData_byDefault_returnsPaymentConfigValue()
    {
        $field = 'test_random_field';
        $value = 'test field value';
        Bolt_Boltpay_TestHelper::stubConfigValue(
            'payment/' . Bolt_Boltpay_Model_Payment::METHOD_CODE . '/' . $field,
            $value
        );
        $this->assertEquals($value, $this->currentMock->getConfigData($field));
    }

    /**
     * @test
     * that translateHookTypeToTransactionStatus throws exception if provided with invalid hook type
     *
     * @covers ::translateHookTypeToTransactionStatus
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Invalid hook type invalid-hook-type
     */
    public function translateHookTypeToTransactionStatus_withInvalidHookType_throwsException()
    {
        Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus('invalid-hook-type');
    }

    /**
     * @test
     * that translateHookTypeToTransactionStatus throws exception if provided with invalid hook type
     *
     * @covers ::translateHookTypeToTransactionStatus
     */
    public function translateHookTypeToTransactionStatus_withHookCaptureAndTxStatusAuthorized_returnsTransactionAuthorized()
    {
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
            Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus(
                Bolt_Boltpay_Model_Payment::HOOK_TYPE_CAPTURE,
                (object)array('status' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED)
            )
        );
    }

    /**
     * @test
     * that translateHookTypeToTransactionStatus throws exception if provided with invalid hook type
     *
     * @covers ::translateHookTypeToTransactionStatus
     *
     * @dataProvider translateHookTypeToTransactionStatus_withVariousHookTypesProvider
     *
     * @param string $hookType to be translated to transaction status
     * @param string $transactionStatus expected result of the translation
     */
    public function translateHookTypeToTransactionStatus_withVariousHookTypes_returnsAppropriateTransactionStatus(
        $hookType,
        $transactionStatus
    ) {
        $this->assertEquals(
            $transactionStatus,
            Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus($hookType)
        );
    }

    /**
     * Data provider for
     * @see translateHookTypeToTransactionStatus_withVariousHookTypes_returnsAppropriateTransactionStatus
     *
     * @return array containing hook type and transaction status
     */
    public function translateHookTypeToTransactionStatus_withVariousHookTypesProvider()
    {
        return array(
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_AUTH,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_CAPTURE,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_PAYMENT,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_PENDING,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_REVERSIBLE,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_IRREVERSIBLE,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_VOID,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
            ),
            array(
                'hookType'          => Bolt_Boltpay_Model_Payment::HOOK_TYPE_REFUND,
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
            ),
        );
    }

    /**
     * @test
     * that fetchTransactionInfo throws exception if bolt_merchant_transaction_id is not present in payment
     *
     * @covers ::fetchTransactionInfo
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Waiting for a transaction update from Bolt. Please retry after 60 seconds.
     *
     * @throws Exception from tested method
     */
    public function fetchTransactionInfo_withoutMerchantTransId_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array('additional_information' => array('bolt_merchant_transaction_id' => null))
        );
        $this->currentMock->fetchTransactionInfo($payment, self::BOLT_MERCHANT_TRANSACTION_ID);
    }

    /**
     * @test
     * that fetchTransactionInfo throws exception if fetched transaction status is empty
     *
     * @covers ::fetchTransactionInfo
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bad fetch transaction response. Empty transaction status
     *
     * @throws Exception from tested method
     */
    public function fetchTransactionInfo_withEmptyTransactionStatus_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'bolt_reference'               => self::BOLT_TRANSACTION_REFERENCE
                )
            )
        );
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(self::BOLT_TRANSACTION_REFERENCE, null)
            ->willReturn((object)array('status' => null));
        $this->currentMock->fetchTransactionInfo($payment, self::BOLT_MERCHANT_TRANSACTION_ID);
    }

    /**
     * @test
     * that fetchTransactionInfo executes {@see Bolt_Boltpay_Model_Payment::handleTransactionUpdate}
     * if transaction is valid
     *
     * @covers ::fetchTransactionInfo
     *
     * @throws Exception from tested method
     */
    public function fetchTransactionInfo_withValidTransaction_handlesTransactionUpdate()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'bolt_reference'               => self::BOLT_TRANSACTION_REFERENCE
                )
            )
        );
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'handleTransactionUpdate'))
            ->getMock();
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(self::BOLT_TRANSACTION_REFERENCE, null)
            ->willReturn((object)array('status' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING));
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $currentMock->expects($this->once())->method('handleTransactionUpdate')->with(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            null
        );
        $adminhtmlSessionMock = $this->getClassPrototype('adminhtml/session')
            ->setMethods(array('addNotice'))
            ->getMock();
        $adminhtmlSessionMock->expects($this->once())->method('addNotice')
            ->with(
                'Bolt is still reviewing this transaction.  The order status will be updated automatically after review.'
            );
        Bolt_Boltpay_TestHelper::stubSingleton('adminhtml/session', $adminhtmlSessionMock);
        $currentMock->fetchTransactionInfo($payment, self::BOLT_MERCHANT_TRANSACTION_ID);
    }

    /**
     * @test
     * that isAvailable returns false if not provided with a quote
     *
     * @covers ::isAvailable
     */
    public function isAvailable_withoutQuote_returnsFalse()
    {
        $this->assertFalse($this->currentMock->isAvailable(null));
    }

    /**
     * @test
     * that isAvailable returns result from {@see Bolt_Boltpay_Helper_GeneralTrait::canUseBolt} if provided with a quote
     *
     * @covers ::isAvailable
     */
    public function isAvailable_withQuote_returnsResultFromHelper()
    {
        $quote = Mage::getModel('sales/quote');
        $this->boltHelperMock->expects($this->once())->method('canUseBolt')->with($quote)->willReturn(true);
        $this->assertTrue($this->currentMock->isAvailable($quote));
    }

    /**
     * @test
     * that authorize sets transaction closed to false on payment object
     *
     * @covers ::authorize
     *
     * @throws Exception from tested method
     */
    public function authorize_always_setsTransactionClosedToFalse()
    {
        /** @var MockObject|Varien_Object $paymentMock */
        $paymentMock = $this->getClassPrototype('Varien_Object')
            ->setMethods(array('setIsTransactionClosed'))
            ->getMock();
        $paymentMock->expects($this->once())->method('setIsTransactionClosed')->with(false);
        $this->assertEquals($this->currentMock, $this->currentMock->authorize($paymentMock, 0));
    }

    /**
     * @test
     * that authorize notifies and rethrows exception if it occurs when setting transaction closed status
     *
     * @covers ::authorize
     *
     * @expectedException Exception
     * @expectedExceptionMessage Unable to set transaction closed status
     *
     * @throws Exception from tested method
     */
    public function authorize_whenExceptionIsThrown_notifiesAndReThrowsException()
    {
        /** @var MockObject|Varien_Object $paymentMock */
        $paymentMock = $this->getClassPrototype('Varien_Object')
            ->setMethods(array('setIsTransactionClosed'))
            ->getMock();
        $exception = new Exception('Unable to set transaction closed status');
        $paymentMock->expects($this->once())->method('setIsTransactionClosed')
            ->willThrowException($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->currentMock->authorize($paymentMock, 0);
    }

    /**
     * @test
     * that capture throws exception if merchant transaction id is not set and auto capture is disabled
     *
     * @covers ::capture
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Waiting for a transaction update from Bolt. Please retry after 60 seconds.
     *
     * @throws Exception from tested method
     */
    public function capture_withoutTransactionIdAndAutoCaptureDisabled_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array('additional_information' => array('bolt_merchant_transaction_id' => null), 'auto_capture' => false)
        );
        $this->boltHelperMock->expects($this->once())->method('logWarning')
            ->with('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
        $this->currentMock->capture($payment, 0);
    }

    /**
     * @test
     * that capture throws exception if capture API response status is empty
     *
     * @covers ::capture
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bad capture response. Empty transaction status
     *
     * @throws Exception from tested method
     */
    public function capture_withTransactionStatusAuthorizedAndBadCaptureResponse_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'bolt_reference'               => self::BOLT_TRANSACTION_REFERENCE,
                    'bolt_transaction_status'      => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                ),
                'auto_capture'           => false,
                'order'                  => Mage::getModel('sales/order', array('order_currency_code' => 'USD'))
            )
        );
        $this->boltHelperMock->expects($this->once())->method('transmit')
            ->with(
                'capture',
                array(
                    'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'amount'                 => (int)round(123 * 100),
                    'currency'               => 'USD',
                    'skip_hook_notification' => true
                )
            )
            ->willReturn((object)array('status' => null));
        $this->boltHelperMock->expects($this->once())->method('logWarning')
            ->with('Bad capture response. Empty transaction status');
        $this->currentMock->capture($payment, 123);
    }

    /**
     * @test
     * that capture throws exception if transaction status is not authorized
     *
     * @covers ::capture
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Capture attempted denied. Transaction status: cancelled
     *
     * @throws Exception from tested method
     */
    public function capture_withTransactionStatusNotAuthorized_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'bolt_reference'               => self::BOLT_TRANSACTION_REFERENCE,
                    'bolt_transaction_status'      => Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
                ),
                'auto_capture'           => false,
                'order'                  => Mage::getModel('sales/order', array('order_currency_code' => 'USD'))
            )
        );
        $this->boltHelperMock->expects($this->once())->method('logWarning')
            ->with('Capture attempted denied. Transaction status: cancelled');
        $this->currentMock->capture($payment, 123);
    }

    /**
     * @test
     * that capture transmits capture request to Bolt API if transaction status is authorized
     *
     * @covers ::capture
     *
     * @throws Exception from tested method
     */
    public function capture_withAuthorizedTransactionStatus_updatesPayment()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', '_handleBoltTransactionStatus'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        /** @var Mage_Payment_Model_Info|MockObject $payment */
        $payment = $this->getClassPrototype('payment/info')
            ->setMethods(
                array(
                    'getAdditionalInformation',
                    'setAdditionalInformation',
                    'setParentTransactionId',
                    'setTransactionId',
                    'setIsTransactionClosed',
                    'getData',
                    'getOrder',
                    'save'
                )
            )
            ->getMock();
        $payment->expects($this->exactly(3))->method('getAdditionalInformation')
            ->willReturnMap(
                array(
                    array('bolt_merchant_transaction_id', self::BOLT_MERCHANT_TRANSACTION_ID),
                    array('bolt_reference', self::BOLT_TRANSACTION_REFERENCE),
                    array('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED),
                )
            );
        $payment->expects($this->once())->method('getOrder')->willReturn(
            Mage::getModel('sales/order', array('order_currency_code' => 'USD'))
        );
        $currentMock->expects($this->once())->method('_handleBoltTransactionStatus')
            ->with($payment, Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED);
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED);

        $payment->expects($this->once())->method('setParentTransactionId')->with(self::BOLT_TRANSACTION_REFERENCE);
        $payment->expects($this->once())->method('setTransactionId')
            ->with($this->stringStartsWith('92XB-GBX4-T49L-capture'));
        $payment->expects($this->once())->method('setIsTransactionClosed')->with(0);
        $payment->expects($this->once())->method('save');

        $this->boltHelperMock->expects($this->once())->method('transmit')
            ->with(
                'capture',
                array(
                    'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'amount'                 => (int)round(123 * 100),
                    'currency'               => 'USD',
                    'skip_hook_notification' => true
                )
            )
            ->willReturn((object)array('status' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED));
        $this->assertEquals($currentMock, $currentMock->capture($payment, 123));
    }

    /**
     * @test
     * that refund doesn't perform refund if bolt_transaction_was_refunded_by_webhook flag is set
     *
     * @covers ::refund
     *
     * @throws Exception from tested method
     */
    public function refund_withTransactionRefundedFlag_doesNotRefund()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array('additional_information' => array('bolt_transaction_was_refunded_by_webhook' => '1'))
        );
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getInfoInstance', 'boltHelper', 'setRefundPaymentInfo'))
            ->getMock();
        $currentMock->expects($this->never())->method('getInfoInstance');
        $currentMock->expects($this->never())->method('boltHelper');
        $currentMock->expects($this->never())->method('setRefundPaymentInfo');
        $this->assertEquals($currentMock, $currentMock->refund($payment, 0));
    }

    /**
     * @test
     * that refund throws exception if transaction id is not set to payment
     *
     * @covers ::refund
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Waiting for a transaction update from Bolt. Please retry after 60 seconds.
     *
     * @throws Exception from tested method
     */
    public function refund_withoutTransactionId_throwsException()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_transaction_was_refunded_by_webhook' => '0',
                    'bolt_merchant_transaction_id'             => null
                )
            )
        );
        $this->currentMock->expects($this->once())->method('getInfoInstance')->willReturn($payment);
        $this->currentMock->refund($payment, 0);
    }

    /**
     * @test
     * that refund throws exception if credit API response has empty reference
     *
     * @covers ::refund
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bad refund response. Empty transaction reference
     *
     * @throws Exception from tested method
     */
    public function refund_ifCreditResponseHasEmptyReference_throwsException()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_transaction_was_refunded_by_webhook' => '0',
                    'bolt_merchant_transaction_id'             => self::BOLT_MERCHANT_TRANSACTION_ID
                ),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('order_currency_code' => 'USD')
                )
            )
        );
        $this->currentMock->expects($this->once())->method('getInfoInstance')->willReturn($payment);
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'credit',
            array(
                'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                'amount'                 => (int)round(12300),
                'currency'               => 'USD',
                'skip_hook_notification' => true,
            )
        )->willReturn((object)array('reference' => null));
        $this->currentMock->refund($payment, 123);
    }

    /**
     * @test
     * that refund throws exception if credit API response has empty transaction id
     *
     * @covers ::refund
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bad refund response. Empty transaction id
     *
     * @throws Exception from tested method
     */
    public function refund_ifCreditResponseHasEmptyId_throwsException()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_transaction_was_refunded_by_webhook' => '0',
                    'bolt_merchant_transaction_id'             => self::BOLT_MERCHANT_TRANSACTION_ID
                ),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('order_currency_code' => 'USD')
                )
            )
        );
        $this->currentMock->expects($this->once())->method('getInfoInstance')->willReturn($payment);
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'credit',
            array(
                'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                'amount'                 => (int)round(12300),
                'currency'               => 'USD',
                'skip_hook_notification' => true,
            )
        )->willReturn((object)array('reference' => self::BOLT_TRANSACTION_REFERENCE, 'id' => null));
        $this->currentMock->refund($payment, 123);
    }

    /**
     * @test
     * that refund calls {@see Bolt_Boltpay_Model_Payment::setRefundPaymentInfo} if credit API response is valid
     *
     * @covers ::refund
     *
     * @throws Exception from tested method
     */
    public function refund_withValidCreditAPIResponse_setsRefundPaymentInfo()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_transaction_was_refunded_by_webhook' => '0',
                    'bolt_merchant_transaction_id'             => self::BOLT_MERCHANT_TRANSACTION_ID
                ),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('order_currency_code' => 'USD')
                )
            )
        );
        $response = (object)array('reference' => self::BOLT_TRANSACTION_REFERENCE, 'id' => 'TAiHkkqzKc1Zi');
        /** @var Bolt_Boltpay_Model_Payment|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'setRefundPaymentInfo', 'getInfoInstance'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $currentMock->method('getInfoInstance')->willReturn($payment);
        $currentMock->expects($this->once())->method('setRefundPaymentInfo')->with($payment, $response);

        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'credit',
            array(
                'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                'amount'                 => (int)round(12300),
                'currency'               => 'USD',
                'skip_hook_notification' => true,
            )
        )->willReturn($response);
        $currentMock->refund($payment, 123);
    }

    /**
     * @test
     * that void throws exception if transaction id is not set
     *
     * @covers ::void
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Waiting for a transaction update from Bolt. Please retry after 60 seconds.
     *
     * @throws Exception from tested method
     */
    public function void_withoutTransactionId_throwsException()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => null
                )
            )
        );
        $this->currentMock->void($payment);
    }

    /**
     * @test
     * that void throws exception if credit API response has empty reference
     *
     * @covers ::void
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bad void response. Empty transaction status
     *
     * @throws Exception from tested method
     */
    public function void_ifVoidResponseHasEmptyStatus_throwsException()
    {
        /** @var Mage_Payment_Model_Info $payment */
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID
                )
            )
        );
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'void',
            array(
                'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                'skip_hook_notification' => true
            )
        )->willReturn((object)array('status' => null));
        $this->currentMock->void($payment);
    }

    /**
     * @test
     * that void sends void API request and updates payment with response information
     *
     * @covers ::void
     *
     * @throws Exception from tested method
     */
    public function void_withValidAPIResponse_sendsVoidAPIRequestAndUpdatesPayment()
    {
        /** @var Mage_Payment_Model_Info|MockObject $payment */
        $payment = $this->getClassPrototype('payment/info')
            ->setMethods(
                array(
                    'getAdditionalInformation',
                    'setAdditionalInformation',
                    'setParentTransactionId',
                    'setTransactionId',
                )
            )
            ->getMock();
        $payment->expects($this->exactly(2))->method('getAdditionalInformation')
            ->willReturnMap(
                array(
                    array('bolt_merchant_transaction_id', self::BOLT_MERCHANT_TRANSACTION_ID),
                    array('bolt_reference', self::BOLT_TRANSACTION_REFERENCE),
                )
            );
        $payment->expects($this->once())->method('setAdditionalInformation')
            ->with('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED);

        $payment->expects($this->once())->method('setParentTransactionId')->with(self::BOLT_TRANSACTION_REFERENCE);
        $payment->expects($this->once())->method('setTransactionId')
            ->with($this->stringStartsWith('92XB-GBX4-T49L-void'));

        $this->boltHelperMock->expects($this->once())->method('transmit')
            ->with(
                'void',
                array(
                    'transaction_id'         => self::BOLT_MERCHANT_TRANSACTION_ID,
                    'skip_hook_notification' => true
                )
            )
            ->willReturn((object)array('status' => Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED));
        $this->assertEquals($this->currentMock, $this->currentMock->void($payment));
    }

    /**
     * @test
     * that cancel calls {@see Bolt_Boltpay_Model_Payment::void}
     * if {@see Bolt_Boltpay_Model_Payment::canVoid} returns true
     *
     * @covers ::cancel
     *
     * @throws Exception if test class name is not defined
     */
    public function cancel_ifVoidAllowed_callsVoid()
    {
        $payment = Mage::getModel('payment/info');
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('canVoid', 'void'))->getMock();
        $currentMock->expects($this->once())->method('canVoid')->with($payment)->willReturn(false);
        $currentMock->expects($this->never())->method('void')->with($payment);
        $currentMock->cancel($payment);
    }

    /**
     * @test
     * that cancel doesn't call {@see Bolt_Boltpay_Model_Payment::void}
     * if {@see Bolt_Boltpay_Model_Payment::canVoid} returns false
     *
     * @covers ::cancel
     *
     * @throws Exception if test class name is not defined
     */
    public function cancel_ifVoidNotAllowed_doesNotVoid()
    {
        $payment = Mage::getModel('payment/info');
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('canVoid', 'void'))->getMock();
        $currentMock->expects($this->once())->method('canVoid')->with($payment)->willReturn(false);
        $currentMock->expects($this->never())->method('void')->with($payment);
        $this->assertEquals($currentMock, $currentMock->cancel($payment));
    }

    /**
     * @test
     * that assignData does not assign data to info instance when called from outside admin area
     *
     * @covers ::assignData
     *
     * @throws Mage_Core_Exception from tested method if unable to set Bolt Reference
     */
    public function assignData_ifNotAdminArea_doesNotAssignData()
    {
        $data = new Varien_Object(array('bolt_reference' => self::BOLT_TRANSACTION_REFERENCE));

        $this->currentMock->expects($this->once())->method('isAdminArea')->willReturn(false);
        $this->currentMock->expects($this->never())->method('getInfoInstance');

        $result = $this->currentMock->assignData($data);

        $this->assertEquals($this->currentMock, $result);
    }

    /**
     * @test
     * that assignData sets Bolt Reference from provided data when in admin area
     *
     * @covers ::assignData
     *
     * @throws Mage_Core_Exception from tested method if unable to set Bolt Reference
     */
    public function assignData_ifInAdminAreaAndDataHasBoltReference_setsBoltReferenceToInfoInstance()
    {
        $data = new Varien_Object(array('bolt_reference' => self::BOLT_TRANSACTION_REFERENCE));

        $this->currentMock->expects($this->once())->method('isAdminArea')->willReturn(true);

        $mockPaymentInfo = $this->getMockBuilder('Mage_Payment_Model_Info')
            ->setMethods(array('setAdditionalInformation'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $this->currentMock->expects($this->once())->method('getInfoInstance')->willReturn($mockPaymentInfo);

        $result = $this->currentMock->assignData($data);

        $this->assertEquals($this->currentMock, $result);
    }

    /**
     * @test
     * that canReviewPayment returns true if Bolt Transaction Status is Rejected Reversible
     *
     * @covers ::canReviewPayment
     *
     * @throws Mage_Core_Exception if unable to set Bolt Transaction Status
     */
    public function canReviewPayment_withBoltTxStatusRejectedReversible_returnsTrue()
    {
        $orderPayment = Mage::getModel('payment/info');
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
        );
        $this->assertTrue($this->currentMock->canReviewPayment($orderPayment));
    }

    /**
     * @test
     * that void calls to Bolt are allowed initiated from a non-webhook context
     *
     * @covers ::canVoid
     */
    public function canVoid_inNonWebhookContext_isAllowed()
    {
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        $this->assertTrue($this->currentMock->canVoid(Mage::getModel('sales/order_payment')));
    }

    /**
     * @test
     * that void calls to Bolt are not allowed when initiated from a webhook context
     *
     * @covers  ::canVoid
     */
    public function canVoid_inWebhookContext_isNotAllowed()
    {
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        $this->assertFalse($this->currentMock->canVoid(Mage::getModel('sales/order_payment')));
    }

    /**
     * Creates a order for a dummy product that is used for tracking inventory
     *
     * @param Mage_Catalog_Model_Resource_Product $product the product to be ordered and monitored
     * @param int                                 $orderProductQty number of products to be order
     *
     * @return Mage_Sales_Model_Order   The resulting order from this setup
     *
     * @throws Mage_Core_Exception when there is a problem creating an order for the given product and quantity
     */
    private function handleTransactionUpdateSetUp($product, $orderProductQty = 1)
    {

        $initialQty = (int)$product->getQty();

        // Assert initial product store stock is greater than what we will order
        $this->assertGreaterThan($orderProductQty, $initialQty);

        // Create order with the product
        Bolt_Boltpay_TestHelper::createCheckout('guest');
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder($product->getId(), $orderProductQty);

        // After order creation product store stock should be reduced by the order qty
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty($product->getId());
        $this->assertEquals(($initialQty - $orderProductQty), (int)$storeProduct->getQty());

        $order->getPayment()->setAdditionalInformation('bolt_reference', '12345');
        return $order;
    }

    /**
     * @test
     * that product inventory is restored after order cancellation for irreversibly rejected hooks
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if there is a problem creating the dummy order for this test
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withRejectedIrreversibleHook_restoresStockToPreOrderLevel()
    {
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(static::$productId);
        $initialQty = (int)$storeProduct->getQty();

        $order = $this->handleTransactionUpdateSetUp($storeProduct, 2);

        // Transaction is set to REJECTED_IRREVERSIBLE
        $this->currentMock->handleTransactionUpdate(
            $order->getPayment(),
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        // After the hook is triggered order should be cancelled and product stock restored
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals($initialQty, (int)$storeProduct->getQty());
        $this->assertEquals('canceled', $order->getStatus());

        // Delete dummy order
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Verifies that when the order state is "pending_payment" and a pending hook is received:
     *
     * 1.) The order state is set to "payment_review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOrdersWithPendingPayment_setsOrderInReview()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setStatus('pending_bolt');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionPending(true);
        $orderPayment->unsAdditionalInformation('bolt_transaction_status');
        $orderPayment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);


        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertEquals('pending_bolt', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction'));

        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            null
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());

        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history) - 1]->getComment()
        );

        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals('pending', $orderPayment->getAdditionalInformation('bolt_transaction_status'));

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Verifies that when there is no Bolt reference set in the payment and a hook is received
     *
     * 1.) The order state is unchanged
     * 2.) The order status is unchanged
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for bolt_transaction_status is unchanged
     * 6.) An Exception is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOrdersWithNoBoltTransactionReference_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus('pending');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->unsAdditionalInformation('bolt_reference');
        $orderPayment->unsAdditionalInformation('bolt_transaction_status');
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertNull($orderPayment->getAdditionalInformation('bolt_reference'));
        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertEquals('pending', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertFalse($orderPayment->getIsTransactionPending());

        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
            );
        } catch (Exception $ex) {
            $this->assertInstanceOf('Exception', $ex);
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertTrue($exceptionThrown);
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_reference'));
            $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
            $this->assertEquals('pending', $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "new" and a pending hook is received:
     *
     * 1.) The order state is set to "payment review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForNewOrders_setsOrderInReview()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus('pending');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertEquals('pending', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            null
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history) - 1]->getComment()
        );
        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Verifies that when the order state is "processing" and a pending hook is received:
     *
     * 1.) The order state is set to "payment review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOrdersBeingProcessed_setsOrderInReview()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );
        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history) - 1]->getComment()
        );
        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Verifies that when the order state is "completed" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "complete"
     * 2.) The order status is unchanged - "complete"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForCompletedOrders_orderIsUnchanged()
    {
        $order = $this->createDummyOrderWithInitialState(
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            0.0 // This will complete the order. We can not explicitly set the order state to "completed" as it is a protected state and will throw an exception.
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );
        $orderPayment->setIsTransactionPending(true);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from completed to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getStatus());
            $this->assertTrue($exceptionThrown);
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "closed" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "closed"
     * 2.) The order status is unchanged - "closed"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForClosedOrders_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            5.0,
            true // This will close the order. We can not create completed order as "closed" is a protected state
        );

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionPending(false);
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from credit to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "cancelled" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "canceled"
     * 2.) The order status is unchanged - "canceled"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForCanceledOrders_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_CANCELED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
        );
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
        );
        $this->assertFalse($orderPayment->getIsTransactionPending());
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from cancelled to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "holded" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "holded"
     * 2.) The order status is unchanged - "holded"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOnHoldOrders_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_HOLDED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_HOLDED);
        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();

        $initialIsPendingState = $orderPayment->getIsTransactionPending();
        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;
        $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from on-hold to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);
            $this->assertTrue($exceptionThrown);
            $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertEquals($initialIsPendingState, $orderPayment->getIsTransactionPending());
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "canceled", when last payment status is "irreversible_rejected"
     * and a pending hook is received:
     *
     * 1.) The order state is unchanged - "canceled"
     * 2.) The order status is unchanged - "canceled"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOrdersWithCanceledIrreversibleRejected_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_CANCELED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE
        );
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from rejected_irreversible to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * Verifies that when the order state is "payment_review", when last payment status is "reversible_rejected"
     * and a pending hook is received:
     *
     * 1.) The order state is unchanged - "payment_review"
     * 2.) The order status is unchanged - "payment_review"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is true
     * 5.) The payment's additional information for `bolt_transaction_status` is rejected_reversible
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Mage_Core_Exception if unable to create mock order
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withPendingForOrdersWithPaymentReviewReversibleRejected_throwsException()
    {
        $order = $this->createDummyOrderWithInitialState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
        );
        $orderPayment->setIsTransactionPending(true);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );
        $this->assertTrue($orderPayment->getIsTransactionPending());
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from rejected_reversible to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertTrue($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * @test
     * that handleTransactionUpdate creates invoice for capture request
     *
     * @covers ::handleTransactionUpdate
     * @covers ::createInvoiceForHookRequest
     * @covers ::createInvoice
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withCaptureRequestWithoutTransaction_retrievesTransactionAndCreatesInvoice()
    {
        $order = $this->createDummyOrder();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);
        $total = $order->getGrandTotal() * 100;
        $transaction = new stdClass();
        $transaction->captures[0]->status = 'succeeded';
        $transaction->captures[0]->amount->amount = $total;
        $transaction->order->cart->total_amount->amount = $total;
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')
            ->with(self::BOLT_TRANSACTION_REFERENCE)->willReturn($transaction);
        $this->currentMock->handleTransactionUpdate(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );
        $this->assertEquals($order->getGrandTotal(), $order->getTotalInvoiced());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getState());
        $this->assertEquals('pending_bolt', $order->getStatus());
        $this->assertEquals(1, $order->getInvoiceCollection()->getSize());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that handleTransactionUpdate does not handle transaction update if transaction status is unchanged
     * (when {@see Bolt_Boltpay_Model_Payment::isTransactionStatusChanged} returns false)
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if test class name is not defined
     */
    public function handleTransactionUpdate_ifTransactionStatusIsUnchanged_doesNotHandleUpdate()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('validateWebHook', 'createInvoiceForHookRequest', 'handleVoidTransactionUpdate', '_handleBoltTransactionStatus'))
            ->getMock();
        $currentMock->expects($this->never())->method('validateWebHook');
        $currentMock->expects($this->never())->method('createInvoiceForHookRequest');
        $currentMock->expects($this->never())->method('handleVoidTransactionUpdate');
        $currentMock->expects($this->never())->method('_handleBoltTransactionStatus');
        $currentMock->handleTransactionUpdate(
            Mage::getModel('payment/info'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
        );
    }

    /**
     * @test
     * Verifies that when an authorized hook is received:
     *
     * 1.) The order state is set to "processing"
     * 2.) There is a history message confirming this update
     * 3.) The payment's IsTransactionApproved flag is set to true
     * 4.) The payment's additional information for `bolt_transaction_status` is `authorized`
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_fromPendingToAuthorized_movesOrderIntoProcessing()
    {
        $order = $this->createDummyOrder();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);
        $this->currentMock->handleTransactionUpdate(
            $order->getPayment(),
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
        );
        $this->assertEquals(self::BOLT_TRANSACTION_REFERENCE, $payment->getTransactionId());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
            $payment->getAdditionalInformation('bolt_transaction_status')
        );
        $lastTrans = Mage::getModel('sales/order_payment_transaction')
            ->setOrderPaymentObject($payment)
            ->loadByTxnId($payment->getLastTransId());
        $this->assertEquals(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
            $lastTrans->getTxnType()
        );
        /** @var Mage_Sales_Model_Order_Status_History[] $historyMessages */
        $historyMessages = $order->getAllStatusHistory();
        $this->assertEquals(
            'BOLT notification: Payment transaction is authorized.',
            end($historyMessages)->getComment()
        );
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getState());
        $this->assertTrue($payment->getIsTransactionApproved());
    }

    /**
     * @test
     * that handleTransactionUpdate delegates to {@see Bolt_Boltpay_Model_Payment::handleVoidTransactionUpdate}
     * when new transaction status is canceled
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withCanceledTransactionStatus_handlesVoidTransactionUpdate()
    {
        $order = $this->createDummyOrder();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('handleVoidTransactionUpdate'))->getMock();
        $currentMock->expects($this->once())->method('handleVoidTransactionUpdate')->with($payment);
        $currentMock->handleTransactionUpdate(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Verifies that when the order state is "processing" and a pending hook is received:
     * 1.) The order state is set to "payment review"
     * 2.) The order status is set to "deferred"
     * 3.) There is a history message confirming this update
     * 4.) The payment's additional information for `bolt_transaction_status` is `pending`
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withRejectedReversibleTransactionStatus_setsOrderInReview()
    {
        $order = $this->createDummyOrder();
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);
        $this->currentMock->handleTransactionUpdate(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
        );
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Bolt_Boltpay_Model_Payment::ORDER_DEFERRED, $order->getStatus());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
            $payment->getAdditionalInformation('bolt_transaction_status')
        );
        /** @var Mage_Sales_Model_Order_Status_History[] $historyMessages */
        $historyMessages = $order->getAllStatusHistory();
        $this->assertEquals(
            sprintf(
                'BOLT notification: Transaction reference "%s" has been rejected by Bolt internal review but is eligible for force approval on Bolt\'s merchant dashboard',
                self::BOLT_TRANSACTION_REFERENCE
            ),
            end($historyMessages)->getComment()
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that handleTransactionUpdate delegates to {@see Bolt_Boltpay_Model_Payment::handleRefundTransactionUpdate}
     * when new transaction status is refund
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withRefundTransactionStatus_handlesRefundTransactionUpdate()
    {
        $order = $this->createDummyOrder();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('handleRefundTransactionUpdate'))->getMock();
        $transactionAmount = 12345;
        $boltTransaction = (object)array();
        $currentMock->expects($this->once())->method('handleRefundTransactionUpdate')->with(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
            $transactionAmount,
            $boltTransaction
        );
        $currentMock->handleTransactionUpdate(
            $payment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
            $transactionAmount,
            $boltTransaction
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that handleTransactionUpdate sets ShouldCloseParentTransaction flag on payment
     * if both new and previous transaction statuses are empty
     *
     * @covers ::handleTransactionUpdate
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleTransactionUpdate_withEmptyPreviousAndNewTxStatus_setsShouldCloseParentTransaction()
    {
        $order = $this->createDummyOrder();
        $payment = $order->getPayment();
        $this->currentMock->handleTransactionUpdate($payment, '', '');
        $this->assertTrue($payment->getShouldCloseParentTransaction());

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that handleRefundTransactionUpdate throws exception if attempting to refund more than available
     * @covers ::handleRefundTransactionUpdate
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Maximum amount available 100 is less than requested 200
     *
     * @throws Exception if unable to handle transaction update
     */
    public function handleRefundTransactionUpdate_whenRefundTxAmount_throwsException()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'order' => Mage::getModel('sales/order', array('total_refunded' => 100, 'total_paid' => 200))
            )
        );
        $this->currentMock->handleRefundTransactionUpdate(
            $payment,
            '',
            '',
            200,
            (object)array()
        );
    }

    /**
     * @test
     * that handleRefundTransactionUpdate performs complete refund of the order if
     *
     * @covers ::handleRefundTransactionUpdate
     *
     * @throws Exception if unable to handle refund transaction update
     */
    public function handleRefundTransactionUpdate_withCompleteRefund_refundsOrderCompletely()
    {
        $order = $this->createMockOrderWithInvoice();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_merchant_transaction_id', self::BOLT_MERCHANT_TRANSACTION_ID);
        $payment->save();
        $this->currentMock->handleRefundTransactionUpdate(
            $payment,
            '',
            '',
            $transactionAmount = $order->getGrandTotal(),
            (object)array()
        );
        //reload order
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($order->getId());
        /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
        $creditMemo = $order->getCreditmemosCollection()->getFirstItem();
        $this->assertEquals($order->getTotalRefunded(), $transactionAmount);
        $this->assertEquals($creditMemo->getGrandTotal(), $transactionAmount);
        $this->assertTrue($payment->getIsTransactionClosed());
        $this->assertTrue($payment->getShouldCloseParentTransaction());
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that handleRefundTransactionUpdate performs partial refund successfully
     *
     * @covers ::handleRefundTransactionUpdate
     *
     * @throws Exception if unable to handle refund transaction update
     */
    public function handleRefundTransactionUpdate_withPartialRefund_performsPartialRefund()
    {
        $order = $this->createMockOrderWithInvoice();
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_merchant_transaction_id', self::BOLT_MERCHANT_TRANSACTION_ID);
        $payment->save();
        $transaction = new stdClass();
        $transaction->order->cart->total_amount->amount = $order->getGrandTotal() * 100;
        $transactionAmount = 10;
        $this->assertLessThan($order->getGrandTotal(), $transactionAmount);
        $this->currentMock->handleRefundTransactionUpdate(
            $payment,
            '',
            '',
            $transactionAmount,
            $transaction
        );
        //reload order
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($order->getId());
        /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
        $creditMemo = $order->getCreditmemosCollection()->getFirstItem();
        $this->assertEquals($transactionAmount, $order->getTotalRefunded());
        $this->assertEquals($transactionAmount, $creditMemo->getGrandTotal());
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllItems() as $item) {
            $this->assertEquals(0, $item->getQtyRefunded());
        }

        return $order;
    }

    /**
     * @test
     * that handleRefundTransactionUpdate refunds order items if order is completely refunded in two partial refunds
     *
     * @covers ::handleRefundTransactionUpdate
     *
     * @depends handleRefundTransactionUpdate_withPartialRefund_performsPartialRefund
     *
     * @param Mage_Sales_Model_Order $order partially refunded from previous test
     *
     * @throws Exception if unable to handle refund transaction update
     */
    public function handleRefundTransactionUpdate_withOrderPartiallyRefunded_restoresItemsInCart($order)
    {
        $payment = $order->getPayment();
        $transaction = new stdClass();
        $transaction->order->cart->total_amount->amount = $order->getGrandTotal() * 100;
        $transactionAmount = $order->getGrandTotal() - $order->getTotalRefunded();
        $this->currentMock->handleRefundTransactionUpdate(
            $payment,
            '',
            '',
            $transactionAmount,
            $transaction
        );
        //reload order
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($order->getId());
        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($order->getAllItems() as $item) {
            $this->assertEquals($item->getQtyInvoiced(), $item->getQtyRefunded());
        }

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that setRefundPaymentInfo updates payment object with transaction details
     *
     * @covers ::setRefundPaymentInfo
     *
     * @throws ReflectionException if setRefundPaymentInfo method does not exist
     */
    public function setRefundPaymentInfo_withEmptyTxStatusesAndIds_updatesPaymentData()
    {
        $payment = Mage::getModel('payment/info', array('order' => Mage::getModel('sales/order')));
        $transaction = (object)array(
            'reference' => self::BOLT_TRANSACTION_REFERENCE,
            'id'        => 'AAAABBBBCCCCDDDD',
            'status'    => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'setRefundPaymentInfo',
            array(
                $payment,
                $transaction
            )
        );
        $this->assertEquals('92XB-GBX4-T49L-refund', $payment->getTransactionId());
        $this->assertEquals(
            array(Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING),
            json_decode($payment->getAdditionalInformation('bolt_refund_transaction_statuses'))
        );
        $this->assertEquals(
            array('AAAABBBBCCCCDDDD'),
            json_decode($payment->getAdditionalInformation('bolt_refund_merchant_transaction_ids'))
        );
        return $payment;
    }

    /**
     * @test
     * that setRefundPaymentInfo updates payment object with transaction details
     *
     * @covers ::setRefundPaymentInfo
     *
     * @depends setRefundPaymentInfo_withEmptyTxStatusesAndIds_updatesPaymentData
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @throws ReflectionException if setRefundPaymentInfo method does not exist
     */
    public function setRefundPaymentInfo_withExistingTxStatusesAndIds_updatesPaymentData($payment)
    {
        $transaction = (object)array(
            'reference' => self::BOLT_TRANSACTION_REFERENCE,
            'id'        => 'EEEEFFFFGGGGHHHH',
            'status'    => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'setRefundPaymentInfo',
            array(
                $payment,
                $transaction
            )
        );
        $this->assertEquals('92XB-GBX4-T49L-refund', $payment->getTransactionId());
        $this->assertEquals(
            array(Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING, Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED),
            json_decode($payment->getAdditionalInformation('bolt_refund_transaction_statuses'))
        );
        $this->assertEquals(
            array('AAAABBBBCCCCDDDD', 'EEEEFFFFGGGGHHHH'),
            json_decode($payment->getAdditionalInformation('bolt_refund_merchant_transaction_ids'))
        );
    }

    /**
     * @test
     * that createInvoice creates full invoice if not provided with an amount
     *
     * @covers ::createInvoice
     *
     * @throws ReflectionException if createInvoice method is not defined
     */
    public function createInvoice_withoutCaptureAmount_createsFullInvoice()
    {
        $orderMock = $this->getClassPrototype('sales/order')->setMethods(array('prepareInvoice'))->getMock();
        $dummyInvoice = Mage::getModel('sales/order_invoice');
        $orderMock->expects($this->once())->method('prepareInvoice')->willReturn($dummyInvoice);
        $this->assertEquals(
            $dummyInvoice,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'createInvoice',
                array($orderMock, null, (object)array())
            )
        );
    }

    /**
     * @test
     * that createInvoice creates partial invoice if provided with an amount
     *
     * @covers ::createInvoice
     *
     * @throws ReflectionException if createInvoice method is not defined
     * @throws Exception if test class name is not defined
     */
    public function createInvoice_withCaptureAmount_createsPartialInvoice()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getBoltMaxCaptureAmountAfterRefunds', 'validateCaptureAmount'))
            ->getMock();
        $boltServiceOrderMock = $this->getClassPrototype('boltpay/service_order')
            ->setMethods(array('prepareInvoiceWithoutItems'))
            ->getMock();
        $dummyOrder = Mage::getModel('sales/order', array('grand_total' => 1000));
        $dummyInvoice = Mage::getModel('sales/order_invoice');
        $boltServiceOrderMock->expects($this->once())->method('prepareInvoiceWithoutItems')->with(0)->willReturn(
            $dummyInvoice
        );
        Bolt_Boltpay_TestHelper::stubModel('boltpay/service_order', $boltServiceOrderMock);
        $this->assertEquals(
            $dummyInvoice,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $currentMock,
                'createInvoice',
                array($dummyOrder, 100, (object)array())
            )
        );
    }

    /**
     * @test
     * that getBoltMaxCaptureAmountAfterRefunds returns total amount from transaction subtracted by refunded amount
     *
     * @covers ::getBoltMaxCaptureAmountAfterRefunds
     *
     * @throws ReflectionException if getBoltMaxCaptureAmountAfterRefunds method is not defined
     */
    public function getBoltMaxCaptureAmountAfterRefunds_always_returnsTotalSubtractedByRefundedAmount()
    {
        $transaction = new stdClass();
        $transaction->refunded_amount->amount = 1234500;
        $transaction->order->cart->total_amount->amount = 34567800;
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getBoltMaxCaptureAmountAfterRefunds',
            array(null, $transaction)
        );
        $this->assertEquals(333333, $result);
    }

    /**
     * @test
     * that validateCaptureAmount throws exception if one of the following is true
     * - capture amount is not provided
     * - capture amount is not numeric
     * - capture amount is negative
     * - order grand total is greater than total invoiced plus capture amount
     *
     * @covers ::validateCaptureAmount
     *
     * @dataProvider validateCaptureAmount_withVariousInvalidAmountsProvider
     *
     * @expectedException Exception
     * @expectedExceptionMessage Capture amount is invalid
     *
     * @param float $totalInvoiced order amount
     * @param float $grandTotal order amount
     * @param mixed $captureAmount provided as parameter to method
     *
     * @throws ReflectionException if validateCaptureAmount method is not defined
     */
    public function validateCaptureAmount_withVariousInvalidAmounts_throwsException(
        $totalInvoiced,
        $grandTotal,
        $captureAmount
    ) {
        $order = Mage::getModel(
            'sales/order',
            array('total_invoiced' => $totalInvoiced, 'grand_total' => $grandTotal)
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCaptureAmount',
            array($order, $captureAmount)
        );
    }

    /**
     * Data provider for {@see validateCaptureAmount_withVariousInvalidAmounts_throwsException}
     *
     * @return array containing total invoiced, grand total and capture amount
     */
    public function validateCaptureAmount_withVariousInvalidAmountsProvider()
    {
        return array(
            'Capture amount not set'                                  => array(
                'totalInvoiced' => 100,
                'grandTotal'    => 50,
                'captureAmount' => null
            ),
            'Capture amount not numeric'                              => array(
                'totalInvoiced' => 100,
                'grandTotal'    => 100,
                'captureAmount' => 'test'
            ),
            'Capture amount negative'                                 => array(
                'totalInvoiced' => 100,
                'grandTotal'    => 100,
                'captureAmount' => -100
            ),
            'Invalid amount range (invoiced + capture > grand total)' => array(
                'totalInvoiced' => 100,
                'grandTotal'    => 100,
                'captureAmount' => 100
            ),
        );
    }

    /**
     * @test
     * that validateCaptureAmount doesn't throw exception if provided with a valid capture amount
     *
     * @covers ::validateCaptureAmount
     *
     * @throws ReflectionException if validateCaptureAmount method is not defined
     */
    public function validateCaptureAmount_withValidCaptureAmount_succeeds()
    {
        $grandTotal = 400;
        $totalInvoiced = 100;
        $captureAmount = 200;
        $order = Mage::getModel(
            'sales/order',
            array('total_invoiced' => $totalInvoiced, 'grand_total' => $grandTotal)
        );
        $this->boltHelperMock->expects($this->never())->method('logWarning');
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateCaptureAmount',
            array($order, $captureAmount)
        );
    }

    /**
     * @test
     * that _handleBoltTransactionStatus updates payment object data depending on provided status
     *
     * @covers ::_handleBoltTransactionStatus
     *
     * @dataProvider _handleBoltTransactionStatus_withVariousTxStatusesProvider
     *
     * @param string $status transaction status to be handled
     * @param array  $paymentData expected to be subset of payment data after method call
     */
    public function _handleBoltTransactionStatus_withVariousTxStatuses_updatesPayment($status, $paymentData)
    {
        $payment = Mage::getModel('payment/info');
        $this->currentMock->_handleBoltTransactionStatus($payment, $status);
        $this->assertArraySubset($payment->getData(), $paymentData);
    }

    /**
     * Data provider for {@see _handleBoltTransactionStatus_withVariousTxStatuses}
     *
     * @return array containing transaction status and expected payment data subset after the method call
     */
    public function _handleBoltTransactionStatus_withVariousTxStatusesProvider()
    {
        return array(
            array('status' => 'completed', 'paymentData' => array('is_transaction_approved' => true)),
            array('status' => 'COMPLETED', 'paymentData' => array('is_transaction_approved' => true)),
            array('status' => 'CoMplEteD', 'paymentData' => array('is_transaction_approved' => true)),
            array('status' => 'authorized', 'paymentData' => array('is_transaction_approved' => true)),
            array('status' => 'aUthoRizEd', 'paymentData' => array('is_transaction_approved' => true)),
            array('status' => 'failed', 'paymentData' => array('is_transaction_denied' => true)),
            array('status' => 'fAilEd', 'paymentData' => array('is_transaction_denied' => true)),
            array('status' => 'pending', 'paymentData' => array('is_transaction_pending' => true)),
            array('status' => 'closed', 'paymentData' => array()),
        );
    }

    /**
     * @test
     * that transactionStatusToOrderStatus returns expected order status when provided with a transaction status
     * in case of unrecognized order status - 'new' is returned and an exception sent to Bugsnag
     *
     * @covers ::transactionStatusToOrderStatus
     *
     * @dataProvider transactionStatusToOrderStatus_withVariousTransactionStatusesProvider
     *
     * @param string $transactionStatus to be converted to order status
     * @param string $orderStatus expected result of the method call
     * @param bool   $expectNotifyException if exception notify about unrecognized order status should be expected
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function transactionStatusToOrderStatus_withVariousTransactionStatuses_returnsOrderStatus(
        $transactionStatus,
        $orderStatus,
        $expectNotifyException = false
    ) {
        if ($expectNotifyException) {
            $this->boltHelperMock->expects($this->once())->method('notifyException')->with(
                new Exception(
                    $this->boltHelperMock->__(
                        "'%s' is not a recognized order status.  '%s' is being set instead.",
                        $transactionStatus,
                        $orderStatus
                    )
                )
            );
            Bolt_Boltpay_TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        }

        $this->assertEquals($orderStatus, $this->currentMock->transactionStatusToOrderStatus($transactionStatus));
    }

    /**
     * Data provider for {@see transactionStatusToOrderStatus_withVariousTransactionStatuses_returnsOrderStatus}
     *
     * @return array containing transaction status, matching order status and notify exception expectation flag
     */
    public function transactionStatusToOrderStatus_withVariousTransactionStatusesProvider()
    {
        return array(
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'orderStatus'       => Mage_Sales_Model_Order::STATE_PROCESSING
            ),
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'orderStatus'       => Mage_Sales_Model_Order::STATE_PROCESSING
            ),
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'orderStatus'       => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
            ),
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
                'orderStatus'       => Bolt_Boltpay_Model_Payment::ORDER_DEFERRED
            ),
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
                'orderStatus'       => Mage_Sales_Model_Order::STATE_CANCELED
            ),
            array(
                'transactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
                'orderStatus'       => Mage_Sales_Model_Order::STATE_CANCELED
            ),
            array(
                'transactionStatus'     => Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD,
                'orderStatus'           => Mage_Sales_Model_Order::STATE_NEW,
                'expectNotifyException' => true
            ),
            array(
                'transactionStatus'     => Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING,
                'orderStatus'           => Mage_Sales_Model_Order::STATE_NEW,
                'expectNotifyException' => true
            ),
        );
    }

    /**
     * @test
     * that createInvoiceForHookRequest creates invoice for free orders
     *
     * @covers ::createInvoiceForHookRequest
     *
     * @throws ReflectionException if createInvoiceForHookRequest method is not set
     * @throws Varien_Exception if unable to create dummy rule
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy rule
     * @throws Mage_Core_Exception if unable to create dummy order
     */
    public function createInvoiceForHookRequest_withoutNewBoltCapturesAndOrderTotalZero_createsInvoice()
    {
        $couponCode = uniqid('FREE_ORDER_TEST');
        $ruleId = Bolt_Boltpay_CouponHelper::createDummyRule(
            $couponCode,
            array('discount_amount' => 100, 'apply_to_shipping' => 1)
        );
        $order = $this->createDummyOrder(array('coupon_code' => $couponCode));
        $this->assertEquals(0, $order->getGrandTotal());
        $payment = $order->getPayment();
        $transaction = new stdClass();
        $transaction->captures = array();
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'createInvoiceForHookRequest',
            array($payment, $transaction)
        );
        $this->assertEquals(1, $order->getInvoiceCollection()->count());
        Bolt_Boltpay_CouponHelper::deleteDummyRule($ruleId);
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that getNewBoltCaptures returns bolt captures from transaction without already invoiced ones
     *
     * @covers ::getNewBoltCaptures
     *
     * @throws Exception if test class name is not defined
     * @throws ReflectionException if getNewBoltCaptures method does not exist
     */
    public function getNewBoltCaptures_always_returnsBoltCapturesExceptInvoicedOnes()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getBoltCaptures', 'removeInvoicedCaptures'))
            ->getMock();
        $payment = Mage::getModel('payment/info');
        $transaction = new stdClass();
        $boltCaptures = array(123.45, 2345.67);
        $nonInvoicedCaptures = array(123.45);
        $currentMock->expects($this->once())->method('getBoltCaptures')->with($transaction)->willReturn($boltCaptures);
        $currentMock->expects($this->once())->method('removeInvoicedCaptures')->with($payment, $boltCaptures)
            ->willReturn($nonInvoicedCaptures);
        $this->assertEquals(
            $nonInvoicedCaptures,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $currentMock,
                'getNewBoltCaptures',
                array($payment, $transaction)
            )
        );
    }

    /**
     * @test
     * that getBoltCaptures returns successful capture amounts from transactions
     *
     * @covers ::getBoltCaptures
     *
     * @throws ReflectionException if getBoltCaptures method does not exist
     */
    public function getBoltCaptures_always_returnsSuccessfulBoltCaptureAmounts()
    {
        $transaction = new stdClass();
        $transaction->captures[0]->amount->amount = 123;
        $transaction->captures[0]->status = 'succeeded';
        $transaction->captures[1]->amount->amount = 345;
        $transaction->captures[1]->status = 'failed';
        $transaction->captures[2]->amount->amount = 567;
        $transaction->captures[2]->status = 'failed';
        $transaction->captures[3]->amount->amount = 987;
        $transaction->captures[3]->status = 'succeeded';
        $this->assertEquals(
            array(123, 987),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getBoltCaptures',
                array($transaction)
            )
        );
    }

    /**
     * @test
     * that removeInvoicedCaptures removes all bolt captures that match existing invoices by amount
     *
     * @covers ::removeInvoicedCaptures
     *
     * @throws ReflectionException
     */
    public function removeInvoicedCaptures_always_removesInvoicedCaptures()
    {
        $order = Mage::getModel('sales/order');
        $payment = Mage::getModel('payment/info', array('order' => $order));
        $invoices = array(
            Mage::getModel('sales/order_invoice')->setGrandTotal(123.45),
            Mage::getModel('sales/order_invoice')->setGrandTotal(3456.78),
            Mage::getModel('sales/order_invoice')->setGrandTotal(98765.43),
            Mage::getModel('sales/order_invoice')->setGrandTotal(34567.89),
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty($order, '_invoices', $invoices);
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'removeInvoicedCaptures',
            array($payment, array(12345, 3456789, 234567, 123456, 7890))
        );
        $this->assertEquals(
            array(
                234567,
                123456,
                7890,
            ),
            array_values($result)
        );
    }

    /**
     * @test
     * that preparePaymentAndAddTransaction calls {@see Bolt_Boltpay_Model_Payment::preparePaymentForTransaction}
     * and {@see Bolt_Boltpay_Model_Payment::addPaymentTransaction}
     *
     * @covers ::preparePaymentAndAddTransaction
     *
     * @throws Exception if test class name is not set
     */
    public function preparePaymentAndAddTransaction_always_preparesAndAddsPaymentTransaction()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array('bolt_reference' => self::BOLT_TRANSACTION_REFERENCE),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('grand_total' => 123.45, 'total_paid' => 0)
                )
            )
        );
        $invoice = Mage::getModel('sales/order_invoice');
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('preparePaymentForTransaction', 'addPaymentTransaction'))
            ->getMock();
        $currentMock->expects($this->once())->method('preparePaymentForTransaction')->with($payment, 1);
        $currentMock->expects($this->once())->method('addPaymentTransaction')->with($payment, $invoice);
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $currentMock,
            'preparePaymentAndAddTransaction',
            array($payment, $invoice, 1)
        );
    }

    /**
     * @test
     * that preparePaymentForTransaction doesn't set shouldCloseParentTransaction if capture was partial
     * (total due is greater than 0)
     *
     * @covers ::preparePaymentForTransaction
     *
     * @throws ReflectionException if preparePaymentForTransaction method is not defined
     */
    public function preparePaymentForTransaction_withPartialCapture_shouldNotCloseParentTransaction()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array('bolt_reference' => self::BOLT_TRANSACTION_REFERENCE),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('grand_total' => 123.45, 'total_paid' => 0)
                )
            )
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'preparePaymentForTransaction',
            array($payment, 0)
        );
        $this->assertEquals($payment->getParentTransactionId(), self::BOLT_TRANSACTION_REFERENCE);
        $this->assertStringStartsWith('92XB-GBX4-T49L-capture-', $payment->getTransactionId());
        $this->assertEquals(0, $payment->getIsTransactionClosed());
        $this->assertNotTrue($payment->getShouldCloseParentTransaction());
    }

    /**
     * @test
     * that preparePaymentForTransaction sets shouldCloseParentTransaction to true if capture was complete
     * (total due is 0)
     *
     * @covers ::preparePaymentForTransaction
     *
     * @throws ReflectionException if preparePaymentForTransaction method is not defined
     */
    public function preparePaymentForTransaction_withCompleteCapture_shouldCloseParentTransaction()
    {
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array('bolt_reference' => self::BOLT_TRANSACTION_REFERENCE),
                'order'                  => Mage::getModel(
                    'sales/order',
                    array('grand_total' => 123.45, 'total_paid' => 123.45)
                )
            )
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'preparePaymentForTransaction',
            array($payment, 0)
        );
        $this->assertEquals($payment->getParentTransactionId(), self::BOLT_TRANSACTION_REFERENCE);
        $this->assertStringStartsWith('92XB-GBX4-T49L-capture-', $payment->getTransactionId());
        $this->assertEquals(0, $payment->getIsTransactionClosed());
        $this->assertTrue($payment->getShouldCloseParentTransaction());
    }

    /**
     * @test
     * that addPaymentTransaction adds capture transaction to payment with provided invoice
     *
     * @covers ::addPaymentTransaction
     *
     * @throws ReflectionException if addPaymentTransaction method is not defined
     */
    public function addPaymentTransaction_always_addsTransactionToPayment()
    {
        $payment = $this->getClassPrototype('payment/info')
            ->setMethods(array('getOrder', 'getPreparedMessage', 'addTransaction'))
            ->getMock();
        $invoice = Mage::getModel('sales/order_invoice')->setGrandTotal(123.45);
        $payment->expects($this->once())->method('getOrder')->willReturn(Mage::getModel('sales/order'));
        $payment->expects($this->once())->method('addTransaction')->with(
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
            $invoice,
            true,
            $this->matchesRegularExpression('/(.)*Captured amount of(.)*123(\.|,)45(.)*online\./')
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'addPaymentTransaction',
            array($payment, $invoice)
        );
    }

    /**
     * @test
     * that isTransactionStatusChanged if new status is different to previous status
     * or new status is refund, authorized or completed
     *
     * @covers ::isTransactionStatusChanged
     *
     * @dataProvider isTransactionStatusChanged_withVariousTransactionStatusesProvider
     *
     * @param string $newTransactionStatus to be provided as parameter to method call
     * @param string $prevTransactionStatus to be provided as parameter to method call
     * @param bool   $expectedResult of the method call
     *
     * @throws ReflectionException
     */
    public function isTransactionStatusChanged_withVariousTransactionStatuses_determinesIfTransactionStatusIsChanged(
        $newTransactionStatus,
        $prevTransactionStatus,
        $expectedResult
    ) {
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isTransactionStatusChanged',
                array($newTransactionStatus, $prevTransactionStatus)
            )
        );
    }

    /**
     * Data provider for
     * @see isTransactionStatusChanged_withVariousTransactionStatuses_determinesIfTransactionStatusIsChanged
     *
     * @return array containing prev and new transaction status and expected result of the call
     */
    public function isTransactionStatusChanged_withVariousTransactionStatusesProvider()
    {
        return array(
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                'expectedResult'        => false
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD,
                'expectedResult'        => false
            ),
        );
    }

    /**
     * @test
     * that validateWebHook throws exception if previous transaction status is invalid
     *
     * @covers ::validateWebHook
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Invalid previous state: invalid-state
     *
     * @throws ReflectionException if validateWebHook method is not defined
     */
    public function validateWebHook_withInvalidPreviousState_throwsException()
    {
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateWebHook',
            array(null, 'invalid-state')
        );
    }

    /**
     * @test
     * that validateWebHook throws exception if previous transaction status is invalid
     *
     * @covers ::validateWebHook
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage validNextStatuses is null
     *
     * @throws ReflectionException if validateWebHook method is not defined
     */
    public function validateWebHook_withValidNextStatusesNull_throwsException()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            '_validStateTransitions',
            array('prev-state' => null)
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateWebHook',
            array(null, 'prev-state')
        );
    }

    /**
     * @test
     * that validateWebHook throws exception if state transition is invalid
     *
     * @covers ::validateWebHook
     *
     * @expectedException Bolt_Boltpay_InvalidTransitionException
     * @expectedExceptionMessage Cannot transition a transaction from completed to cancelled
     *
     * @throws ReflectionException if validateWebHook method is not defined
     */
    public function validateWebHook_withInvalidTransition_throwsException()
    {
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateWebHook',
            array(Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED, Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED)
        );
    }

    /**
     * @test
     * that validateWebHook returns true if the status transition is valid
     *
     * @covers ::validateWebHook
     *
     * @throws ReflectionException if validateWebHook method is not defined
     */
    public function validateWebHook_withValidWebHook_returnsTrue()
    {
        $this->assertTrue(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'validateWebHook',
                array(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
                )
            )
        );
    }

    /**
     * @test
     * that isCaptureRequest determines if request is capture based on provided transaction statuses
     *
     * @covers ::isCaptureRequest
     *
     * @param string $newTransactionStatus provided to method call
     * @param string $prevTransactionStatus provided to method call
     * @param bool   $expectedResult of the method call
     *
     * @throws ReflectionException if isCaptureRequest method is undefined
     */
    public function isCaptureRequest_withVariousTxStatuses_determinesIsRequestCapture(
        $newTransactionStatus,
        $prevTransactionStatus,
        $expectedResult
    ) {
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isCaptureRequest',
                array($newTransactionStatus, $prevTransactionStatus)
            )
        );
    }

    /**
     * Data provider for {@see isCaptureRequest_withVariousTxStatuses_determinesIsRequestCapture}
     *
     * @return array containing previous and new transaction status and expected result of the method call
     */
    public function isCaptureRequest_withVariousTxStatusesProvider()
    {
        return array(
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'prevTransactionStatus' => null,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'expectedResult'        => false
            ),
        );
    }

    /**
     *
     * @test
     * that updateRequiresBoltTransaction determines whether an transaction update needs a copy of
     * the Bolt transaction to process
     *
     * @covers ::updateRequiresBoltTransaction
     *
     * @dataProvider updateRequiresBoltTransaction_withVariousTxStatusesProvider
     *
     * @param string $newTransactionStatus The new transaction directive from Bolt
     * @param string $prevTransactionStatus The previous transaction directive from Bolt
     * @param bool   $expectedResult of the method call
     */
    public function updateRequiresBoltTransaction_withVariousTxStatuses_determinesIfTxNeedsToBeCopied(
        $newTransactionStatus,
        $prevTransactionStatus,
        $expectedResult
    ) {
        $this->assertEquals(
            $expectedResult,
            $this->currentMock->updateRequiresBoltTransaction(
                $newTransactionStatus,
                $prevTransactionStatus
            )
        );
    }

    /**
     * Data provider for {@see updateRequiresBoltTransaction_withVariousTxStatuses_determinesIfTxNeedsToBeCopied}
     *
     * @return array containing new and previous transaction status and expected result
     */
    public function updateRequiresBoltTransaction_withVariousTxStatusesProvider()
    {
        return array(
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'prevTransactionStatus' => Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                'prevTransactionStatus' => null,
                'expectedResult'        => true
            ),
            array(
                'newTransactionStatus'  => Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
                'prevTransactionStatus' => null,
                'expectedResult'        => true
            ),
        );
    }

    /**
     * @test
     * that product inventory is restored after order is voided.
     *
     * @covers ::handleVoidTransactionUpdate
     * @dataProvider handleVoidTransactionUpdate_forOrdersInValidInitialStateProvider
     *
     * @param string $orderState The state that the order should be in just prior to handling the void
     * @throws Mage_Core_Exception if there is a problem creating the dummy order for this test
     */
    public function handleVoidTransactionUpdate_forOrdersInValidInitialState_restoresStockToPreOrderLevel($orderState)
    {
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(static::$productId);
        $initialQty = (int)$storeProduct->getQty();

        $order = $this->handleTransactionUpdateSetUp($storeProduct, 2);
        $order->setState($orderState);

        $this->currentMock->handleVoidTransactionUpdate($order->getPayment());

        // After the hook is triggered order should be deleted and product stock restored
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(static::$productId);
        $this->assertEquals($initialQty, (int)$storeProduct->getQty());
        $this->assertEquals('canceled', $order->getState());

        // Delete dummy order
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Provides the various valid previous order states that can be canceled
     *
     * @return array Single parameter of order state to initialize created order in test
     */
    public function handleVoidTransactionUpdate_forOrdersInValidInitialStateProvider()
    {
        return array(
            'Order in pending payment state' => array(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT),
            'Order in new state'             => array(Mage_Sales_Model_Order::STATE_NEW)
        );
    }

    /**
     * @test
     * that handleVoidTransactionUpdate closes auth transaction if it's not already closed
     * and canVoidAuthorizationCompletely flag is set to false
     *
     * @covers ::handleVoidTransactionUpdate
     *
     * @throws Exception if test class name is not defined
     */
    public function handleVoidTransactionUpdate_whenAuthTransactionIsNotClosed_closesAuthTransaction()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getVoidMessage'))->getMock();
        /** @var Mage_Payment_Model_Info|MockObject $paymentMock */
        $paymentMock = $this->getClassPrototype('payment/info')
            ->setMethods(array('getAuthorizationTransaction', 'getOrder'))
            ->getMock();
        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('addStatusHistoryComment', 'save'))
            ->getMock();
        $authTransactionMock = $this->getClassPrototype('Mage_Sales_Model_Order_Payment_Transaction')
            ->setMethods(array('getIsClosed', 'closeAuthorization', 'canVoidAuthorizationCompletely'))
            ->getMock();
        $currentMock->expects($this->once())->method('getVoidMessage')->willReturn('DUMMY VOID MESSAGE');
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAuthorizationTransaction')->willReturn($authTransactionMock);
        $authTransactionMock->expects($this->once())->method('getIsClosed')->willReturn(false);
        $authTransactionMock->expects($this->once())->method('closeAuthorization');
        $orderMock->expects($this->once())->method('addStatusHistoryComment')->with('DUMMY VOID MESSAGE');
        $orderMock->expects($this->once())->method('save');
        $currentMock->handleVoidTransactionUpdate($paymentMock);
    }

    /**
     * @test
     * that getVoidMessage returns void message based on payment and transaction provided
     *
     * @covers ::getVoidMessage
     *
     * @throws ReflectionException if getVoidMessage is not defined
     */
    public function getVoidMessage_always_returnsVoidMessage()
    {
        $payment = Mage::getModel('payment/info', array('order' => Mage::getModel('sales/order')));
        $transaction = new Varien_Object(array('html_txn_id' => self::BOLT_MERCHANT_TRANSACTION_ID));
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getVoidMessage',
            array($payment, $transaction, 123)
        );

        $this->assertRegExp( # Supports American and European locale
            sprintf(
                '/BOLT notification: Transaction authorization has been voided. Amount:(.)*123(\.|,)00(.)*\. Transaction ID: "%s"\./',
                self::BOLT_MERCHANT_TRANSACTION_ID
            ),
            $result
        );
    }

    /**
     * @test
     * that isPartialRefundFixingMismatch returns true if Magento and Bolt totals after refunds don't match
     *
     * @covers ::isPartialRefundFixingMismatch
     *
     * @throws ReflectionException if isPartialRefundFixingMismatch is not defined
     */
    public function isPartialRefundFixingMismatch_ifMagentoAndBoltTotalsAfterRefundsAreNotEqual_returnsTrue()
    {
        $boltTransaction = new stdClass();
        $boltTransaction->order->cart->total_amount->amount = 10000;
        $this->assertTrue(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isPartialRefundFixingMismatch',
                array(
                    Mage::getModel('sales/order', array('grand_total' => 123.45)),
                    0,
                    $boltTransaction
                )
            )
        );
    }

    /**
     * @test
     * that isPartialRefundFixingMismatch returns false if Magento and Bolt totals match
     *
     * @covers ::isPartialRefundFixingMismatch
     *
     * @throws ReflectionException if isPartialRefundFixingMismatch is not defined
     */
    public function isPartialRefundFixingMismatch_withBoltTotalEqualMagentoTotal_returnsFalse()
    {
        $boltTransaction = new stdClass();
        $boltTransaction->order->cart->total_amount->amount = 12345;
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isPartialRefundFixingMismatch',
                array(
                    Mage::getModel('sales/order', array('grand_total' => 123.45)),
                    0,
                    $boltTransaction
                )
            )
        );
    }

    /**
     * @test
     * that denyPayment calls review method with reject as review parameter
     *
     * @covers ::denyPayment
     *
     * @throws Exception if test class name is not set
     */
    public function denyPayment_always_executesReviewWithRejectDecision()
    {
        $payment = Mage::getModel('payment/info');
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('review'))
            ->getMock();
        $currentMock->expects($this->once())->method('review')->with(
            $payment,
            Bolt_Boltpay_Model_Payment::DECISION_REJECT
        );
        $currentMock->denyPayment($payment);
    }

    /**
     * @test
     * that acceptPayment calls review method with approve as review parameter
     *
     * @covers ::acceptPayment
     *
     * @throws Exception if test class name is not defined
     */
    public function acceptPayment_always_executesReviewWithApproveDecision()
    {
        $payment = Mage::getModel('payment/info');
        /** @var MockObject|Bolt_Boltpay_Model_Payment $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('review'))
            ->getMock();
        $currentMock->expects($this->once())->method('review')->with(
            $payment,
            Bolt_Boltpay_Model_Payment::DECISION_APPROVE
        );
        $currentMock->acceptPayment($payment);
    }

    /**
     * @test
     * that review notifies exception and returns false if transaction id is not set to payment
     *
     * @covers ::review
     *
     * @throws Exception if test class name is not defined
     */
    public function review_withTransactionIdNotSet_returnsFalse()
    {
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with(
            new Mage_Core_Exception('Waiting for a transaction update from Bolt. Please retry after 60 seconds.')
        );
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'review',
                array(
                    Mage::getModel(
                        'payment/info',
                        array('additional_information' => array('bolt_merchant_transaction_id' => null))
                    ),
                    Bolt_Boltpay_Model_Payment::DECISION_APPROVE
                )
            )
        );
    }

    /**
     * @test
     * that review notifies exception and returns false if API response for review has empty reference
     *
     * @covers ::review
     *
     * @throws ReflectionException if review method doesn't exist
     */
    public function review_whenAPIResponseHasEmptyReference_returnsFalse()
    {
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with(
            new Mage_Core_Exception('Bad review response. Empty transaction reference')
        );
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'review',
            array(
                'transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                'decision'       => Bolt_Boltpay_Model_Payment::DECISION_APPROVE,
            )
        )->willReturn((object)array('reference' => null));
        $this->assertFalse(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'review',
                array(
                    Mage::getModel(
                        'payment/info',
                        array(
                            'additional_information' => array(
                                'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID
                            )
                        )
                    ),
                    Bolt_Boltpay_Model_Payment::DECISION_APPROVE
                )
            )
        );
    }

    /**
     * @test
     * that review updates reviewed order history if Bolt API response is valid
     *
     * @covers ::review
     *
     * @throws ReflectionException if review method is not defined
     * @throws Exception if test class name is not defined
     */
    public function review_whenAPIResponseIsValid_updatesReviewedOrderHistory()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'updateReviewedOrderHistory'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->boltHelperMock->expects($this->once())->method('transmit')->with(
            'review',
            array(
                'transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID,
                'decision'       => Bolt_Boltpay_Model_Payment::DECISION_APPROVE,
            )
        )->willReturn((object)array('reference' => self::BOLT_TRANSACTION_REFERENCE));
        $payment = Mage::getModel(
            'payment/info',
            array(
                'additional_information' => array(
                    'bolt_merchant_transaction_id' => self::BOLT_MERCHANT_TRANSACTION_ID
                )
            )
        );
        $currentMock->expects($this->once())->method('updateReviewedOrderHistory')->with($payment);
        $this->assertTrue(
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $currentMock,
                'review',
                array(
                    $payment,
                    Bolt_Boltpay_Model_Payment::DECISION_APPROVE
                )
            )
        );
    }

    /**
     * @test
     * that updateReviewedOrderHistory adds status comment to order stating that it was force approved by current admin
     *
     * @covers ::updateReviewedOrderHistory
     *
     * @throws ReflectionException if updateReviewedOrderHistory method doesn't exist
     */
    public function updateReviewedOrderHistory_ifApproved_addsStatusCommentToOrder()
    {
        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('addStatusHistoryComment', 'save'))
            ->getMock();
        $orderMock->expects($this->once())->method('addStatusHistoryComment')
            ->with(sprintf('Force approve order by %s %s.', 'Admin', 'Admin'));
        $orderMock->expects($this->once())->method('save');
        Mage::getSingleton('admin/session')->setUser(
            new Varien_Object(array('firstname' => 'Admin', 'lastname' => 'Admin'))
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'updateReviewedOrderHistory',
            array(
                Mage::getModel('payment/info', array('order' => $orderMock)),
                Bolt_Boltpay_Model_Payment::DECISION_APPROVE
            )
        );
    }

    /**
     * @test
     * that updateReviewedOrderHistory adds status comment to order stating that
     * rejection was confirmed by current admin
     *
     * @covers ::updateReviewedOrderHistory
     *
     * @throws ReflectionException if updateReviewedOrderHistory method doesn't exist
     */
    public function updateReviewedOrderHistory_ifRejected_addsStatusCommentToOrder()
    {
        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('addStatusHistoryComment', 'save'))
            ->getMock();
        $orderMock->expects($this->once())->method('addStatusHistoryComment')
            ->with(sprintf('Confirm order rejection by %s %s.', 'Admin', 'Admin'));
        $orderMock->expects($this->once())->method('save');
        Mage::getSingleton('admin/session')->setUser(
            new Varien_Object(array('firstname' => 'Admin', 'lastname' => 'Admin'))
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'updateReviewedOrderHistory',
            array(
                Mage::getModel('payment/info', array('order' => $orderMock)),
                Bolt_Boltpay_Model_Payment::DECISION_REJECT
            )
        );
    }

    /**
     * Initializes the mock order used for testing transaction webhooks
     *
     * @param string $initialOrderState State to be set when creating test order. In some cases it will be
     *                                  different from $previousOrderState
     * @param float  $baseGrandTotal Base grand total for the order. If equals to 0 the order state
     *                                  will be set to "complete". If greater than zero initial order
     *                                  state will be set to $previousOrderState while saving the order.
     * @param float  $totalRefunded Total refund for the order. If greater than 0 order will be closed
     *
     * @return Mage_Sales_Model_Order Order object we will use for assertion
     *
     * @throws Mage_Core_Exception Throws exception in case there is no Bolt reference set in the payment object
     * @throws Exception if unable to save order
     */
    private function createDummyOrderWithInitialState($initialOrderState, $baseGrandTotal = 5.0, $totalRefunded = 0.0)
    {
        // Create dummy order
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(
            self::$productId,
            1,
            Bolt_Boltpay_Model_Payment::METHOD_CODE
        );

        // Set refund and base grand total
        $order->setTotalRefunded($totalRefunded);
        $order->setBaseGrandTotal($baseGrandTotal);
        $order->setGrandTotal($baseGrandTotal);

        // Set and confirm initial state of the order. This is important starting point
        $order->setState($initialOrderState);
        $order->save();

        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('bolt_reference', self::BOLT_TRANSACTION_REFERENCE);

        return $order;
    }

    /**
     * Creates dummy order and invoices the order for full amount
     *
     * @return Mage_Sales_Model_Order dummy order
     *
     * @throws Exception if unable to save order
     */
    private function createMockOrderWithInvoice()
    {
        $order = $this->createDummyOrder();
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);
        $invoice->save();

        return $order;
    }

    /**
     * Creates completely valid mock order
     *
     * @param array $quoteData to be added to quote before submit
     *
     * @return Mage_Sales_Model_Order dummy order
     *
     * @throws Mage_Core_Exception if unable to add product to cart or unable to import payment data
     */
    private function createDummyOrder($quoteData = array())
    {
        $cart = Mage::getModel('checkout/cart', array('quote' => Mage::getModel('sales/quote')));
        $cart->addProduct(self::$productId, 1);
        $address = array(
            'firstname'  => 'Luke',
            'lastname'   => 'Skywalker',
            'street'     => 'Sample Street 10',
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'telephone'  => '+1 867 345 123 5681',
            'country_id' => 'US',
            'region_id'  => 12
        );
        $quote = $cart->getQuote();
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address)->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate')
            ->collectShippingRates()
            ->setPaymentMethod('boltpay');
        $quote->addData($quoteData);
        $quote->reserveOrderId();
        $quote->getPayment()->importData(array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE));
        $quote->save();
        $service = Mage::getModel('sales/service_quote', $quote);
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
        $service->submitAll();
        Bolt_Boltpay_Helper_Data::$fromHooks = false;
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($service->getOrder()->getId());
        return $order;
    }
}