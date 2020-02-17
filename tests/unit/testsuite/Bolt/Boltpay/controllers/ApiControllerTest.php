<?php

require_once 'Bolt/Boltpay/controllers/ApiController.php';
require_once 'TestHelper.php';
require_once 'MockingTrait.php';
require_once 'OrderHelper.php';
require_once 'ProductProvider.php';

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class Bolt_Boltpay_ApiControllerTest
 *
 * Unit and Integration test for the Bolt_Boltpay_ApiController
 *
 * @coversDefaultClass Bolt_Boltpay_ApiController
 */
class Bolt_Boltpay_ApiControllerTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_BoltGlobalTrait;
    use Bolt_Boltpay_MockingTrait;

    /** @var string Dummy transaction reference */
    const REFERENCE = 'TEST-BOLT-TRNX';

    /** @var int Dummy order increment id */
    const ORDER_INCREMENT_ID = 457;

    /** @var string Dummy Bolt transaction id */
    const TRANSACTION_ID = 'TRboltx0test1';

    /** @var string Dummy Bolt transaction display id */
    const DISPLAY_ID = '1234567890|44444';

    /** @var int Dummy immutable quote id */
    const IMMUTABLE_QUOTE_ID = 124;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_ApiController';

    /**
     * @var PHPUnit_Framework_MockObject_MockBuilder The builder for a generically mocked API controller
     *      that is the subject of these test
     */
    private $_apiControllerBuilder;

    /**
     * @var Mage_Sales_Model_Order  Disposable order used within each test
     */
    private static $_dummyOrder;

    /**
     * @var int ID of the dummy product.  This is primarily used to create orders and for DB cleanup
     */
    private static $_dummyProductId;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Order Mocked instance of Mage::getModel('boltpay/order')
     */
    private $boltOrderModelMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Bolt_Boltpay_ApiController Mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Core_Controller_Response_Http Mocked instance of Magento response object
     */
    private $responseMock;

    /**
     * @var MockObject|Mage_Sales_Model_Order Mocked instance of Magento order object
     */
    private $orderMock;

    /**
     * @var MockObject|Mage_Sales_Model_Order_Payment Mocked instance of Magento order payment object
     */
    private $paymentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Payment Mocked instance of Bolt payment object
     */
    private $boltPaymentMethodInstanceMock;

    /**
     * Generates common objects used in all test
     * @throws Exception
     */
    public static function setUpBeforeClass()
    {
        self::$_dummyProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            'api_controller_test_product',
            array(),
            50
        );
    }

    /**
     * Sets up a new mock builder for a generically mocked Bolt_Boltpay_ApiController between each test method.  Use
     * {@see Bolt_Boltpay_ApiControllerTest::$_apiControllerBuilder}'s setMethods method from within the test method
     * for further refinement of stubbed behavior
     *
     * @throws Zend_Controller_Request_Exception    on unexpected problem in creating the controller
     * @throws Mage_Core_Exception                  on failure to create a dummy order
     * @throws ReflectionException                  if unable to stub boltpay/order model
     * @throws Exception                            if test class name is not defined
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'sendResponse', 'getResponse', 'getRequestData'))->getMock();
        $this->responseMock = $this->getClassPrototype('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader'))->getMock();
        $this->_apiControllerBuilder = $this->getTestClassPrototype()
            ->setConstructorArgs(
                array(new Mage_Core_Controller_Request_Http(), new Mage_Core_Controller_Response_Http())
            )
            ->setMethods(null);
        $this->boltHelperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array(
                    'fetchTransaction',
                    'getImmutableQuoteIdFromTransaction',
                    'logInfo',
                    'logWarning',
                    'notifyException',
                    'doFilterEvent'
                )
            )
            ->getMock();
        $this->orderMock = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(array('getStatus', 'isObjectNew', 'load', 'getPayment', 'getIncrementId'))->getMock();
        $this->paymentMock = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')
            ->setMethods(
                array(
                    'getAdditionalInformation',
                    'setTransactionId',
                    'getMethodInstance',
                    'setAdditionalInformation',
                    'getAuthorizationTransaction',
                    'getLastTransId',
                    'save',
                    'setData',
                    'getMethod'
                )
            )
            ->getMock();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getIncrementId')->willReturn(self::ORDER_INCREMENT_ID);
        $this->boltPaymentMethodInstanceMock = $this->getClassPrototype('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('setStore', 'handleTransactionUpdate'))->getMock();

        $this->paymentMock->method('getMethod')->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $this->paymentMock->method('getMethodInstance')->willReturn($this->boltPaymentMethodInstanceMock);

        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->currentMock->method('getResponse')->willReturn($this->responseMock);

        $this->boltOrderModelMock = $this->getClassPrototype('Bolt_Boltpay_Model_Order')
            ->setMethods(array('getOrderByQuoteId', 'receiveOrder', 'createOrder', 'removePreAuthOrder'))->getMock();

        self::$_dummyOrder = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$_dummyProductId, 2, 'boltpay');

        TestHelper::stubModel('boltpay/order', $this->boltOrderModelMock);
    }

    /**
     * Restores resources in between each test method
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    public function tearDown()
    {
        Bolt_Boltpay_OrderHelper::deleteDummyOrder(self::$_dummyOrder);
        TestHelper::restoreOriginals();
    }

    /**
     * Restores resources after all test have completed
     *
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy product
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$_dummyProductId);
    }

    /**
     * @test
     * that a pending hook does not attempt to fetch a transaction via the Bolt API
     * and that it successfully processes without throwing and exception
     *
     * @covers ::hookAction
     */
    public function hookAction_pendingHook_returns200() {

        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'boltHelper', 'sendResponse'])
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = 'TEST-BOLT-TRNX';
        $stubbedRequestData->id = 'TRboltx0test1';
        $stubbedRequestData->type = 'pending';
        $stubbedRequestData->display_id = self::$_dummyOrder->getIncrementId();

        $payment = self::$_dummyOrder->getPayment();
        $payment->setAdditionalInformation('bolt_reference', $stubbedRequestData->reference)->save();

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);

        /** @var Bolt_Boltpay_Helper_Data|PHPUnit_Framework_MockObject_MockObject $stubbedBoltHelper */
        $stubbedBoltHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('fetchTransaction', 'logInfo'))
            ->getMock();

        $stubbedBoltHelper
            ->expects($this->never())
            ->method('fetchTransaction');

        $apiControllerMock->method('boltHelper')->willReturn($stubbedBoltHelper);
        ///////////////////////////////////////////////////////////////////////

        $apiControllerMock
            ->expects($this->exactly(2))
            ->method('sendResponse')
            ->with($this->equalTo(200))
            ->willThrowException(
                new Bolt_Boltpay_InvalidTransitionException(
                    "pending",
                    "pending",
                    "Simulated exit"
                )
            );

        ######################################
        # Calling the subject method
        ######################################
        try {
            $apiControllerMock->hookAction();
        } catch (Bolt_Boltpay_InvalidTransitionException $bite) {
            if ( $bite->getMessage() !== "Simulated exit" ) { throw $bite; }
        }
        ######################################
    }

    /**
     * @test
     * that irreversibly rejected hooks do not trigger the "receiving order" behavior which means order
     * finalization including sending out order notification emails, associating the order with a transaction and
     * triggering post order creation events.
     *
     * @covers ::hookAction
     */
    public function hookAction_ifIrreversiblyRejectedHook_orderNotProcessed()
    {

        /** @var Bolt_Boltpay_ApiController|MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(array('getRequestData', 'boltHelper', 'sendResponse'))
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_IRREVERSIBLE;
        $stubbedRequestData->display_id = self::$_dummyOrder->getIncrementId();

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);

        $this->boltHelperMock
            ->expects($this->once())
            ->method('fetchTransaction')->willReturn($stubbedRequestData);

        $apiControllerMock->method('boltHelper')->willReturn($this->boltHelperMock);
        ///////////////////////////////////////////////////////////////////////

        $payment = self::$_dummyOrder->getPayment();

        // Pre-auth orders will not yet have an authorization nor Bolt transaction reference
        $this->assertFalse(
            $payment->getAdditionalInformation('bolt_reference')
            || $payment->getAuthorizationTransaction()
            || $payment->getLastTransId()
        );

        $apiControllerMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with($this->equalTo(200));

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################

        # demonstrate that no attempt was made to mark the order as authorized
        $this->assertFalse(
            $payment->getAdditionalInformation('bolt_reference')
            || $payment->getAuthorizationTransaction()
            || $payment->getLastTransId()
        );

        # demonstrate that no attempt was made to cancel the order.
        $this->assertFalse(self::$_dummyOrder->isCanceled());
    }

    /**
     * @test
     * that the non-piped format of display_id is supported for failed_payment hooks
     *
     * @covers ::hookAction
     */
    public function hookAction_withStandardDisplayId_supportedForFailedPaymentHooks()
    {

        /** @var Bolt_Boltpay_ApiController|MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(array('getRequestData', 'sendResponse'))
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'failed_payment';
        $stubbedRequestData->display_id = '9876543210';

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'  => 'success',
                    'message' => $this->boltHelper()->__(
                        'Order %s has been canceled prior to authorization',
                        "9876543210"
                    )
                )
            );

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################
    }

    /**
     * @test
     * that the piped format of display_id is supported for failed_payment hooks
     *
     * @covers ::hookAction
     */
    public function hookAction_withPipedDisplayId_supportedForFailedPayment()
    {

        /** @var Bolt_Boltpay_ApiController|MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(array('getRequestData', 'handleFailedPaymentHook', 'sendResponse'))
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'failed_payment';
        $stubbedRequestData->display_id = self::DISPLAY_ID;

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'  => 'success',
                    'message' => $this->boltHelper()->__(
                        'Order %s has been canceled prior to authorization',
                        "1234567890"
                    )
                )
            );

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################
    }

    /**
     * @test
     * that for non-Bolt orders, processing is stopped immediately with an exception and a 422 response to Bolt
     *
     * @covers ::hookAction
     */
    public function hookAction_forNonBoltOrder_exitsImmediatelyWithException() {
        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'boltHelper', 'sendResponse'])
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = 'TEST-BOLT-TRNX';
        $stubbedRequestData->id = 'TRboltx0test1';
        $stubbedRequestData->type = 'pending';
        $stubbedRequestData->display_id = self::$_dummyOrder->getIncrementId();

        /** @var Bolt_Boltpay_Helper_Data|PHPUnit_Framework_MockObject_MockObject $stubbedBoltHelper */
        $stubbedBoltHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logException'))
            ->getMock();

        $stubbedBoltHelper
            ->expects($this->once())
            ->method('logException')->willReturnCallback(
                function($exception, $metaData) {
                    $this->assertEquals(
                        "Order #".self::$_dummyOrder->getIncrementId()." is not a Bolt order.  Order type: paypal_express",
                        $exception->getMessage()
                    );
                }
            )
        ;

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock->method('boltHelper')->willReturn($stubbedBoltHelper);

        $apiControllerMock->expects($this->once())->method('sendResponse')->with(422);
        ///////////////////////////////////////////////////////////////////////

        self::$_dummyOrder->getPayment()->setMethod('paypal_express')->save();

        $apiControllerMock->hookAction();
    }

    /**
     * @test
     * that hookAction handles discounts.code.apply hook type by calling {@see Bolt_Boltpay_Model_Coupon}
     *
     * @covers ::hookAction
     *
     * @throws ReflectionException if unable to stub model
     */
    public function hookAction_ifHookTypeIsDiscountCode_appliesCoupon()
    {

        /** @var Bolt_Boltpay_ApiController|MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(array('getRequestData', 'handleFailedPaymentHook', 'sendResponse'))
            ->getMock();

        $httpCode = 200;
        $responseData = array('status' => 'success');

        $couponModelMock = $this->getClassPrototype('Bolt_Boltpay_Model_Coupon')->getMock();
        $couponModelMock->expects($this->once())->method('applyCoupon');
        $couponModelMock->expects($this->once())->method('getHttpCode')->willReturn($httpCode);
        $couponModelMock->expects($this->once())->method('getResponseData')->willReturn($responseData);

        TestHelper::stubModel('boltpay/coupon', $couponModelMock);

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'discounts.code.apply';
        $stubbedRequestData->display_id = self::DISPLAY_ID;

        TestHelper::setNonPublicProperty($apiControllerMock, 'payload', json_encode($stubbedRequestData));
        $couponModelMock->expects($this->once())->method('setupVariables')->with($stubbedRequestData);

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                $httpCode,
                $responseData
            );

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################
    }

    /**
     * @test
     * that if hook type is credit the amount is read from request data before being delegated to payment
     *
     * @covers ::hookAction
     */
    public function hookAction_ifHookTypeIsCredit_usesTransactionAmountFromRequestData()
    {
        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'credit';
        $stubbedRequestData->display_id = self::DISPLAY_ID;
        $stubbedRequestData->amount = 45600;

        $this->currentMock->method('getRequestData')->willReturn($stubbedRequestData);

        $this->boltHelperMock
            ->expects($this->once())
            ->method('fetchTransaction')->willReturn($stubbedRequestData);
        ///////////////////////////////////////////////////////////////////////

        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')->willReturn($this->orderMock);
        $this->orderMock->method('isObjectNew')->willReturn(false);

        $this->boltPaymentMethodInstanceMock->method('setStore')->willReturnSelf();
        $this->boltPaymentMethodInstanceMock->expects($this->once())->method('handleTransactionUpdate')->with(
            $this->paymentMock,
            'credit',
            null,
            $stubbedRequestData->amount / 100,
            $stubbedRequestData
        );

        $this->currentMock
            ->expects($this->exactly(2))
            ->method('sendResponse')
            ->withConsecutive(
                array(
                    200,
                    array(
                        'status'     => 'success',
                        'display_id' => self::ORDER_INCREMENT_ID,
                        'message'    => $this->currentMock->boltHelper()->__(
                            'Updated existing order %d',
                            self::ORDER_INCREMENT_ID
                        )
                    ),
                    true
                ),
                array(
                    422,
                    array(
                        'status' => 'failure',
                        'error'  => array('code' => 6009, 'message' => 'Expected exception, simulate exit')
                    )
                )
            )
            ->willThrowException(new Exception('Expected exception, simulate exit'));

        ######################################
        # Calling the subject method
        ######################################
        try {
            $this->currentMock->hookAction();
        } catch (Exception $e) {
            if ( $e->getMessage() !== "Expected exception, simulate exit" ) { throw $e; }
        }
        ######################################
    }

    /**
     * @test
     * Verifies that if the order has not been previously created at hook time, then the hook will initiate
     * order creation
     *
     * @covers ::hookAction
     */
    public function hookAction_ifOrderIsNotFound_createsNewOrder()
    {
        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'pending';
        $stubbedRequestData->display_id = self::DISPLAY_ID;

        $dummyTransaction = clone $stubbedRequestData;
        $dummyTransaction->order->cart->display_id = self::DISPLAY_ID;

        $this->currentMock->method('getRequestData')->willReturn($stubbedRequestData);

        $this->boltHelperMock
            ->expects($this->once())
            ->method('fetchTransaction')->willReturn($dummyTransaction);
        ///////////////////////////////////////////////////////////////////////

        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')->willReturn($this->orderMock);
        $this->orderMock->method('isObjectNew')->willReturn(true);

        $this->boltOrderModelMock->expects($this->once())->method('createOrder')->with(
            self::REFERENCE,
            null,
            false,
            $dummyTransaction
        );
        $this->currentMock
            ->expects($this->exactly(1))
            ->method('sendResponse')
            ->with(
                422,
                array(
                    'status' => 'failure',
                    'error' => array(
                        'code' => 6009,
                        'message' => 'Could not find order '.self::DISPLAY_ID.' Created it instead.'
                    )
                )
            )
        ;

        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->hookAction();
        ######################################
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_ApiController::hookAction}
     *
     * @param string $hookType dummy transaction type
     * @param string $prevTransactionStatus stored inside payment additional information
     *
     * @return MockObject[]|Mage_Sales_Model_Order[]|Bolt_Boltpay_Model_Payment[]
     *
     * @throws ReflectionException if unable to set payload to current mock
     */
    private function hookActionSetUp($hookType, $prevTransactionStatus = null)
    {
        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $dummyTransaction = new stdClass();
        $dummyTransaction->reference = self::REFERENCE;
        $dummyTransaction->id = self::TRANSACTION_ID;
        $dummyTransaction->type = $hookType;
        $dummyTransaction->display_id = self::DISPLAY_ID;
        $dummyTransaction->capture->amount->amount = '1000';
        $dummyTransaction->order->cart->display_id = self::DISPLAY_ID;

        $payload = json_encode($dummyTransaction);
        TestHelper::setNonPublicProperty($this->currentMock, 'payload', $payload);

        $orderMock = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(array('isObjectNew', 'getPayment', 'getIncrementId'))->getMock();
        $paymentMock = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')
            ->setMethods(
                array(
                    'getAdditionalInformation',
                    'setTransactionId',
                    'getMethodInstance',
                    'getMethod',
                    'setAdditionalInformation',
                    'save',
                    'setData'
                )
            )
            ->getMock();
        $orderMock->method('isObjectNew')->willReturn(false);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->method('getIncrementId')->willReturn(self::ORDER_INCREMENT_ID);
        $methodInstance = $this->getClassPrototype('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('setStore', 'handleTransactionUpdate'))->getMock();
        $paymentMock->method('getMethodInstance')->willReturn($methodInstance);
        $paymentMock->method('getMethod')->willReturn(Bolt_Boltpay_Model_Payment::METHOD_CODE);
        $paymentMock->method('getAdditionalInformation')->willReturnMap(
            array(
                array('bolt_transaction_status', $prevTransactionStatus)
            )
        );

        $this->currentMock->method('getRequestData')->willReturn($dummyTransaction);

        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with(self::REFERENCE)
            ->willReturn($dummyTransaction);
        $this->boltHelperMock->expects($this->once())->method('getImmutableQuoteIdFromTransaction')
            ->with($dummyTransaction)->willReturn(self::$_dummyOrder->getQuoteId());
        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')
            ->with(self::$_dummyOrder->getQuoteId())->willReturn($orderMock);
        $this->boltOrderModelMock->expects($this->once())->method('receiveOrder')
            ->with($orderMock, $payload);

        return array($orderMock, $paymentMock, $methodInstance);
    }

    /**
     * @test
     * that if order cannot be found by increment id, it's loaded based on immutable quote id
     *
     * @covers ::hookAction
     *
     * @throws ReflectionException from setup if unable to set payload
     */
    public function hookAction_ifOrderIsNotFoundByIncrementId_retrievesOrderByQuoteId()
    {
        list($orderMock, $paymentMock, $methodInstance) = $this->hookActionSetUp(
            Bolt_Boltpay_Model_Payment::HOOK_TYPE_AUTH
        );

        $paymentMock->expects($this->exactly(2))->method('setAdditionalInformation')
            ->with('bolt_merchant_transaction_id', self::TRANSACTION_ID)->willReturnSelf();
        $paymentMock->expects($this->once())->method('setTransactionId')
            ->with(self::TRANSACTION_ID)->willReturnSelf();

        $methodInstance->method('setStore')->willReturnSelf();
        $methodInstance->method('handleTransactionUpdate')->with(
            $paymentMock,
            'authorized',
            null,
            10,
            $this->anything()
        );

        $this->currentMock
            ->expects($this->exactly(2))
            ->method('sendResponse')
            ->withConsecutive(
                array(
                    200,
                    array(
                        'status'     => 'success',
                        'display_id' => self::ORDER_INCREMENT_ID,
                        'message'    => $this->currentMock->boltHelper()->__(
                            'Updated existing order %d',
                            self::ORDER_INCREMENT_ID
                        )
                    )
                ),
                array(
                    422,
                    array(
                        'status' => 'failure',
                        'error'  => array('code' => 6009, 'message' => 'Expected exception, simulate exit')
                    )
                )
            )
            ->willThrowException(new Exception('Expected exception, simulate exit'));

        ######################################
        # Calling the subject method
        ######################################
        try {
            $this->currentMock->hookAction();
        } catch (Exception $e) {
            if ( $e->getMessage() !== "Expected exception, simulate exit" ) { throw $e; }
        }
        ######################################
    }

    /**
     * @test
     * that if {@see Bolt_Boltpay_InvalidTransitionException} is thrown
     * from {@see \Bolt_Boltpay_Model_Payment::handleTransactionUpdate} and old status is on-hold
     * Retry-After header is added to response with value of 1 day (in seconds) to delay further webhooks
     * because manual action is required
     *
     * @covers ::hookAction
     *
     * @throws ReflectionException from setup if unable to set payload
     */
    public function hookAction_ifInvalidTransitionFromOnHold_setsRetryAfterHeader()
    {
        list($orderMock, $paymentMock, $methodInstance) = $this->hookActionSetUp(
            Bolt_Boltpay_Model_Payment::HOOK_TYPE_AUTH
        );

        $paymentMock->expects($this->exactly(2))->method('setAdditionalInformation')
            ->with('bolt_merchant_transaction_id', self::TRANSACTION_ID)->willReturnSelf();
        $paymentMock->expects($this->once())->method('setTransactionId')
            ->with(self::TRANSACTION_ID)->willReturnSelf();


        $methodInstance->method('setStore')->willReturnSelf();
        $methodInstance->method('handleTransactionUpdate')
            ->willThrowException(
                new Bolt_Boltpay_InvalidTransitionException(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD,
                    ''
                )
            );

        $message = $this->boltHelperMock->__(
            'The order is on-hold and requires manual merchant update before this hook can be processed'
        );

        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);

        $this->responseMock->expects($this->once())->method('setHeader')->with("Retry-After", "86400");

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                503,
                array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $message))
            );


        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->hookAction();
        ######################################
    }

    /**
     * @test
     * that if {@see Bolt_Boltpay_InvalidTransitionException} is thrown
     * from {@see Bolt_Boltpay_Model_Payment::handleTransactionUpdate} and old status is same as hook status
     * Returns success response, essentially ignoring the hook as it was already handled
     *
     * @covers ::hookAction
     *
     * @throws ReflectionException from setup if unable to set payload
     */
    public function hookAction_whenHookIsRepeated_returnsSuccessResponse()
    {
        list($orderMock, $paymentMock, $methodInstance) = $this->hookActionSetUp(
            Bolt_Boltpay_Model_Payment::HOOK_TYPE_AUTH,
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );

        $paymentMock->expects($this->exactly(2))->method('setAdditionalInformation')
            ->with('bolt_merchant_transaction_id', self::TRANSACTION_ID)->willReturnSelf();
        $paymentMock->expects($this->once())->method('setTransactionId')
            ->with(self::TRANSACTION_ID)->willReturnSelf();


        $methodInstance->method('setStore')->willReturnSelf();
        $methodInstance->method('handleTransactionUpdate')
            ->willThrowException(
                new Bolt_Boltpay_InvalidTransitionException(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
                )
            );

        $message = $this->boltHelperMock->__(
            'Order already handled, so hook was ignored'
        );

        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'     => 'success',
                    'display_id' => self::ORDER_INCREMENT_ID,
                    'message'    => $message
                )
            );


        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->hookAction();
        ######################################
    }

    /**
     * @test
     * that if {@see Bolt_Boltpay_InvalidTransitionException} is thrown
     * from {@see Bolt_Boltpay_Model_Payment::handleTransactionUpdate} and old status is same as hook status
     * Returns success response, essentially ignoring the hook as it was already handled
     *
     * @covers ::hookAction
     *
     * @throws ReflectionException from setup if unable to set payload
     */
    public function hookAction_withInvalidTransitionAndCanNotAssumeHookIsHandledBecauseHookIsCapture_returnsFailResponse()
    {
        list($orderMock, $paymentMock, $methodInstance) = $this->hookActionSetUp(
            Bolt_Boltpay_Model_Payment::HOOK_TYPE_CAPTURE,
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );

        $paymentMock->expects($this->exactly(2))->method('setAdditionalInformation')
            ->with('bolt_merchant_transaction_id', self::TRANSACTION_ID)->willReturnSelf();
        $paymentMock->expects($this->once())->method('setTransactionId')
            ->with(self::TRANSACTION_ID)->willReturnSelf();


        $methodInstance->method('setStore')->willReturnSelf();
        $methodInstance->method('handleTransactionUpdate')
            ->willThrowException(
                new Bolt_Boltpay_InvalidTransitionException(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
                )
            );

        $message = $this->boltHelperMock->__(
            'Invalid webhook transition from %s to %s',
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                422,
                array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $message))
            );


        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->hookAction();
        ######################################
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_ApiController::create_orderAction}
     *
     * @param string   $displayId to be set to transaction
     * @param int|null $immutableQuoteId to be returned from
     * @return stdClass dummy transaction object
     */
    private function create_orderActionSetUp($displayId, $immutableQuoteId = null)
    {
        $transaction = new stdClass();
        $transaction->order->cart->display_id = $displayId;
        $this->currentMock->method('getRequestData')->willReturn($transaction);
        $this->boltHelperMock->method('getImmutableQuoteIdFromTransaction')->with($transaction)
            ->willReturn($immutableQuoteId);
        return $transaction;
    }

    /**
     * @test
     * that create order action returns success response using old orders data
     * if an order related to immutable quote already exists without creating a new one
     *
     * @covers ::create_orderAction
     *
     * @throws Mage_Core_Model_Store_Exception from method tested if there is a problem locating a reference to the underlying store
     * @throws ReflectionException if createSuccessUrl method doesn't exist
     * @throws Zend_Controller_Response_Exception from method tested if there is an error in sending a response back to the caller
     */
    public function create_orderAction_withExistingOrder_returnsSuccessResponseWithOrderSuccessUrl()
    {
        $this->create_orderActionSetUp(self::DISPLAY_ID, self::$_dummyOrder->getQuoteId());
        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')
            ->with(self::$_dummyOrder->getQuoteId())
            ->willReturn(self::$_dummyOrder);
        $orderSuccessUrl = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'createSuccessUrl',
            array(self::$_dummyOrder, self::$_dummyOrder->getQuoteId())
        );
        $this->boltHelperMock->expects($this->once())->method('doFilterEvent')->with(
            'bolt_boltpay_filter_success_url',
            $orderSuccessUrl,
            array(
                'order'    => self::$_dummyOrder,
                'quote_id' => self::$_dummyOrder->getQuoteId()
            )
        )->willReturn($orderSuccessUrl);
        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'             => 'success',
                    'display_id'         => self::$_dummyOrder->getIncrementId(),
                    'total'              => (int)(self::$_dummyOrder->getGrandTotal() * 100),
                    'order_received_url' => $orderSuccessUrl
                ),
                false
            );
        $this->boltOrderModelMock->expects($this->never())->method('createOrder');

        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->create_orderAction();
        ######################################
    }

    /**
     * @test
     * that create order action returns error response if an order related to immutable quote already exists
     * and its status is canceled_bolt
     *
     * @covers ::create_orderAction
     *
     * @throws Mage_Core_Model_Store_Exception from method tested if there is a problem locating a reference to the underlying store
     * @throws Zend_Controller_Response_Exception from method tested if there is an error in sending a response back to the caller
     */
    public function create_orderAction_withExistingOrderCanceledFromBolt_returnsErrorResponse()
    {
        $this->create_orderActionSetUp(self::DISPLAY_ID, self::IMMUTABLE_QUOTE_ID);
        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')
            ->with(self::IMMUTABLE_QUOTE_ID)
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->orderMock->expects($this->once())->method('getStatus')->willReturn('canceled_bolt');

        $orderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED
        );

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                $orderCreationException->getHttpCode(),
                $orderCreationException->getJson(),
                false
            );

        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->create_orderAction();
        ######################################
    }

    /**
     * @test
     * that create_orderAction successfully loads orders by increment id for legacy transactions
     *
     * @covers ::create_orderAction
     *
     * @throws Mage_Core_Model_Store_Exception from method tested if there is a problem locating a reference to the underlying store
     * @throws ReflectionException if createSuccessUrl method doesn't exist
     * @throws Zend_Controller_Response_Exception from method tested if there is an error in sending a response back to the caller
     */
    public function create_orderAction_whenDisplayIdIsWithoutPipe_loadsOrderByIncrementId()
    {
        $this->create_orderActionSetUp(self::$_dummyOrder->getIncrementId(), self::$_dummyOrder->getQuoteId());
        $orderSuccessUrl = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'createSuccessUrl',
            array(self::$_dummyOrder, self::$_dummyOrder->getQuoteId())
        );
        $dummyOrderFromDb = Mage::getModel('sales/order')->loadByIncrementId(
            self::$_dummyOrder->getIncrementId()
        );
        //populate _idFieldName
        $dummyOrderFromDb->getIdFieldName();

        $this->boltHelperMock->expects($this->once())->method('doFilterEvent')->with(
            'bolt_boltpay_filter_success_url',
            $orderSuccessUrl,
            array(
                'order'    => $dummyOrderFromDb,
                'quote_id' => self::$_dummyOrder->getQuoteId()
            )
        )->willReturn($orderSuccessUrl);
        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'             => 'success',
                    'display_id'         => self::$_dummyOrder->getIncrementId(),
                    'total'              => (int)(self::$_dummyOrder->getGrandTotal() * 100),
                    'order_received_url' => $orderSuccessUrl
                ),
                false
            );

        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->create_orderAction();
        ######################################
    }

    /**
     * @test
     * that create_orderAction attempts to create new order if it doesn't exist already by using
     * @see Bolt_Boltpay_Model_Order::createOrder
     *
     * @covers ::create_orderAction
     *
     * @throws Mage_Core_Model_Store_Exception from method tested if there is a problem locating a reference to the underlying store
     * @throws ReflectionException if createSuccessUrl method doesn't exist
     * @throws Zend_Controller_Response_Exception from method tested if there is an error in sending a response back to the caller
     */
    public function create_orderAction__whenOrderDoesNotExistAlready_createsNewOrder()
    {
        $transaction = $this->create_orderActionSetUp(self::DISPLAY_ID, self::$_dummyOrder->getQuoteId());
        $this->boltOrderModelMock->expects($this->once())->method('getOrderByQuoteId')
            ->with(self::$_dummyOrder->getQuoteId())
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())->method('isObjectNew')->willReturn(true);

        $this->boltOrderModelMock->expects($this->once())->method('createOrder')
            ->with(null, null, true, $transaction)->willReturn(self::$_dummyOrder);

        $orderSuccessUrl = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'createSuccessUrl',
            array(self::$_dummyOrder, self::$_dummyOrder->getQuoteId())
        );
        $this->boltHelperMock->expects($this->once())->method('doFilterEvent')->with(
            'bolt_boltpay_filter_success_url',
            $orderSuccessUrl,
            array(
                'order'    => self::$_dummyOrder,
                'quote_id' => self::$_dummyOrder->getQuoteId()
            )
        )->willReturn($orderSuccessUrl);

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'             => 'success',
                    'display_id'         => self::$_dummyOrder->getIncrementId(),
                    'total'              => (int)(self::$_dummyOrder->getGrandTotal() * 100),
                    'order_received_url' => $orderSuccessUrl
                ),
                false
            );

        ######################################
        # Calling the subject method
        ######################################
        $this->currentMock->create_orderAction();
        ######################################
    }

    /**
     * @test
     * that createSuccessUrl successfully creates success url using default configuration value for the success route
     *
     * @covers ::createSuccessUrl
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub configuration value
     * @throws ReflectionException if createSuccessUrl method doesn't exist
     * @throws Exception if test class name is not defined
     */
    public function createSuccessUrl_withDefaultConfiguration_createsSuccessUrl()
    {
        TestHelper::stubConfigValue('payment/boltpay/successpage', 'checkout/onepage/success');
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper'))->getMock();
        /** @var MockObject|Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = $this->getClassPrototype('Bolt_Boltpay_Helper_Data', false)
            ->enableProxyingToOriginalMethods()
            ->setMethods(array('getMagentoUrl'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($boltHelper);
        $boltHelper->expects($this->exactly(2))->method('getMagentoUrl')->with(
            'checkout/onepage/success',
            array(
                '_query' => array(
                    'lastQuoteId'        => self::$_dummyOrder->getQuoteId(),
                    'lastSuccessQuoteId' => self::$_dummyOrder->getQuoteId(),
                    'lastOrderId'        => self::$_dummyOrder->getId(),
                    'lastRealOrderId'    => self::$_dummyOrder->getIncrementId()
                )
            )
        );
        $this->assertEquals(
            $boltHelper->getMagentoUrl(
                'checkout/onepage/success',
                array(
                    '_query' => array(
                        'lastQuoteId'        => self::$_dummyOrder->getQuoteId(),
                        'lastSuccessQuoteId' => self::$_dummyOrder->getQuoteId(),
                        'lastOrderId'        => self::$_dummyOrder->getId(),
                        'lastRealOrderId'    => self::$_dummyOrder->getIncrementId()
                    )
                )
            ),
            TestHelper::callNonPublicFunction(
                $currentMock,
                'createSuccessUrl',
                array(self::$_dummyOrder, self::$_dummyOrder->getQuoteId())
            )
        );
    }

    /**
     * @test
     * that handleFailedPaymentHook returns success response when order payment already contains bolt_reference
     *
     * @covers ::handleFailedPaymentHook
     *
     * @throws ReflectionException if createSuccessUrl method doesn't exist
     * @throws Exception if test class name is not defined
     */
    public function handleFailedPaymentHook_ifPaymentIsAlreadyRecorded_returnsSuccessResponse()
    {
        TestHelper::stubModel('sales/order', $this->orderMock);
        $this->orderMock->expects($this->once())->method('load')->with(self::ORDER_INCREMENT_ID, 'increment_id')
            ->willReturnSelf();
        $this->orderMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->paymentMock->expects($this->once())->method('getAdditionalInformation')->with('bolt_reference')
            ->willReturn(true);

        $message = $this->boltHelper()->__(
            'Payment was already recorded. The failed payment hook for order %s seems out of sync.',
            $this->orderMock->getIncrementId()
        );
        $this->boltHelperMock->expects($this->once())->method('logWarning')->with($message);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with(
            new Exception($message),
            array(),
            'warning'
        );

        $this->currentMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                array(
                    'status'  => 'success',
                    'message' => $message
                ),
                $exitImmediately = true
            )
            ->willThrowException(new Exception('Expected exception, simulate exit'));

        ######################################
        # Calling the subject method
        ######################################
        try {
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'handleFailedPaymentHook',
                array(self::ORDER_INCREMENT_ID)
            );
        } catch (Exception $e) {
            if ( $e->getMessage() !== "Expected exception, simulate exit" ) { throw $e; }
        }
        ######################################
    }

    /**
     * @test
     * that handleFailedPaymentHook removes pre-auth order if order is still pending authorization
     *
     * @covers ::handleFailedPaymentHook
     *
     * @throws ReflectionException if unable to stub model or handleFailedPaymentHook method is not defined
     */
    public function handleFailedPaymentHook_ifOrderIsPendingAuthorization_removesPreAuthOrder()
    {
        TestHelper::stubModel('sales/order', $this->orderMock);
        $this->orderMock->expects($this->once())->method('load')->with(self::ORDER_INCREMENT_ID, 'increment_id')
            ->willReturnSelf();
        $this->orderMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->paymentMock->expects($this->once())->method('getAdditionalInformation')->with('bolt_reference')
            ->willReturn(null);
        $this->paymentMock->expects($this->once())->method('getAuthorizationTransaction')->willReturn(null);
        $this->paymentMock->expects($this->once())->method('getLastTransId')->willReturn(null);

        $this->boltOrderModelMock->expects($this->once())->method('removePreAuthOrder')->with($this->orderMock);

        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                200,
                array(
                    'status'  => 'success',
                    'message' => 'Order ' . self::ORDER_INCREMENT_ID . ' has been canceled prior to authorization'
                )
            );
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'handleFailedPaymentHook',
            array(self::ORDER_INCREMENT_ID)
        );
    }

    /**
     * @test
     * that getCaptureAmount returns capture amount from provided transaction divided by 100
     *
     * @covers ::getCaptureAmount
     *
     * @throws ReflectionException if getCaptureAmount method doesn't exist
     */
    public function getCaptureAmount_ifAmountIsSetAndIsNumeric_returnsAmountDividedBy100()
    {
        $transaction = new stdClass();
        $transaction->capture->amount->amount = 12300;
        $this->assertSame(
            123,
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getCaptureAmount',
                array($transaction)
            )
        );
    }

    /**
     * @test
     * that getCaptureAmount returns null if value supplied for capture amount is not numeric
     *
     * @covers ::getCaptureAmount
     *
     * @throws ReflectionException if getCaptureAmount method doesn't exist
     */
    public function getCaptureAmount_ifAmountIsSetButIsNotNumeric_returnsNull()
    {
        $transaction = new stdClass();
        $transaction->capture->amount->amount = 'not numeric';
        $this->assertNull(
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getCaptureAmount',
                array($transaction)
            )
        );
    }

    /**
     * @test
     * that getCaptureAmount returns null if capture amount is not set
     *
     * @covers ::getCaptureAmount
     *
     * @throws ReflectionException if getCaptureAmount method doesn't exist
     */
    public function getCaptureAmount_ifAmountIsNotSet_returnsNull()
    {
        $transaction = new stdClass();
        $this->assertNull(
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getCaptureAmount',
                array($transaction)
            )
        );
    }

    /**
     * @test
     * that handleDiscountHook applies coupon via {@see Bolt_Boltpay_Model_Coupon} and returns response
     *
     * @covers ::handleDiscountHook
     *
     * @throws ReflectionException if unable to stub model
     */
    public function handleDiscountHook_always_appliesCouponThroughBoltpayCouponModelAndSendsResponse()
    {
        $httpCode = 200;
        $responseData = array('status' => 'success');

        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = self::REFERENCE;
        $stubbedRequestData->id = self::TRANSACTION_ID;
        $stubbedRequestData->type = 'discounts.code.apply';
        $stubbedRequestData->display_id = self::DISPLAY_ID;

        $couponModelMock = $this->getClassPrototype('Bolt_Boltpay_Model_Coupon')->getMock();
        $couponModelMock->expects($this->once())->method('applyCoupon');
        $couponModelMock->expects($this->once())->method('getHttpCode')->willReturn($httpCode);
        $couponModelMock->expects($this->once())->method('getResponseData')->willReturn($responseData);
        $couponModelMock->expects($this->once())->method('setupVariables')->with($stubbedRequestData);

        TestHelper::stubModel('boltpay/coupon', $couponModelMock);

        TestHelper::setNonPublicProperty($this->currentMock, 'payload', json_encode($stubbedRequestData));

        $this->currentMock->expects($this->once())->method('sendResponse')->with($httpCode, $responseData);

        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'handleDiscountHook',
            array(self::ORDER_INCREMENT_ID)
        );
    }
}