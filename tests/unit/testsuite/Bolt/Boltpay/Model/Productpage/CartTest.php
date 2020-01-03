<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Productpage_Cart
 */
class Bolt_Boltpay_Model_Productpage_CartTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int Quantity in stock to use for dummy product */
    const DUMMY_PRODUCT_STOCK_QUANTITY = 20;

    /** @var int Number of dummy products ordered */
    const DUMMY_PRODUCT_ORDER_QUANTITY = 2;

    /**
     * @var int Id of the dummy product
     */
    private static $productId;

    /**
     * @var string Name of the class currently tested, required by {@see Bolt_Boltpay_MockingTrait}
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Productpage_Cart';

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data Mocked instance of the Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Productpage_Cart Mocked instance of current model
     */
    private $currentMock;

    /**
     * Create dummy product and clear Mage registry before tests are executed
     *
     * @throws Exception if unable to create dummy product
     */
    public static function setUpBeforeClass()
    {
        Bolt_Boltpay_TestHelper::clearRegistry();
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(
            uniqid('PHPUNIT_TEST_'),
            array(),
            self::DUMMY_PRODUCT_STOCK_QUANTITY
        );
    }

    /**
     * Setup test dependencies, executed before each test
     *
     * @throws Mage_Core_Exception if unable to stub Boltpay helper
     * @throws Exception if the variable $testClassName is not specified in the class
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->setMethods()->getMock();
        $this->boltHelperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logException', 'getItemImageUrl'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubHelper('boltpay', $this->boltHelperMock);
    }

    /**
     * Restore original stubbed methods
     *
     * @throws ReflectionException via _configProxy if Mage doesn't have _config property
     * @throws Mage_Core_Model_Store_Exception if store doesn't  exist
     * @throws Mage_Core_Exception from registry if key already exists
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * Clear Mage registry
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_TestHelper::clearRegistry();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @test
     * that init method sets internal cartRequest property
     *
     * @covers ::init
     * @covers ::validateCartRequest
     * @covers ::createCart
     * @covers ::createImmutableQuote
     * @covers ::setCartResponse
     *
     * @throws ReflectionException if class tested doesn't have cartRequest property
     * @throws Exception if the variable $testClassName is not specified in the class
     * @return MockObject|Bolt_Boltpay_Model_Productpage_Cart mock instance to allow chaining
     */
    public function init_withValidCartRequest_setsInternalCartRequestProperty()
    {
        $cartRequest = json_decode(
            json_encode(
                array(
                    'items' => array(
                        array('reference' => self::$productId, 'quantity' => self::DUMMY_PRODUCT_ORDER_QUANTITY)
                    )
                )
            )
        );
        $this->currentMock->init($cartRequest);
        $this->assertEquals(
            $cartRequest,
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'cartRequest')
        );
        return $this->currentMock;
    }

    /**
     * @test
     * that generateData returns data array when provided with valid cart request
     *
     * @covers ::generateData
     * @covers ::validateCartRequest
     * @covers ::validateCartInfo
     * @covers ::validateEmptyCart
     * @covers ::getCartRequestItems
     * @covers ::validateProductsExist
     * @covers ::validateProductsQty
     * @covers ::validateProductsStock
     * @covers ::createCart
     * @covers ::createImmutableQuote
     * @covers ::setCartResponse
     * @depends init_withValidCartRequest_setsInternalCartRequestProperty
     *
     * @param MockObject|Bolt_Boltpay_Model_Productpage_Cart $currentMock tested class instance from previous test
     * @return MockObject|Bolt_Boltpay_Model_Productpage_Cart mock instance to allow chaining
     * @throws ReflectionException if class tested doesn't have httpCode or cartResponse properties
     */
    public function generateData_withValidData_populatesInternalProperties($currentMock)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        $this->assertTrue($currentMock->generateData());
        $httpCode = Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'httpCode');
        $cartResponse = Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'cartResponse');

        $this->assertArraySubset(
            array(
                'order_reference' => Mage::getSingleton('checkout/cart')->getQuote()->getId(),
                'currency'        => 'USD',
                'total_amount'    => 2000
            ),
            $cartResponse
        );
        $this->assertArrayHasKey('items', $cartResponse);
        $cartResponseItems = $cartResponse['items'];
        $this->assertCount(1, $cartResponseItems);
        $item = reset($cartResponseItems);
        $this->assertArraySubset(
            array(
                'name'         => $product->getName(),
                'sku'          => $product->getSku(),
                'description'  => $product->getDescription(),
                'unit_price'   => $product->getPrice() * 100,
                'total_amount' => $product->getPrice() * self::DUMMY_PRODUCT_ORDER_QUANTITY * 100
            ),
            $item
        );
        $this->assertEquals(200, $httpCode);
        return $currentMock;
    }

    /**
     * @test
     * that generate data returns false and sets error response with appropriate message when request not initialized
     *
     * @covers ::generateData
     * @covers ::validateCartRequest
     * @covers ::validateCartInfo
     * @covers ::setErrorResponseAndThrowException
     * @throws ReflectionException if class tested doesn't have httpCode, cartResponse or responseError properties
     */
    public function generateData_uninitialized_willReturnFalseAndSetResponseError()
    {
        $this->boltHelperMock->expects($this->never())->method('notifyException');
        $this->boltHelperMock->expects($this->never())->method('logException');

        $this->assertFalse($this->currentMock->generateData());
        $httpCode = Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'httpCode');
        $cartResponse = Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'cartResponse');
        $errorResponse = Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'responseError');
        $this->assertEquals(422, $httpCode);
        $this->assertNull($cartResponse);
        $this->assertEquals(
            array(
                'code'    => Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_INVALID_SIZE,
                'message' => 'Invalid cart information'
            ),
            $errorResponse
        );
        return $this->currentMock;
    }

    /**
     * @test
     * that generate data returns false and logs exception thrown from {@see Bolt_Boltpay_Model_BoltOrder::cloneQuote}
     *
     * @covers ::generateData
     * @covers ::createImmutableQuote
     * @covers ::getProductById
     * @depends init_withValidCartRequest_setsInternalCartRequestProperty
     *
     * @param MockObject|Bolt_Boltpay_Model_Productpage_Cart $currentMock tested class instance from previous test
     * @throws ReflectionException if unable to stub boltOrder model
     */
    public function generateData_whenQuoteCloningThrowsException_returnsFalseAndLogsException($currentMock)
    {
        $boltOrderMock = $this->getClassPrototype('Bolt_Boltpay_Model_BoltOrder')
            ->setMethods(array('cloneQuote'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubModel('boltpay/boltOrder', $boltOrderMock);
        $exception = new Exception('Unable to clone quote');
        $boltOrderMock->expects($this->once())->method('cloneQuote')->willThrowException($exception);

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);

        $this->assertFalse($currentMock->generateData());
    }

    /**
     * Creates instance of tested class with specific cartRequest set
     *
     * @param array|null $cartRequest to set as tested class instance property
     * @return MockObject|Bolt_Boltpay_Model_Productpage_Cart instance of class test
     * @throws ReflectionException if class tested doesn't have cartRequest
     */
    private function validationMethodSetUp($cartRequest = null)
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('setErrorResponseAndThrowException'))
            ->getMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($currentMock, 'cartRequest', json_decode(json_encode($cartRequest)));
        return $currentMock;
    }

    /**
     * @test
     * that validateCartInfo sets appropriate error when cartRequest is not set
     *
     * @covers ::validateCartInfo
     *
     * @throws ReflectionException if unable to setup mocked instance or it doesn't have validateCartInfo method
     */
    public function validateCartInfo_emptyCartRequest_willTriggerErrorResponse()
    {
        $currentMock = $this->validationMethodSetUp();
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_INVALID_SIZE, "Invalid cart information", 422);
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateCartInfo');
    }

    /**
     * @test
     * that validateEmptyCart sets appropriate error when cartRequest doesn't have any items
     *
     * @covers ::validateEmptyCart
     *
     * @throws ReflectionException if unable to setup mocked instance or it doesn't have validateEmptyCart method
     */
    public function validateEmptyCart_cartRequestWithoutItems_willTriggerErrorResponse()
    {
        $currentMock = $this->validationMethodSetUp(array('items' => array()));
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_INVALID_SIZE, "Empty cart request", 422);
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateEmptyCart');
    }

    /**
     * @test
     * that validateProductsExist sets appropriate error when an item in cartRequest has invalid product id
     *
     * @covers ::validateProductsExist
     *
     * @throws ReflectionException if unable to setup mocked instance or it doesn't have validateProductsExist method
     */
    public function validateProductsExist_productDoesntExist_willTriggerErrorResponse()
    {
        $invalidProductId = -425838;
        $currentMock = $this->validationMethodSetUp(array('items' => array(array('reference' => $invalidProductId))));
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_INVALID_REFERENCE,
                "Product $invalidProductId was not found",
                404
            );
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateProductsExist');
    }

    /**
     * @test
     * that validateProductsQty sets appropriate error when an item in cartRequest has invalid quantity
     *
     * @covers ::validateProductsQty
     *
     * @throws ReflectionException if unable to setup mocked instance or it doesn't have validateProductsQty method
     */
    public function validateProductsQty_itemHasInvalidQuantity_willTriggerErrorResponse()
    {
        $currentMock = $this->validationMethodSetUp(array('items' => array(array('reference' => self::$productId))));
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_INVALID_QUANTITY,
                "Invalid product quantity",
                422
            );
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateProductsQty');
    }

    /**
     * @test
     * that validateProductsStock invokes setErrorResponseAndThrowException method
     * if product quantity in cart is larger than stock
     *
     * @covers ::validateProductsStock
     *
     * @throws ReflectionException if unable to setup mocked instance or it doesn't have validateCartInfo method
     */
    public function validateProductsStock_productInsufficientStock_willTriggerErrorResponse()
    {
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        $currentMock = $this->validationMethodSetUp(
            array(
                'items' => array(
                    array(
                        'reference' => self::$productId,
                        'quantity'  => self::DUMMY_PRODUCT_STOCK_QUANTITY + 1
                    )
                )
            )
        );
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Productpage_Cart::ERR_CODE_OUT_OF_STOCKS,
                "Product {$product->getName()} is out of stock",
                422
            );
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateProductsStock');
    }

    /**
     * @test
     * that getSessionCart returns checkout cart from Mage::getSingleton('checkout/cart')
     *
     * @covers ::getSessionCart
     */
    public function getSessionCart_always_returnsCheckoutCartFromMage()
    {
        $sessionCartMock = $this->getClassPrototype('Mage_Checkout_Model_Cart')
            ->setMethods()->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('checkout/cart', $sessionCartMock);
        $this->assertEquals($sessionCartMock, $this->currentMock->getSessionCart());
    }

    /**
     * @test
     * that getSessionQuote returns quote from session cart
     *
     * @covers ::getSessionQuote
     *
     * @throws ReflectionException
     * @throws Mage_Core_Exception
     */
    public function getSessionQuote_always_returnsQuote()
    {
        $quote = Mage::getModel('sales/quote');
        $sessionCartMock = $this->getClassPrototype('Mage_Checkout_Model_Cart')
            ->setMethods(array('getQuote'))->getMock();
        $sessionCartMock->expects($this->once())->method('getQuote')->willReturn($quote);
        Bolt_Boltpay_TestHelper::stubSingleton('checkout/cart', $sessionCartMock);
        $this->assertEquals(
            $quote,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getSessionQuote')
        );
    }

    /**
     * @test
     * that getGeneratedItems returns expected array containing data of provided quote items
     *
     * @covers ::getGeneratedItems
     *
     * @throws ReflectionException if getGeneratedItems method doesn't exist
     */
    public function getGeneratedItems_withValidQuote_returnsArrayOfQuoteItemData()
    {
        $imageUrl = 'http://localhost/img.jpg';

        /** @var Mage_Sales_Model_Quote|MockObject $quoteMock */
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems', 'getId'))->getMock();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);

        /** @var Mage_Sales_Model_Quote_Item $orderItem */
        $orderItem = Mage::getModel('sales/quote_item')
            ->setProductId($product->getId())
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice($product->getPrice())
            ->setQty(self::DUMMY_PRODUCT_ORDER_QUANTITY);

        $this->boltHelperMock->expects($this->once())->method('getItemImageUrl')->with($orderItem)
            ->willReturn($imageUrl);

        $quoteMock->expects($this->once())->method('getAllVisibleItems')->willReturn(array($orderItem));
        $quoteMock->expects($this->once())->method('getId')->willReturn(1);

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getGeneratedItems',
            array($quoteMock)
        );

        $this->assertCount(1, $result);
        $item = reset($result);

        $this->assertArraySubset(
            array(
                'image_url'    => $imageUrl,
                'name'         => $orderItem->getName(),
                'sku'          => $orderItem->getSku(),
                'description'  => $product->getDescription(),
                'total_amount' => ($product->getPrice() * $orderItem->getQty()) * 100,
                'unit_price'   => $product->getPrice() * 100,
                'type'         => Bolt_Boltpay_Model_Order_Detail::ITEM_TYPE_PHYSICAL,
                'quantity'     => self::DUMMY_PRODUCT_ORDER_QUANTITY
            ),
            $item
        );
    }

    /**
     * @test
     * that getGeneratedTotal returns expected amount
     *
     * @dataProvider getGeneratedTotal_withVariousItems_returnsTotalProvider
     * @covers ::getGeneratedTotal
     *
     * @throws ReflectionException if class tested doesn't have getGeneratedTotal method
     * @throws Exception if the variable $testClassName is not specified in the class
     */
    public function getGeneratedTotal_withVariousItems_returnsTotal($itemsData, $expectedTotal)
    {
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(array('getAllVisibleItems'))->getMock();
        $quoteMock->expects($this->once())->method('getAllVisibleItems')
            ->willReturn(
                array_map(
                    function ($itemData) {
                        return Mage::getModel('sales/quote_item', $itemData);
                    },
                    $itemsData
                )
            );
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getSessionQuote'))->getMock();
        $currentMock->expects($this->once())->method('getSessionQuote')->willReturn($quoteMock);

        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $currentMock,
            'getGeneratedTotal'
        );
        $this->assertEquals($expectedTotal, $result);
    }

    /**
     * Data provider for {@see getGeneratedTotal_withVariousItems_returnsTotal}
     *
     * @return array containing items quantity, price and expected total
     */
    public function getGeneratedTotal_withVariousItems_returnsTotalProvider()
    {
        return array(
            'Single item'                      => array(
                'itemsData'     => array(
                    array('qty' => 5, 'price' => 6)
                ),
                'expectedTotal' => 3000
            ),
            'Single item with float price'     => array(
                'itemsData'     => array(
                    array('qty' => 2, 'price' => 5.455)
                ),
                'expectedTotal' => 1092
            ),
            'Multiple items'                   => array(
                'itemsData'     => array(
                    array('qty' => 2, 'price' => 5),
                    array('qty' => 4, 'price' => 10),
                    array('qty' => 5, 'price' => 40),
                ),
                'expectedTotal' => 25000
            ),
            'Multiple items with float prices' => array(
                'itemsData'     => array(
                    array('qty' => 2, 'price' => 5.3),
                    array('qty' => 4, 'price' => 10.45),
                    array('qty' => 5, 'price' => 40.55),
                ),
                'expectedTotal' => 25515
            ),
        );
    }

    /**
     * @test
     * that setErrorResponseAndThrowException will throw exception if it's provided in parameters
     *
     * @covers ::setErrorResponseAndThrowException
     * @expectedException Exception
     * @expectedExceptionMessage Custom exception
     *
     * @throws ReflectionException if class tested doesn't have setErrorResponseAndThrowException method
     */
    public function setErrorResponseAndThrowException_withExceptionParameter_throwsProvidedException()
    {
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'setErrorResponseAndThrowException',
            array(
                'error code',
                'error message',
                522,
                new Exception('Custom exception')
            )
        );
    }

    /**
     * @test
     * that getResponseBody returns array with success status and cart data when cart request is valid
     *
     * @covers ::getResponseBody
     * @depends generateData_withValidData_populatesInternalProperties
     *
     * @param MockObject|Bolt_Boltpay_Model_Productpage_Cart $currentMock tested class instance from previous test
     * @throws ReflectionException if class tested doesn't have cartResponse property
     */
    public function getResponseBody_withValidRequest_returnsSuccessStatusAndCartData($currentMock)
    {
        $this->assertEquals(
            array(
                'status' => 'success',
                'cart'   => Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'cartResponse')
            ),
            $currentMock->getResponseBody()
        );
    }

    /**
     * @test
     * that getResponseBody returns array with failure status and error message
     *
     * @covers ::getResponseBody
     * @depends generateData_uninitialized_willReturnFalseAndSetResponseError
     *
     * @param MockObject|Bolt_Boltpay_Model_Productpage_Cart $currentMock tested class instance from previous test
     * @throws ReflectionException if class tested doesn't have responseError property
     */
    public function getResponseBody_withInvalidRequest_returnsFailureStatusAndErrorMessage($currentMock)
    {
        $this->assertEquals(
            array(
                'status' => 'failure',
                'error'  => Bolt_Boltpay_TestHelper::getNonPublicProperty($currentMock, 'responseError')
            ),
            $currentMock->getResponseBody()
        );
    }

    /**
     * @test
     * that getResponseHttpCode returns 200 when cart request is valid
     *
     * @covers ::getResponseHttpCode
     * @depends generateData_withValidData_populatesInternalProperties
     *
     * @param MockObject|Bolt_Boltpay_Model_Productpage_Cart $currentMock  tested class instance from previous test
     */
    public function getResponseHttpCode_withValidRequest_returnsOKStatusCode($currentMock)
    {
        $this->assertEquals(
            200,
            $currentMock->getResponseHttpCode()
        );
    }
}
