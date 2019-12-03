<?php

require_once 'Bolt/Boltpay/controllers/OrderController.php';
require_once 'TestHelper.php';
require_once 'OrderHelper.php';

/**
 * @coversDefaultClass Bolt_Boltpay_OrderController
 */
class Bolt_Boltpay_OrderControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string Dummy HMAC
     */
    const TEST_HMAC = 'fdd6zQftGT36/tGRItDZ0oB48VSptxj6TpZImLy4aZ4=';

    /** @var string Dummy transaction reference */
    const REFERENCE = 'TEST-BOLT-TRNX';

    /** @var string Assumed Firecheckout error message when customer email already exists */
    const CUSTOMER_EMAIL_EXISTS_MESSAGE = 'Customer email exists';

    /**
     * @var int Dummy product id
     */
    private static $productId;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_OrderController Mocked instance of the class being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Request_Http Mocked instance of request object
     */
    private $request;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Response_Http Mocked instance of response object
     */
    private $response;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $helperMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject Mocked instance of Firecheckout class
     */
    private $fireCheckoutStandardMock;

    /**
     * Clear registry data from previous tests and create dummy product
     */
    public static function setUpBeforeClass()
    {
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/firecheckout/type_standard');
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1', array(), 20);
    }

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_OrderController')
            ->setMethods(array('getRequest', 'getResponse', 'getCartData'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->request = $this->getMockBuilder('Mage_Core_Controller_Request_Http')
            ->setMethods(array('isAjax'))
            ->getMock();

        $this->response = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(
                array(
                    'setHeader',
                    'setBody',
                    'setException',
                    'clearAllHeaders',
                    'clearBody',
                    'setHttpResponseCode',
                    'sendHeaders',
                    'sendResponse'
                )
            )
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array(
                    'notifyException',
                    'logException',
                    'fetchTransaction',
                    'addBreadcrumb',
                    'getImmutableQuoteIdFromTransaction',
                    'collectTotals',
                    'save',
                    'verify_hook'
                )
            )
            ->getMock();

        $this->fireCheckoutStandardMock = $this->getMockBuilder('Firecheckout_Model_Type_Standard')
            ->setMethods(
                array(
                    'getQuote',
                    'saveBilling',
                    'saveShipping',
                    'getCustomerEmailExistsMessage',
                    'applyShippingMethod',
                    'registerCustomerIfRequested'
                )
            )
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
        $this->fireCheckoutStandardMock->method('getCustomerEmailExistsMessage')
            ->willReturn(self::CUSTOMER_EMAIL_EXISTS_MESSAGE);

        Mage::register('_singleton/firecheckout/type_standard', $this->fireCheckoutStandardMock);
        Mage::register('_helper/boltpay', $this->helperMock);

        $this->currentMock->method('getRequest')->willReturn($this->request);
        $this->currentMock->method('getResponse')->willReturn($this->response);
    }

    /**
     * Delete dummy product after every test is done
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Reset Magento registry values
     */
    protected function tearDown()
    {
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/firecheckout/type_standard');
        $this->request->setPost(array());
        unset($_SERVER['HTTP_X_BOLT_HMAC_SHA256']);
    }

    /**
     * @test
     * Save action when requested without AJAX
     *
     * @covers ::saveAction
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Bolt_Boltpay_OrderController::saveAction called with a non AJAX call
     */
    public function saveAction_notRequestedViaAJAX_throwsException()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(false);

        $this->currentMock->saveAction();
    }

    /**
     * @test
     * Save action with order already created
     * Only 200 status code should be returned, which happens by default
     *
     * @covers ::saveAction
     */
    public function saveAction_withOrderAlreadyCreated_returnsEmptySuccessResponse()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->request->setPost(array('reference' => self::REFERENCE));

        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);

        $transaction = (object)array();

        $this->helperMock->expects($this->once())->method('fetchTransaction')->with(self::REFERENCE)
            ->willReturn($transaction);
        $this->helperMock->expects($this->once())->method('addBreadcrumb')->willReturnCallback(
            function ($metaData) {
                $this->assertArrayHasKey('Save Action reference', $metaData);
                $this->assertArrayHasKey('reference', $metaData['Save Action reference']);
                $this->assertEquals(self::REFERENCE, $metaData['Save Action reference']['reference']);
            }
        );
        $this->helperMock->expects($this->once())->method('getImmutableQuoteIdFromTransaction')->with($transaction)
            ->willReturn($order->getQuoteId());

        $this->currentMock->saveAction();
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Save action with new order
     * Intentionally don't provide reference to prevent internal calls
     *
     * @covers ::saveAction
     * @expectedException Bolt_Boltpay_OrderCreationException
     * @expectedExceptionMessage Bolt transaction reference is missing in the Magento order creation process.
     */
    public function saveAction_withNewOrder_returnsEmptySuccessResponse()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $reference = null;
        $this->request->setPost(array('reference' => $reference));

        $transaction = (object)array();

        $this->helperMock->expects($this->atLeastOnce())->method('fetchTransaction')->with($reference)
            ->willReturn($transaction);
        $this->helperMock->expects($this->once())->method('addBreadcrumb')->willReturnCallback(
            function ($metaData) use ($reference) {
                $this->assertArrayHasKey('Save Action reference', $metaData);
                $this->assertArrayHasKey('reference', $metaData['Save Action reference']);
                $this->assertEquals($reference, $metaData['Save Action reference']['reference']);
            }
        );
        $this->helperMock->expects($this->atLeastOnce())->method('getImmutableQuoteIdFromTransaction')
            ->with($transaction)->willReturn($reference);

        $this->currentMock->saveAction();
    }

    /**
     * @test
     * Firecheckout action executed with valid data succesfully
     *
     * @covers ::firecheckoutcreateAction
     */
    public function firecheckoutcreateAction_withSufficientData_returnsSuccessResponseWithJSONData()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);

        $quote = Mage::getModel('sales/quote');
        $shippingMethod = 'flatrate';
        $billing = array('use_for_shipping' => false);
        $billingAddressId = 1;
        $shipping = array();
        $shippingAddressId = 2;
        $cartData = array('orderToken' => md5('bolt'));

        $this->request->setPost(
            array(
                'billing'             => $billing,
                'billing_address_id'  => $billingAddressId,
                'shipping_method'     => $shippingMethod,
                'shipping'            => $shipping,
                'shipping_address_id' => $shippingAddressId
            )
        );

        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);

        $this->fireCheckoutStandardMock->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveBilling')
            ->with($billing, $billingAddressId)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveShipping')
            ->with($shipping, $shippingAddressId)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('applyShippingMethod')
            ->with($shippingMethod)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('registerCustomerIfRequested')
            ->willReturn($quote);

        $this->helperMock->expects($this->atLeastOnce())->method('collectTotals')->with($quote)->willReturnSelf();
        $this->helperMock->expects($this->atLeastOnce())->method('save');

        $this->currentMock->expects($this->once())->method('getCartData')
            ->with($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE)
            ->willReturn($cartData);

        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($cartData) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertArrayHasKey('cart_data', $result);
                $this->assertEquals($cartData, $result['cart_data']);
            }
        );

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Firecheckout action when requested without AJAX
     *
     * @covers ::firecheckoutcreateAction
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage OrderController::firecheckoutcreateAction called with a non AJAX call
     */
    public function firecheckoutcreateAction_notRequestedViaAJAX_throwsException()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(false);

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Firecheckout action with billing address returning error on save
     *
     * @covers ::firecheckoutcreateAction
     */
    public function firecheckoutcreateAction_withBillingAddressError_returnsJSONResponseContainingErrorMessage()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);

        $quote = Mage::getModel('sales/quote');
        $billing = array('use_for_shipping' => true);
        $billingAddressId = 1;

        $this->request->setPost(
            array(
                'billing'            => $billing,
                'billing_address_id' => $billingAddressId,
            )
        );
        $errorMessage = 'Firecheckout error message';

        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);

        $this->fireCheckoutStandardMock->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveBilling')
            ->with($billing, $billingAddressId)->willReturn(array('message' => $errorMessage));


        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($errorMessage) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertFalse($result['success']);
                $this->assertTrue($result['error']);
                $this->assertEquals($errorMessage, $result['error_messages']);
                $this->assertEquals('step-address', $result['onecolumn_step']);
            }
        );

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Firecheckout action with saving billing address returning that customer email already exists
     *
     * @covers ::firecheckoutcreateAction
     */
    public function firecheckoutcreateAction_billingError_withCustomerEmailAlreadyExisting_returnsJSONResponseContainingErrorMessage()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);

        $quote = Mage::getModel('sales/quote');
        $billing = array('use_for_shipping' => true);
        $billingAddressId = 1;

        $this->request->setPost(
            array(
                'billing'            => $billing,
                'billing_address_id' => $billingAddressId
            )
        );

        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);

        $this->fireCheckoutStandardMock->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveBilling')
            ->with($billing, $billingAddressId)->willReturn(array('message' => self::CUSTOMER_EMAIL_EXISTS_MESSAGE));


        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertArrayHasKey('error_messages', $result);
                $this->assertArrayHasKey('body', $result);
                $this->assertFalse($result['success']);
                $this->assertTrue($result['error']);
                $this->assertEquals('emailexists', $result['body']['id']);
            }
        );

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Firecheckout action with error returned when saving shipping address
     *
     * @covers ::firecheckoutcreateAction
     */
    public function firecheckoutcreateAction_withShippingAddressError_returnsJSONResponseContainingErrorMessage()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);

        $quote = Mage::getModel('sales/quote');
        $shippingMethod = false;
        $billing = array('use_for_shipping' => false);
        $billingAddressId = 1;
        $shipping = array();
        $shippingAddressId = 2;

        $this->request->setPost(
            array(
                'billing'             => $billing,
                'billing_address_id'  => $billingAddressId,
                'shipping_method'     => $shippingMethod,
                'shipping'            => $shipping,
                'shipping_address_id' => $shippingAddressId
            )
        );

        $errorMessage = 'Shipping address error';

        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);

        $this->fireCheckoutStandardMock->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveBilling')
            ->with($billing, $billingAddressId)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveShipping')
            ->with($shipping, $shippingAddressId)->willReturn(array('message' => $errorMessage));

        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($errorMessage) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertFalse($result['success']);
                $this->assertTrue($result['error']);
                $this->assertEquals($errorMessage, $result['error_messages']);
                $this->assertEquals('step-address', $result['onecolumn_step']);
            }
        );

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Firecheckout action executed with error in cart data
     *
     * @covers ::firecheckoutcreateAction
     */
    public function firecheckoutcreateAction_withCartDataError_returnsJSONResponseContainingErrorMessage()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(true);

        $quote = Mage::getModel('sales/quote');
        $shippingMethod = false;
        $billing = array('use_for_shipping' => false);
        $billingAddressId = 1;
        $shipping = array();
        $shippingAddressId = 2;
        $cartData = array('error' => 'Your shopping cart is empty. Please add products to the cart.');

        $this->request->setPost(
            array(
                'billing'             => $billing,
                'billing_address_id'  => $billingAddressId,
                'shipping_method'     => $shippingMethod,
                'shipping'            => $shipping,
                'shipping_address_id' => $shippingAddressId
            )
        );

        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);

        $this->fireCheckoutStandardMock->expects($this->once())->method('getQuote')->willReturn($quote);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveBilling')
            ->with($billing, $billingAddressId)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('saveShipping')
            ->with($shipping, $shippingAddressId)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('applyShippingMethod')
            ->with($shippingMethod)->willReturn(false);
        $this->fireCheckoutStandardMock->expects($this->once())->method('registerCustomerIfRequested')
            ->willReturn($quote);

        $this->helperMock->expects($this->atLeastOnce())->method('collectTotals')->with($quote)->willReturnSelf();
        $this->helperMock->expects($this->atLeastOnce())->method('save');

        $this->currentMock->expects($this->once())->method('getCartData')
            ->with($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE)
            ->willReturn($cartData);

        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($cartData) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertFalse($result['success']);
                $this->assertTrue($result['error']);
                $this->assertEquals($cartData['error'], $result['error_messages']);
            }
        );

        $this->currentMock->firecheckoutcreateAction();
    }

    /**
     * @test
     * Successfully returning order details from action
     *
     * @covers ::viewAction
     */
    public function viewAction_withValidOrder_returnsOrderDetailsInJSON()
    {
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;
        $reference = md5('bolt' . rand());
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1, 'boltpay');
        Mage::getModel('sales/order_payment')->setLastTransId($reference)->setOrder($order)->save();
        $this->request->setParams(array('reference' => $reference));

        $this->helperMock->expects($this->once())->method('verify_hook')->with('{}', self::TEST_HMAC)->willReturn(true);

        $this->response->expects($this->never())->method('setHttpResponseCode');
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json');
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($order) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertEquals($order->getQuoteId(), $result['order_reference']);
                $this->assertEquals($order->getIncrementId(), $result['display_id']);
            }
        );

        $this->currentMock->viewAction();
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * Retrieving order details from action with invalid HMAC
     *
     * @covers ::viewAction
     */
    public function viewAction_withInvalidHMACParameter_returnsErrorResponseWithErrorMessage()
    {
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;

        $this->helperMock->expects($this->once())->method('verify_hook')->with('{}', self::TEST_HMAC)
            ->willReturn(false);

        $this->response->expects($this->once())->method('setHttpResponseCode')->with(404)->willReturnSelf();
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertEquals('failure', $result['status']);
                $this->assertEquals(6009, $result['error']['code']);
                $this->assertEquals('Failed HMAC Authentication', $result['error']['message']);
            }
        );

        $this->currentMock->viewAction();
    }

    /**
     * @test
     * Retrieving order details from action with missing reference parameter
     *
     * @covers ::viewAction
     */
    public function viewAction_withInvalidReferenceParameter_returnsErrorResponseWithErrorMessage()
    {
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;
        $this->request->setParam('reference', null);

        $this->helperMock->expects($this->once())->method('verify_hook')->with('{}', self::TEST_HMAC)
            ->willReturn(true);

        $this->response->expects($this->once())->method('setHttpResponseCode')->with(404)->willReturnSelf();
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertEquals('failure', $result['status']);
                $this->assertEquals(6009, $result['error']['code']);
                $this->assertEquals('Transaction parameter is required', $result['error']['message']);
            }
        );

        $this->currentMock->viewAction();
    }

    /**
     * @test
     * Retrieving order details from action with reference parameter not related to any order
     *
     * @covers ::viewAction
     */
    public function viewAction_withNoOrderRelatedToProvidedReference_returnsErrorResponseWithErrorMessage()
    {
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;
        $this->request->setParam('reference', md5('bolt-non-existent-order'));

        $this->helperMock->expects($this->once())->method('verify_hook')->with('{}', self::TEST_HMAC)
            ->willReturn(true);

        $this->response->expects($this->once())->method('setHttpResponseCode')->with(409)->willReturnSelf();
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertEquals('failure', $result['status']);
                $this->assertEquals(6009, $result['error']['code']);
                $this->assertEquals('No payment found', $result['error']['message']);
            }
        );

        $this->currentMock->viewAction();
    }
}