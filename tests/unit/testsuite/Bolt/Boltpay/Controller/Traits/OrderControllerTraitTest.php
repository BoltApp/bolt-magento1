<?php

require_once('TestHelper.php');
require_once('CouponHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Controller_Traits_OrderControllerTrait
 */
class Bolt_Boltpay_Controller_Traits_OrderControllerTraitTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string The name of the PHP entity being tested
     */
    private $testClassName = 'Bolt_Boltpay_Controller_Traits_OrderControllerTrait';

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
     * that createAction when not requested via AJAX will throw an exception indicating this
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessageRegExp /\:\:createAction called with a non AJAX call/
     */
    public function createAction_nonAjax_throwsException()
    {
        $this->request->expects($this->once())->method('isAjax')->willReturn(false);
        $this->currentMock->createAction();
    }

    /**
     * @test
     * that if method is called when the shopping cart is empty, a JSON error will be sent
     * as a response via {@see Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData}
     * indicating that the cart is empty and that products should be added to the cart
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData
     */
    public function createAction_withEmptySessionQuote_respondsWithEmptyCartJsonError()
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

        $this->expectsErrorResponse('Your shopping cart is empty. Please add products to the cart.');
        $this->currentMock->createAction();
    }


    /**
     * @test
     * that if called when no shipping option has been selected, a JSON error will be sent
     * as a response via {@see Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData}
     * instructing the user to check their address and check select a shipping option
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData
     */
    public function createAction_whenShippingNotSelected_respondsWithSelectShippingJsonError()
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
        $this->expectsErrorResponse(
            'A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.'
        );
        $this->currentMock->createAction();
    }

    /**
     * @test
     * that if called when no billing address and with no shipping option selected, a JSON error will
     * be sent via {@see Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData} as a response
     * instructing the user to check their address and check select a shipping option
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData
     */
    public function createAction_withNoBillingAddressAndShippingNotSelected_respondsWithShippingJsonError()
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
        $this->expectsErrorResponse(
            'A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.'
        );
        $this->currentMock->createAction();
    }

    /**
     *
     * that if called in a valid state, (e.g. a non-empty cart with all virtual items),
     * then a request will be sent to Bolt.  Bolt, in return, will send an order token
     * which the method via {@see Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData}
     * is expected to respond with JSON that includes the order token.
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData
     */
    public function createAction_whenAllDataCorrectlySet_respondsWithBoltOrderJsonWithOrderToken()
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

        $this->expectsResponse(array('cart_data' => array('orderToken' => self::BOLT_ORDER_TOKEN)));

        $this->currentMock->createAction();
    }

    /**
     * @test
     * that when called under the context of an admin session, it will attempt
     * to create the cart data for the admin checkout type
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::createAction
     */
    public function createAction_underAdminContext_willCreateCartWithAdminType()
    {
        $this->currentMock = $this->getClassPrototype( 'Bolt_Boltpay_Adminhtml_Sales_Order_CreateController', true )
            ->setMethods(array('getRequest', 'getLayout', 'getResponse', 'prepareAddressData', 'getCartData'))
            ->getMock();

        $this->currentMock->method('getLayout')->willReturn($this->layout);
        $this->currentMock->method('getRequest')->willReturn($this->request);
        $this->currentMock->method('getResponse')->willReturn($this->response);

        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->getMock();

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

        $this->currentMock->expects($this->once())->method('getCartData')
            ->with($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN);

        $this->response->expects($this->once())->method('setHeader');
        $this->response->expects($this->once())->method('setBody');
        $this->currentMock->createAction();
    }

    /**
     * @test
     * that retrieving cart data from cache always occurs if the quote has been cached,
     * regardless of the checkout type
     *
     * @covers       Bolt_Boltpay_Controller_Traits_OrderControllerTrait::getCartData
     * @dataProvider checkoutTypesProvider
     *
     * @param string $checkoutType Bolt checkout type code
     * @throws ReflectionException from TestHelper if method called doesn't exist
     * @throws Zend_Db_Adapter_Exception from CouponHelper if quote deletion fails
     */
    public function getCartData_whenQuoteHasBeenCached_willGetCartDataFromTheCache($checkoutType)
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
     * that cloning a quote for all checkout types returns a valid quote clone object
     *
     * @covers Bolt_Boltpay_Controller_Traits_OrderControllerTrait::cloneQuote
     * @dataProvider checkoutTypesProvider
     */
    public function cloneQuote_withAllTypes_producesClone()
    {
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $clonedQuote = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'cloneQuote',
            array(
                Mage::getModel('sales/quote')->load($quoteId),
                Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE
            )
        );
        $this->assertInstanceOf('Mage_Sales_Model_Quote', $clonedQuote);
        $this->assertEquals($quoteId, $clonedQuote->getParentQuoteId());
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
            "Admin Checkout" => array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN),
            "FireCheckout" => array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT),
            "MultiPage Checkout" => array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE),
            "OnePage Checkout" => array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE),
            "Product Page Checkout" => array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE)
        );
    }

    /**
     * Configures response mock to expect JSON error response with a message
     *
     * @param string $errorMessage to expect inside JSON body
     */
    private function expectsErrorResponse($errorMessage)
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
    private function expectsResponse($responseData)
    {
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json', true);
        $this->response->expects($this->once())->method('setBody')->with(json_encode($responseData));
    }
}