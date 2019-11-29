<?php

require_once 'Bolt/Boltpay/controllers/ApiController.php';
require_once 'TestHelper.php';
require_once 'OrderHelper.php';
require_once 'ProductProvider.php';

/**
 * Class Bolt_Boltpay_ApiControllerTest
 *
 * Unit and Integration test for the Bolt_Boltpay_ApiController
 */
class Bolt_Boltpay_ApiControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockBuilder The builder for a generically mocked API controller
     *      that is the subject of these test
     */
    private $_apiControllerBuilder;

    /**
     * @var Bolt_Boltpay_TestHelper  Used for accessing private and protected data members and interfaces
     */
    private static $_testHelper;

    /**
     * @var Mage_Sales_Model_Order  Disposable order used within each test
     */
    private static $_mockOrder;

    /**
     * @var int ID of the dummy product.  This is primarily used to create orders and for DB cleanup
     */
    private static $_dummyProductId;

    /**
     * Generates common objects used in all test
     */
    public static function setUpBeforeClass()
    {
        self::$_testHelper = new Bolt_Boltpay_TestHelper();
        self::$_dummyProductId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            'api_controller_test_product', [], 50
        );
    }

    /**
     * Sets up a new mock builder for a generically mocked Bolt_Boltpay_ApiController between each test method.  Use
     * {@see Bolt_Boltpay_ApiControllerTest::$_apiControllerBuilder}'s setMethods method from within the test method
     * for further refinement of stubbed behavior
     *
     * @throws Zend_Controller_Request_Exception    on unexpected problem in creating the controller
     * @throws Mage_Core_Exception                  on failure to create a dummy order
     */
    public function setUp()
    {
        $this->_apiControllerBuilder = $this->getMockBuilder( "Bolt_Boltpay_ApiController")
            ->setConstructorArgs( array( new Mage_Core_Controller_Request_Http(), new Mage_Core_Controller_Response_Http()) )
            ->setMethods(null);

        self::$_mockOrder = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$_dummyProductId, 2, 'boltpay');
    }

    /**
     * Restores resources in between each test method
     */
    public function tearDown()
    {
        Bolt_Boltpay_OrderHelper::deleteDummyOrder(self::$_mockOrder);
    }

    /**
     * Restores resources after all test have completed
     */
    public static function tearDownAfterClass()
    {
       Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$_dummyProductId);
    }

    /**
     * Verifies that irreversibly rejected hooks do not trigger the "receiving order" behavior which means order
     * finalization including sending out order notification emails, associating the order with a transaction and
     * triggering post order creation events.
     *
     * @throws ReflectionException      on unexpected problems with reflection
     */
    public function testHookAction_thatOrderNotProcessedForIrreversiblyRejectedHooks() {

        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'boltHelper'])
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = 'TEST-BOLT-TRNX';
        $stubbedRequestData->id = 'TRboltx0test1';
        $stubbedRequestData->type = 'rejected_irreversible';
        $stubbedRequestData->display_id = self::$_mockOrder->getIncrementId();

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);

        /** @var Bolt_Boltpay_Helper_Data|PHPUnit_Framework_MockObject_MockObject $stubbedBoltHelper */
        $stubbedBoltHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('fetchTransaction', 'logInfo'))
            ->getMock();

        $stubbedBoltHelper
            ->expects($this->once())
            ->method('fetchTransaction')->willReturn($stubbedRequestData);

        $apiControllerMock->method('boltHelper')->willReturn($stubbedBoltHelper);
        ///////////////////////////////////////////////////////////////////////

        $payment = self::$_mockOrder->getPayment();

        $this->assertFalse(
            $payment->getAdditionalInformation('bolt_reference')
            || $payment->getAuthorizationTransaction()
            || $payment->getLastTransId()
        );

        $apiControllerMock
            ->expects($this->once())
            ->method('sendResponse')
            ->with($this->equalTo(200));

        $this->assertFalse(self::$_mockOrder->isCanceled());

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################

        $this->assertFalse(
            $payment->getAdditionalInformation('bolt_reference')
            || $payment->getAuthorizationTransaction()
            || $payment->getLastTransId()
        );
        $this->assertTrue(self::$_mockOrder->isCanceled());
    }

    /**
     *  Test makes sure that the non-piped format of display_id is supported for failed_payment hooks
     */
    public function testHookAction_thatStandardDisplayIdIsSupportedForFailedPayment() {

        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'handleFailedPaymentHook'])
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = 'TEST-BOLT-TRNX';
        $stubbedRequestData->id = 'TRboltx0test1';
        $stubbedRequestData->type = 'failed_payment';
        $stubbedRequestData->display_id = '9876543210';

        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'handleFailedPaymentHook'])
            ->getMock();

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock
            ->expects($this->once())
            ->method('handleFailedPaymentHook')
            ->with($this->equalTo('9876543210'));

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################
    }

    /**
     *  Test makes sure that the piped format of display_id is supported for failed_payment hooks
     */
    public function testHookAction_thatPipedDisplayIdIsSupportedForFailedPayment() {

        /** @var Bolt_Boltpay_ApiController|PHPUnit_Framework_MockObject_MockObject $apiControllerMock */
        $apiControllerMock = $this->_apiControllerBuilder
            ->setMethods(['getRequestData', 'handleFailedPaymentHook'])
            ->getMock();

        ///////////////////////////////////////////////////////////////////////
        /// Create a pseudo transaction data and map to request and responses
        ///////////////////////////////////////////////////////////////////////
        $stubbedRequestData = new stdClass();
        $stubbedRequestData->reference = 'TEST-BOLT-TRNX';
        $stubbedRequestData->id = 'TRboltx0test1';
        $stubbedRequestData->type = 'failed_payment';
        $stubbedRequestData->display_id = '1234567890|44444';

        $apiControllerMock->method('getRequestData')->willReturn($stubbedRequestData);
        $apiControllerMock
            ->expects($this->once())
            ->method('handleFailedPaymentHook')
            ->with($this->equalTo('1234567890'));

        ######################################
        # Calling the subject method
        ######################################
        $apiControllerMock->hookAction();
        ######################################
    }
}