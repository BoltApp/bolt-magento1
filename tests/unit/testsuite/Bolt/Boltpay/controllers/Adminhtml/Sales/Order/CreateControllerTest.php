<?php

require_once 'Bolt/Boltpay/controllers/Adminhtml/Sales/Order/CreateController.php';

require_once('MockingTrait.php');
require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Adminhtml_Sales_Order_CreateController
 */
class Bolt_Boltpay_Adminhtml_Sales_Order_CreateControllerTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Dummy Bolt order reference */
    const BOLT_REFERENCE = 'AAAA-BBBB-1234';

    /** @var int Dummy immutable quote id */
    const IMMUTABLE_QUOTE_ID = 456;

    /** @var int Dummy order increment id */
    const ORDER_INCREMENT_ID = 123;

    /** @var int Dummy order entity id */
    const ORDER_ID = 122;

    /** @var int Dummy parent quote id */
    const QUOTE_ID = 455;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Adminhtml_Sales_Order_CreateController';

    /** @var MockObject|Bolt_Boltpay_Adminhtml_Sales_Order_CreateController Mocked instance of the class tested */
    private $currentMock;

    /** @var MockObject|Mage_Admin_Model_Session Mocked instance of admin session */
    private $adminSessionMock;

    /** @var MockObject|Mage_Core_Controller_Request_Http Mocked instance of the request class */
    private $requestMock;

    /** @var MockObject|Mage_Adminhtml_Model_Sales_Order_Create Mocked instance of order create singleton */
    private $salesOrderCreateMock;

    /** @var MockObject|Mage_Adminhtml_Model_Session_Quote Mocked instance of admin session quote */
    private $adminSessionQuoteMock;

    /** @var MockObject|Mage_Core_Model_Session Mocked instance of core session */
    private $coreSessionMock;

    /** @var MockObject|Mage_Sales_Model_Quote_Payment Mocked instance of quote payment class */
    private $quotePaymentMock;

    /** @var MockObject|Bolt_boltpay_helper_Data Mocked instance of Bolt helper */
    private $boltHelperMock;

    /** @var array Dummy order data */
    private $orderData = array('increment_id' => self::ORDER_INCREMENT_ID);

    /** @var array Dummy payment data */
    private $paymentData = array('reference' => self::BOLT_REFERENCE);

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Exception if unable to stub one of the singletons or Bolt helper
     * @throws Exception if test class name is not set
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'getRequest',
                    'getLayout',
                    'getResponse',
                    '_redirect',
                    '_normalizeOrderData',
                    '_processActionData',
                    'getImmutableQuoteIdFromTransaction',
                    '_postOrderCreateProcessing',
                )
            )
            ->enableOriginalConstructor()
            ->getMock();

        $this->adminSessionMock = $this->getClassPrototype('Mage_Admin_Model_Session')
            ->setMethods(array('setOrderShippingAddress', 'addSuccess', 'isAllowed'))->getMock();
        TestHelper::stubSingleton('admin/session', $this->adminSessionMock);
        TestHelper::stubSingleton('adminhtml/session', $this->adminSessionMock);

        $this->salesOrderCreateMock = $this->getClassPrototype('Mage_Adminhtml_Model_Sales_Order_Create')
            ->getMock();

        $this->requestMock = $this->getClassPrototype('Mage_Core_Controller_Request_Http')->getMock();

        $this->adminSessionQuoteMock = $this->getClassPrototype('Mage_Adminhtml_Model_Session_Quote')
            ->setMethods(array('setQuoteId', 'getPayment', 'clear', 'addError', 'addException', 'getQuote'))->getMock();
        TestHelper::stubSingleton('adminhtml/session_quote', $this->adminSessionQuoteMock);

        $this->coreSessionMock = $this->getClassPrototype('Mage_Core_Model_Session')
            ->setMethods(array('setBoltReference', 'setWasCreatedByHook'))->getMock();
        TestHelper::stubSingleton('core/session', $this->coreSessionMock);

        $this->quotePaymentMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Payment')->getMock();

        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_helper_Data')->getMock();
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);

        $layout = new Mage_Core_Model_Layout();
        $layout->createBlock('Mage_Core_Block_Template', 'content');

        $response = new Mage_Core_Controller_Response_Http();

        $this->currentMock->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->method('getLayout')->willReturn($layout);
        $this->currentMock->method('getResponse')->willReturn($response);
    }

    /**
     * Restore originals for stubbed values
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
     * that loadBlockAction takes address data from request, prepares it and adds it to admin session
     *
     * @covers ::loadBlockAction
     */
    public function loadBlockAction_withValidRequest_preparesAddressDataAndAddsItToSession()
    {
        $expected = array(
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
            'email_address'   => 'test@bolt.com',
            'email'           => 'test@bolt.com'
        );

        $postData = array(
            'shipping_address' => array(
                'street'     => array(
                    'Test street 123',
                    'Additional street 007',
                ),
                'firstname'  => 'TesterFn',
                'lastname'   => 'TesterLn',
                'city'       => 'Los Angeles',
                'region_id'  => 12, // Region: CA
                'postcode'   => '10011',
                'country_id' => 'US',
                'telephone'  => '+1 123 111 3333',
            ),
            'account'          => array(
                'email' => 'test@bolt.com'
            )
        );

        $this->requestMock->expects($this->atLeastOnce())->method('getPost')
            ->willReturnMap(array(array('order', null, $postData)));

        $this->adminSessionMock->expects($this->once())->method('setOrderShippingAddress')->with($expected);

        $this->currentMock->loadBlockAction();
    }

    /**
     * @test
     * that prepareAddressData converts order address from post data into Bolt address format
     *
     * @covers ::prepareAddressData
     */
    public function prepareAddressData_always_convertsAddressDataIntoBoltFormat()
    {
        $expected = array(
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
            'email_address'   => 'test@bolt.com',
            'email'           => 'test@bolt.com'
        );

        $postData = array(
            'shipping_address' => array(
                'street'     => array(
                    'Test street 123',
                    'Additional street 007',
                ),
                'firstname'  => 'TesterFn',
                'lastname'   => 'TesterLn',
                'city'       => 'Los Angeles',
                'region_id'  => 12, // Region: CA
                'postcode'   => '10011',
                'country_id' => 'US',
                'telephone'  => '+1 123 111 3333',
            ),
            'account'          => array(
                'email' => 'test@bolt.com'
            )
        );
        $this->assertEquals($expected, $this->currentMock->prepareAddressData($postData));
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Adminhtml_Sales_Order_CreateController::saveAction}
     */
    private function saveActionSetUp()
    {
        $orderMock = Mage::getModel(
            'sales/order',
            array('entity_id' => self::ORDER_ID, 'increment_id' => self::ORDER_INCREMENT_ID)
        );

        $this->requestMock->method('getPost')->willReturnMap(
            array(
                array('bolt_reference', null, self::BOLT_REFERENCE),
                array('payment', null, $this->paymentData),
                array('order', null, $this->orderData)
            )
        );

        $this->currentMock->expects($this->once())->method('getImmutableQuoteIdFromTransaction')
            ->with(self::BOLT_REFERENCE)->willReturn(self::IMMUTABLE_QUOTE_ID);

        $this->adminSessionQuoteMock->expects($this->once())->method('setQuoteId')->with(self::IMMUTABLE_QUOTE_ID);
        $this->currentMock->expects($this->once())->method('_normalizeOrderData');

        $this->coreSessionMock->expects($this->once())->method('setBoltReference')->with(self::BOLT_REFERENCE);
        $this->coreSessionMock->expects($this->once())->method('setWasCreatedByHook')->with(false);

        $this->currentMock->expects($this->once())->method('_processActionData')->with('save');

        $paymentDataWithChecks = array_merge(
            $this->paymentData,
            array(
                'checks' => Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL
            )
        );
        $this->salesOrderCreateMock->expects($this->once())->method('setPaymentData')->with($paymentDataWithChecks);

        $this->salesOrderCreateMock->expects($this->atLeastOnce())->method('getQuote')
            ->willReturn($this->adminSessionQuoteMock);
        $this->adminSessionQuoteMock->expects($this->once())->method('getPayment')->willReturn($this->quotePaymentMock);
        $this->quotePaymentMock->expects($this->once())->method('addData')->with($paymentDataWithChecks);

        $this->salesOrderCreateMock->expects($this->once())->method('setIsValidate')->with(true)->willReturnSelf();
        $this->salesOrderCreateMock->expects($this->once())->method('importPostData')->with(
            $this->orderData
        )->willReturnSelf();

        $this->salesOrderCreateMock->expects($this->once())->method('createOrder')->willReturn($orderMock);

        $this->currentMock->expects($this->once())->method('_postOrderCreateProcessing')->with($orderMock);

        $this->adminSessionQuoteMock->expects($this->once())->method('clear');

        $this->adminSessionMock->expects($this->once())->method('addSuccess')
            ->with($this->currentMock->__('The order has been created.'));
    }

    /**
     * @test
     * that saveAction doesn't proceed with creating the order if there is no bolt reference in request
     * instead it is deferred to Magento
     *
     * @covers ::saveAction
     */
    public function saveAction_withoutBoltReference_normalizesOrderDataAndReturnsFalse()
    {
        $this->currentMock->method('_processActionData')->willThrowException(new Exception('Expected exception'));
        $this->requestMock->expects($this->atLeastOnce())->method('getPost')->willReturn(null);
        $this->currentMock->expects($this->once())->method('_normalizeOrderData');
        $this->assertFalse($this->currentMock->saveAction());
    }

    /**
     * @test
     * that saveAction creates new order if supplied with valid data
     * after order is created it redirects to order detail page if it's not disabled in ACL
     *
     * @covers ::saveAction
     *
     * @throws Mage_Core_Exception from test helper if unable to stub singleton
     */
    public function saveAction_withValidDataAndOrderViewAllowed_createsOrderAndRedirectsToOrderView()
    {
        $this->saveActionSetUp();
        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);

        $this->adminSessionMock->expects($this->once())->method('isAllowed')->with('sales/order/actions/view')
            ->willReturn(true);

        $this->currentMock->expects($this->once())->method('_redirect')
            ->with('*/sales_order/view', array('order_id' => self::ORDER_ID));

        $this->assertTrue($this->currentMock->saveAction());
    }

    /**
     * @test
     * that saveAction creates new order when supplied with valid data
     * after order is created it redirects to order list page if order details page is disabled in ACL
     *
     * @covers ::saveAction
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function saveAction_withValidDataAndOrderViewNotAllowed_createsOrderAndRedirectsToOrderList()
    {
        $this->saveActionSetUp();

        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);

        $this->adminSessionMock->expects($this->once())->method('isAllowed')->with('sales/order/actions/view')
            ->willReturn(false);

        $this->currentMock->expects($this->once())->method('_redirect')
            ->with('*/sales_order/index');

        $this->assertTrue($this->currentMock->saveAction());
    }

    /**
     * @test
     * that if an {@see Mage_Payment_Model_Info_Exception} is thrown inside saveAction it's logged and added to session
     * quote is saved and user is redirected to order index page
     *
     * @covers ::saveAction
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function saveAction_whenSetPaymentDataThrowsPaymentInfoException_logsExceptionSavesQuoteAndRedirectsToPreviousPage()
    {
        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);
        $this->requestMock->method('getPost')->willReturnMap(
            array(
                array('bolt_reference', null, self::BOLT_REFERENCE),
                array('payment', null, array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE)),
            )
        );
        $exception = new Mage_Payment_Model_Info_Exception('Expected exception');

        $this->salesOrderCreateMock->expects($this->once())->method('setPaymentData')->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        $this->salesOrderCreateMock->expects($this->once())->method('saveQuote');
        $this->adminSessionQuoteMock->expects($this->once())->method('addError')->with('Expected exception');
        $this->currentMock->expects($this->once())->method('_redirect')->with('*/*/');

        $this->assertFalse($this->currentMock->saveAction());
    }

    /**
     * @test
     * that if an {@see Mage_Core_Exception} is thrown inside saveAction it's logged and added to session
     * user is redirected to order index page
     *
     * @covers ::saveAction
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function saveAction_whenSetPaymentDataThrowsMageCoreException_logsExceptionAndRedirectsToPreviousPage()
    {
        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);
        $this->requestMock->method('getPost')->willReturnMap(
            array(
                array('bolt_reference', null, self::BOLT_REFERENCE),
                array('payment', null, array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE)),
            )
        );
        $exception = new Mage_Core_Exception('Expected exception');

        $this->salesOrderCreateMock->expects($this->once())->method('setPaymentData')->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        $this->salesOrderCreateMock->expects($this->never())->method('saveQuote');
        $this->adminSessionQuoteMock->expects($this->once())->method('addError')->with('Expected exception');
        $this->currentMock->expects($this->once())->method('_redirect')->with('*/*/');

        $this->assertFalse($this->currentMock->saveAction());
    }

    /**
     * @test
     * that if an {@see Exception} is thrown inside saveAction it's logged and added to session
     * quote is saved and user is redirected to order index page
     *
     * @covers ::saveAction
     *
     * @throws Mage_Core_Exception
     */
    public function saveAction_whenSetPaymentDataThrowsException_logsExceptionAndRedirectsToPreviousPage()
    {
        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);
        $this->requestMock->method('getPost')->willReturnMap(
            array(
                array('bolt_reference', null, self::BOLT_REFERENCE),
                array('payment', null, array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE)),
            )
        );
        $exception = new Exception('Expected exception');

        $this->salesOrderCreateMock->expects($this->once())->method('setPaymentData')->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        $this->salesOrderCreateMock->expects($this->never())->method('saveQuote');
        $this->adminSessionQuoteMock->expects($this->once())->method('addException')
            ->with($exception, $this->currentMock->__('Order saving error: %s', $exception->getMessage()));
        $this->currentMock->expects($this->once())->method('_redirect')->with('*/*/');

        $this->assertFalse($this->currentMock->saveAction());
    }

    /**
     * @test
     * that getImmutableQuoteIdFromTransaction converts Bolt reference to immutable quote id
     * by retrieving transaction from API and extracting immutable quote id
     *
     * @covers ::getImmutableQuoteIdFromTransaction
     *
     * @throws ReflectionException if class tested doesn't have getImmutableQuoteIdFromTransaction method
     * @throws Exception if test class name is not defined
     */
    public function getImmutableQuoteIdFromTransaction_withBoltReference_retrievesTransactionFromHelperAndExtractsImmutableQuoteId()
    {
        $transaction = (object)array();
        $this->boltHelperMock->expects($this->once())->method('fetchTransaction')->with(self::BOLT_REFERENCE)
            ->willReturn($transaction);
        $this->boltHelperMock->expects($this->once())->method('getImmutableQuoteIdFromTransaction')->with($transaction)
            ->willReturn(self::IMMUTABLE_QUOTE_ID);
        $this->assertEquals(
            self::IMMUTABLE_QUOTE_ID,
            TestHelper::callNonPublicFunction(
                $this->getTestClassPrototype()->setMethods()->getMock(),
                'getImmutableQuoteIdFromTransaction',
                array(
                    self::BOLT_REFERENCE
                )
            )
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Adminhtml_Sales_Order_CreateController::_normalizeOrderData}
     *
     * @param string $shippingMethod    to be used for order
     * @param int    $shippingAsBilling flag used to indicate if shipping address is same as billing
     * @param bool   $shouldStubRequest if true, will stub request methods, others will not
     *
     * @return MockObject instance of class tested
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     * @throws Exception if tested class name is not defined
     */
    private function _normalizeOrderDataSetUp($shippingMethod, $shippingAsBilling, $shouldStubRequest = true)
    {
        TestHelper::stubSingleton('adminhtml/sales_order_create', $this->salesOrderCreateMock);
        $quoteShippingAddress = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')
            ->setMethods(
                array(
                    'getSameAsBilling',
                    'getShippingMethod',
                    'setCollectShippingRates',
                    'collectShippingRates',
                    'save'
                )
            )
            ->getMock();
        $quoteShippingAddress->method('getShippingMethod')->willReturn($shippingMethod);
        $quoteShippingAddress->method('getSameAsBilling')->willReturn(true);
        $quote = $this->getClassPrototype('Mage_Sales_Model_Quote')->getMock();
        $quote->expects($this->atLeastOnce())->method('isVirtual')->willReturn(false);
        $quote->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($quoteShippingAddress);

        $this->salesOrderCreateMock->expects($this->atLeastOnce())->method('getQuote')->willReturn($quote);
        $this->salesOrderCreateMock->expects($this->atLeastOnce())->method('getShippingAddress')
            ->willReturn($quoteShippingAddress);

        $currentMock = $this->getTestClassPrototype()->setMethods(array('getRequest', '_getQuote'))->getMock();
        $currentMock->expects($this->atLeastOnce())->method('_getQuote')->willReturn($quote);
        $currentMock->method('getRequest')->willReturn($this->requestMock);
        $requestPost = array(
            'shipping_method'     => $shippingMethod,
            'shipping_as_billing' => $shippingAsBilling
        );

        if ($shouldStubRequest) {
            $this->requestMock->expects($this->any())->method('getPost')->willReturnMap(
                array(
                    array(null, null, $requestPost),
                    array('shipping_as_billing', null, $shippingAsBilling),
                    array('order', null, $this->orderData),
                    array('reset_shipping', null, 1),
                    array('shipping_method', null, $shippingMethod)
                )
            );

            $this->salesOrderCreateMock->expects($this->once())->method('importPostData')->with($this->orderData);
            $this->salesOrderCreateMock->expects($this->once())->method('resetShippingMethod')->with(true);
        }

        $quoteShippingAddress->expects($this->once())->method('setCollectShippingRates')->with(true)->willReturnSelf();
        $quoteShippingAddress->expects($this->once())->method('collectShippingRates')->willReturnSelf();
        $quoteShippingAddress->expects($this->once())->method('save')->willReturnSelf();

        return $currentMock;
    }

    /**
     * @test
     * that _normalizeOrderData updates post data to the expected format for underlying Magento code to
     * handle it data properly
     *
     * @covers ::_normalizeOrderData
     *
     * @throws ReflectionException if class tested doesn't have _normalizeOrderData method
     * @throws Exception if tested class name is not defined
     */
    public function _normalizeOrderData_withShippingSameAsBillingAndValidShippingMethod_normalizesOrderData()
    {
        $shippingAsBilling = 1;
        $shippingMethod = 'flatrate_flatrate';
        $currentMock = $this->_normalizeOrderDataSetUp($shippingMethod, $shippingAsBilling);
        $this->requestMock->expects($this->any())->method('setPost')
            ->withConsecutive(
                array('order', array('shipping_method' => $shippingMethod, 'increment_id' => 123)),
                array('shipping_as_billing', 1),
                array('collect_shipping_rates', 1)
            );

        TestHelper::callNonPublicFunction(
            $currentMock,
            '_normalizeOrderData'
        );
    }

    /**
     * @test
     * that _normalizeOrderData updates post data to the expected format for underlying Magento code to
     * handle it data properly
     *
     * @covers ::_normalizeOrderData
     *
     * @throws ReflectionException if class tested doesn't have _normalizeOrderData method
     * @throws Exception if tested class name is not defined
     */
    public function _normalizeOrderData_withShippingDifferentThanBillingAndEmptyShippingMethod_normalizesOrderData()
    {
        $shippingAsBilling = 0;
        $currentMock = $this->_normalizeOrderDataSetUp(null, $shippingAsBilling);
        $this->requestMock->expects($this->any())->method('setPost')
            ->withConsecutive(
                array('shipping_as_billing', $shippingAsBilling),
                array('collect_shipping_rates', 1)
            );

        TestHelper::callNonPublicFunction(
            $currentMock,
            '_normalizeOrderData'
        );
    }

    /**
     * @test
     * Verifies that if a shipping method is provide in the POST data, the shipping method is added
     * to the other order data, preserving all other order data fields intact.
     *
     * @covers ::_normalizeOrderData
     */
    public function _normalizeOrderData_whenShippingMethodPosted_addsMethodToOrderData() {

        # Allow proxying to all original methods or request
        $this->requestMock = $this->getClassPrototype('Mage_Core_Controller_Request_Http')
            ->setMethods(null)
            ->getMock();

        $currentMock = $this->_normalizeOrderDataSetUp(
            $shippingMethod = null,
            $shippingAsBilling = true,
            $shouldStubRequest = false
        );

        $this->requestMock->setPost('order', $this->orderData);

        $this->requestMock->setPost('bolt_reference', 'AAAA-BBBB-1234');
        $this->requestMock->setPost('payment', false);
        $this->requestMock->setPost('shipping_method', 'bolt_shipping');

        # confirm no shipping is set in order array before call
        $this->assertEquals(
            array('increment_id' => self::ORDER_INCREMENT_ID),
            $this->requestMock->getPost('order')
        );

        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, '_normalizeOrderData');

        $this->assertArraySubset(
            array(
                'bolt_reference' => 'AAAA-BBBB-1234',
                'payment' => false,
                'shipping_method' => 'bolt_shipping'
            ),
            $this->requestMock->getPost()
        );

        # confirm shipping was added and increment ID was not erased by call
        $this->assertArraySubset(
            array(
                'shipping_method' => 'bolt_shipping',
                'increment_id' => self::ORDER_INCREMENT_ID
            ),
            $this->requestMock->getPost('order')
        );
    }

    /**
     * @test
     * that _postOrderCreateProcessing deactivates immutable quote
     * by assigning the immutable quote as the parent of its parent quote
     *
     * @covers ::_postOrderCreateProcessing
     *
     * @throws ReflectionException if class tested doesn't have _normalizeOrderData method
     * @throws Exception if tested class name is not defined
     */
    public function _postOrderCreateProcessing_always_willDeactivateImmutableQuote()
    {
        $order = Mage::getModel('sales/order', array('quote_id' => self::QUOTE_ID));
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(array('getParentQuoteId'))
            ->getMock();
        $quoteMock->expects($this->once())->method('getParentQuoteId')->willReturn(self::QUOTE_ID);
        $parentQuoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(array('loadByIdWithoutStore', 'setParentQuoteId', 'save'))
            ->getMock();
        $parentQuoteMock->expects($this->once())->method('loadByIdWithoutStore')->with(self::QUOTE_ID)
            ->willReturnSelf();
        $parentQuoteMock->expects($this->once())->method('setParentQuoteId')->with(self::QUOTE_ID)->willReturnSelf();
        $parentQuoteMock->expects($this->once())->method('save')->willReturnSelf();

        $this->adminSessionQuoteMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);

        TestHelper::stubModel('sales/quote', $parentQuoteMock);
        TestHelper::callNonPublicFunction(
            $this->getTestClassPrototype()->setMethods()->getMock(),
            '_postOrderCreateProcessing',
            array(
                $order
            )
        );
    }
}
