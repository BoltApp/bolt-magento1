<?php

require_once('TestHelper.php');
require_once('CouponHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Controller_Traits_OrderControllerTrait
 */
class Bolt_Boltpay_Controller_Traits_OrderControllerTraitTest extends PHPUnit_Framework_TestCase
{
    /** @var string Dummy bolt order token, same as md5('bolt') */
    const BOLT_ORDER_TOKEN = 'a6fe881cecd3fb7660083aea35cce430';

    /**
     * @var Mage_Core_Model_Store The original store prior to replacing with mocks
     */
    private static $originalStore;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Controller_Traits_OrderControllerTrait Mocked instance of the trait being tested
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
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Layout Mocked instance of layout object
     */
    private $layout;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $helperMock;

    /**
     * Unregister helper set from previous tests and save original store to restore after test
     */
    public static function setUpBeforeClass()
    {
        Mage::unregister('_helper/boltpay');
        self::$originalStore = Mage::app()->getStore();
    }

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Controller_Traits_OrderControllerTrait')
            ->setMethods(array('getRequest', 'getLayout', 'getResponse', 'prepareAddressData'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMockForTrait();

        $this->request = $this->getMockBuilder('Mage_Core_Controller_Request_Http')
            ->setMethods(array('isAjax', 'getParam'))
            ->getMock();

        $this->layout = $this->getMockBuilder('Mage_Core_Model_Layout')
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->response = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader', 'setBody'))
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array('notifyException', 'transmit')
            )
            ->getMock();

        Mage::register('_helper/boltpay', $this->helperMock);

        $this->currentMock->method('getLayout')->willReturn($this->layout);
        $this->currentMock->method('getRequest')->willReturn($this->request);
        $this->currentMock->method('getResponse')->willReturn($this->response);
    }

    /**
     * Restores original store after all test have been completed
     */
    public static function tearDownAfterClass()
    {
        Mage::app()->setCurrentStore(self::$originalStore);
    }

    /**
     * Cleanup data from Magento
     */
    protected function tearDown()
    {
        Mage::unregister('_helper/boltpay');
        TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            null
        );
        TestHelper::setNonPublicProperty(
            Mage::getSingleton('adminhtml/session_quote'),
            '_quote',
            null
        );

        Mage::getSingleton('core/session')->unsetData('cached_cart_data');
    }


    /**
     * @test
     * Create action when not requested via AJAX
     *
     * @covers ::createAction
     */
    public function createAction_noAjax()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(false);

        $this->setExpectedExceptionRegExp(
            Mage_Core_Exception::class,
            '/\:\:createAction called with a non AJAX call/'
        );

        $this->currentMock->createAction();
    }

    /**
     * @test
     * Create action when session quote is empty
     *
     * @covers ::createAction
     * @covers ::getCartData
     */
    public function createAction_emptyQuote()
    {
        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems'))
            ->getMock();
        $quote->expects($this->atLeastOnce())->method('getAllVisibleItems')->willReturn(array());

        TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            $quote
        );

        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->layout->expects($this->atLeastOnce())->method('createBlock')->with('boltpay/checkout_boltpay');

        $this->expectErrorResponse('Your shopping cart is empty. Please add products to the cart.');
        $this->currentMock->createAction();
    }


    /**
     * @test
     * Create action when shipping method is not selected
     *
     * @covers ::createAction
     * @covers ::getCartData
     */
    public function createAction_noShipping()
    {
        /** @var Mage_Sales_Model_Quote|PHPUnit_Framework_MockObject_MockObject $quote */
        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems', 'isVirtual', 'getShippingAddress'))
            ->getMock();
        $quoteItem = Mage::getModel('sales/quote_item')
            ->setQuote($quote)
            ->setData(
                array(
                    'product' => Mage::getModel('catalog/product')
                )
            );
        $quote->expects($this->atLeastOnce())->method('getAllVisibleItems')->willReturn(array($quoteItem));
        $quote->expects($this->atLeastOnce())->method('isVirtual')->willReturn(false);
        $shippingAddress = Mage::getModel('sales/quote_address')
            ->setQuote($quote)
            ->setShippingMethod(null);
        $quote->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddress);

        TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            $quote
        );

        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->layout->expects($this->atLeastOnce())->method('createBlock')->with('boltpay/checkout_boltpay');
        $this->expectErrorResponse(
            'A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.'
        );
        $this->currentMock->createAction();
    }

    /**
     * @test
     * Create action when shipping method is not selected and billing address is empty
     *
     * @covers ::createAction
     * @covers ::getCartData
     */
    public function createAction_noShippingnoBillingAddress()
    {
        /** @var Mage_Sales_Model_Quote|PHPUnit_Framework_MockObject_MockObject $quote */
        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems', 'isVirtual', 'getShippingAddress', 'getBillingAddress'))
            ->getMock();
        $quoteItem = Mage::getModel('sales/quote_item')
            ->setQuote($quote)
            ->setData(
                array(
                    'product' => Mage::getModel('catalog/product')
                )
            );
        $quote->expects($this->atLeastOnce())->method('getAllVisibleItems')->willReturn(array($quoteItem));
        $quote->expects($this->atLeastOnce())->method('isVirtual')->willReturn(true);
        $shippingAddress = Mage::getModel('sales/quote_address')
            ->setQuote($quote)
            ->setShippingMethod(null);
        $billingAddress = Mage::getModel('sales/quote_address')
            ->setQuote($quote);
        $quote->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->expects($this->atLeastOnce())->method('getBillingAddress')->willReturn($billingAddress);

        TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            $quote
        );

        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->layout->expects($this->atLeastOnce())->method('createBlock')->with('boltpay/checkout_boltpay');
        $this->expectErrorResponse(
            'A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.'
        );
        $this->currentMock->createAction();
    }

    /**
     * @test
     * Create action will all data set
     *
     * @covers ::createAction
     * @covers ::getCartData
     */
    public function createAction_success()
    {
        /** @var Mage_Sales_Model_Quote|PHPUnit_Framework_MockObject_MockObject $quote */
        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems', 'isVirtual', 'getShippingAddress', 'getBillingAddress'))
            ->getMock();
        $quoteItem = Mage::getModel('sales/quote_item')
            ->setQuote($quote)
            ->setData(
                array(
                    'product' => Mage::getModel('catalog/product')
                )
            );
        $quote->expects($this->atLeastOnce())->method('getAllVisibleItems')->willReturn(array($quoteItem));
        $quote->expects($this->atLeastOnce())->method('isVirtual')->willReturn(true);
        $shippingAddress = Mage::getModel('sales/quote_address')
            ->setQuote($quote)
            ->setShippingMethod('flatrate');
        $billingAddress = Mage::getModel('sales/quote_address')
            ->setQuote($quote);
        $quote->expects($this->atLeastOnce())->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->expects($this->atLeastOnce())->method('getBillingAddress')->willReturn($billingAddress);

        TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            $quote
        );

        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->layout->expects($this->atLeastOnce())->method('createBlock')->with('boltpay/checkout_boltpay');

        $this->helperMock->expects(self::once())->method('transmit')->willReturn(
            (object)array('token' => self::BOLT_ORDER_TOKEN)
        );

        $this->expectResponse(array('cart_data' => array('orderToken' => self::BOLT_ORDER_TOKEN)));

        $this->currentMock->createAction();
    }

    /**
     * @test
     * Create action with admin checkout type
     *
     * @covers ::createAction
     * @covers ::getCartData
     * @covers ::cloneQuote
     */
    public function createAction_admin()
    {
        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems'))
            ->getMock();
        $quoteItem = Mage::getModel('sales/quote_item')
            ->setQuote($quote)
            ->setData(
                array(
                    'product' => Mage::getModel('catalog/product')
                )
            );
        $quote->expects($this->atLeastOnce())->method('getAllVisibleItems')->willReturn(array($quoteItem));

        TestHelper::setNonPublicProperty(
            Mage::getSingleton('adminhtml/session_quote'),
            '_quote',
            $quote
        );

        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);

        $this->request->expects($this->once())->method('isAjax')->willReturn(true);
        $this->request->expects($this->atLeastOnce())->method('getParam')
            ->with('checkoutType', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->willReturn(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN);
        $this->layout->expects($this->atLeastOnce())->method('createBlock')->with('boltpay/checkout_boltpay');

        $this->response->expects($this->once())->method('setHeader');
        $this->response->expects($this->once())->method('setBody');
        $this->currentMock->createAction();
    }

    /**
     * @test
     * Retrieving cart data from cache regardless of checkout type
     *
     * @covers       ::getCartData
     * @dataProvider checkoutTypesProvider
     *
     * @param string $checkoutType Bolt checkout type code
     * @throws ReflectionException from TestHelper if method called doesen't exist
     * @throws Zend_Db_Adapter_Exception from CouponHelper if quote deletion fails
     */
    public function getCartData_cache($checkoutType)
    {
        $layout = $this->getMockBuilder('Mage_Core_Model_Layout')
            ->setMethods(array('getBlock'))
            ->getMock();
        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Controller_Traits_OrderControllerTrait')
            ->setMethods(array('cloneQuote', 'getLayout'))
            ->getMockForTrait();
        $currentMock->method('getLayout')->willReturn($layout);
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $quote = Mage::getModel('sales/quote')->load($quoteId);

        $quoteCacheKey = TestHelper::callNonPublicFunction(
            Mage::getSingleton('boltpay/boltOrder'),
            'calculateCartCacheKey',
            array($quote, $checkoutType)
        );
        Mage::getSingleton('core/session')->setData(
            'cached_cart_data',
            array(
                'creation_time' => time(),
                'key'           => $quoteCacheKey,
                'cart_data'     => serialize((object)array('token' => self::BOLT_ORDER_TOKEN))
            )
        );

        $currentMock->expects(self::never())->method('cloneQuote');

        TestHelper::callNonPublicFunction(
            $currentMock,
            'getCartData',
            array(
                $quote,
                $checkoutType
            )
        );
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * @test
     * Cloning quote when checkout type is multipage
     *
     * @covers ::cloneQuote
     */
    public function cloneQuote_multipage()
    {
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'cloneQuote',
            array(
                Mage::getModel('sales/quote')->load($quoteId),
                Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE
            )
        );
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * Data provider for checkout types
     *
     * @return array of Bolt checkout types
     */
    public function checkoutTypesProvider()
    {
        return array(
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE)
        );
    }

    /**
     * Configures response mock to expect JSON error response with a message
     *
     * @param string $errorMessage to expect inside JSON body
     */
    private function expectErrorResponse($errorMessage)
    {
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);
        $this->response->expects($this->once())->method('setBody')->willReturnCallback(
            function ($body) use ($errorMessage) {
                $data = json_decode($body);
                $this->assertEquals(
                    JSON_ERROR_NONE,
                    json_last_error()
                );
                $this->assertTrue($data->error);
                $this->assertFalse($data->success);
                $this->assertEquals(
                    $errorMessage,
                    $data->error_messages
                );
            }
        );
    }

    /**
     * Configures response mock to expect JSON header with particular response body in JSON
     *
     * @param array $responseData to be expected in response body in JSON format
     */
    private function expectResponse($responseData)
    {
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);
        $this->response->expects($this->once())->method('setBody')->with(json_encode($responseData));
    }
}