<?php

class Bolt_Boltpay_Block_Catalod_Product_BoltpayTest  extends PHPUnit_Framework_TestCase
{
    private $app;

    private $mockBuilder;

    const STORE_ID = 1;
    const WEBSITE_ID = 1;
    const CURRENCY_CODE = 'USD';
    const PRODUCT_PRICE = 12.75;
    
    /**
     * @var int|null Dummy product ID
     */
    private static $productId = null;
    
    /**
     * @var Mage_Core_Model_Store The original store prior to replacing with mocks
     */
    private static $originalStore;
    
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Catalog_Product_Boltpay The mocked instance of the block being tested
     */
    private $currentMock;
    
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Catalog_Model_Product Mocked instance of Product model
     */
    private $productMock;
    
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Store Mocked instance of Store model
     */
    private $storeMock;
    
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt data helper
     */
    private $helperMock;
    
    
    /**
     * Generate dummy product, maintains a reference to the original store and unset registry values set by previous tests
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1', array(), 20);
        self::$originalStore = Mage::app()->getStore();
        Mage::unregister('current_product');
        Mage::unregister('_helper/boltpay');
    }
    
    /**
     * Delete dummy products and restores original store after all test have complete
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
        Mage::app()->setCurrentStore(self::$originalStore);
    }

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->mockBuilder = $this->getMockBuilder(Bolt_Boltpay_Block_Catalog_Product_Boltpay::class)
        ;
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Catalog_Product_Boltpay')
        ->setMethods(array('_toHtml', 'getLayout'))
        ->disableOriginalConstructor()
        ->disableOriginalClone()
        ->disableArgumentCloning()
        ->getMock();
        
        $this->productMock = $this->getMockBuilder('Mage_Catalog_Model_Product')
        ->setMethods(array('getData', 'isInStock', 'getFinalPrice', 'getImageUrl', 'getName'))
        ->getMock();
        
        $this->storeMock = $this->getMockBuilder('Mage_Core_Model_Store')
        ->setMethods(array('getCurrentCurrencyCode'))
        ->getMock();
        
        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
        ->setMethods(
            array('notifyException', 'getBoltCallbacks', 'isBoltPayActive', 'isEnabledProductPageCheckout',
                'getProductPageCheckoutSelector', 'getMagentoUrl')
            )
            ->getMock();
            
            Mage::register('_helper/boltpay', $this->helperMock);
            
    }

    /**
     * Reset Magento registry values
     */
    public function tearDown()
    {
        Mage::unregister('current_product');
        Mage::unregister('_helper/boltpay');
    }

    /**
     * @test
     * @group BlockCatalogProduct
     * @dataProvider isBoltActiveCases
     * @param array $case
     */
    public function isBoltActive(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', $case['active']);
        $mock = $this->mockBuilder->setMethods(null)->getMock();
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($mock, 'isBoltActive');
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function isBoltActiveCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'active' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => '1'
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true
                )
            ),
            
        );
    }

    /**
     * @test
     * @group BlockCatalogProduct
     * @dataProvider isEnabledProductPageCheckoutCases
     * @param array $case
     */
    public function isEnabledProductPageCheckout(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', $case['active']);
        $this->app->getStore()->setConfig('payment/boltpay/enable_product_page_checkout', $case['ppc']);
        $mock = $this->mockBuilder->setMethods(null)->getMock();
        $result = $mock->isEnabledProductPageCheckout();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function isEnabledProductPageCheckoutCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'active' => '',
                    'ppc' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => true,
                    'ppc' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => '',
                    'ppc' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => false,
                    'ppc' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'ppc' => true
                )
            ),
            
        );
    }


//     /**
//      * @test
//      * @group BlockCatalogProduct
//      */
//     public function getProductSupportedTypes()
//     {
//         $expect = array(
//             'simple',
//             'virtual'
//         );
//         $mock = $this->mockBuilder->setMethods(null)->getMock();
//         $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($mock, 'getProductSupportedTypes');
//         $this->assertInternalType('array', $result);
//         $this->assertEquals($expect, $result);
//     }

    /**
     * @test
     * @group BlockCatalogProduct
     * @dataProvider getBoltTokenCases
     * @param array $case
     */
    public function getBoltToken(array $case)
    {
        $quoteStub = $this->getMockBuilder(Mage_Sales_Model_Quote::class)->getMock();
        $helperMock = $this->getMockBuilder(Bolt_Boltpay_Helper_CatalogHelper::class)->getMock();
        $helperMock->method('getQuoteWithCurrentProduct')->will($this->returnValue($quoteStub));
        $boltOrderMock = $this->getMockBuilder(Bolt_Boltpay_Model_BoltOrder::class)->getMock();
        $boltOrderMock->expects($this->any())->method('getBoltOrderToken')->will($this->returnValue($case['response']));
        $mock = $this->mockBuilder->setMethods(array('isSupportedProductType', 'isEnabledProductPageCheckout'))->getMock();
        $mock->expects($this->once())->method('isSupportedProductType')->will($this->returnValue($case['is_supported']));
        $mock->expects($this->any())->method('isEnabledProductPageCheckout')->will($this->returnValue($case['enabled']));
        $result = $mock->getBoltToken($boltOrderMock, $helperMock);
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getBoltTokenCases()
    {
        $response = new stdClass();
        $response->token = 'test.token';
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'response' => $response,
                    'is_supported' => false,
                    'enabled' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'response' => $response,
                    'is_supported' => true,
                    'enabled' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'response' => $response,
                    'is_supported' => false,
                    'enabled' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => $response->token,
                    'response' => $response,
                    'is_supported' => true,
                    'enabled' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'response' => '',
                    'is_supported' => true,
                    'enabled' => true
                )
            ),
        );
    }

    /**
     * @test
     * @group BlockCatalogProduct
     * @dataProvider isSupportedProductTypeCases
     * @param array $case
     */
    public function isSupportedProductType(array $case)
    {
        $productMock = $this->getMockBuilder(Mage_Catalog_Model_Product::class)->getMock();
        $productMock->expects($this->once())->method('getTypeId')->will($this->returnValue($case['type']));
        Mage::register('current_product', $productMock);
        $mock = $this->mockBuilder->setMethods(null)->getMock();
        $result = $mock->isSupportedProductType();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function isSupportedProductTypeCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'type' => 'dummy',
                )
            ),
        );
    }




    /**
     * @test
     * Retrieving product tier price from current product in registry
     *
     * @covers ::getProductTierPrice
     */
    public function getProductTierPrice()
    {
        Mage::register('current_product', $this->productMock);
        $this->productMock->expects($this->once())->method('getData')->with('tier_price')
            ->willReturn(self::PRODUCT_PRICE);
        $this->assertEquals(self::PRODUCT_PRICE, $this->currentMock->getProductTierPrice());
    }

    /**
     * @test
     * Retrieving BoltCheckout config when current product is valid
     *
     * @covers ::getCartDataJsForProductPage
     */
    public function getCartDataJsForProductPage()
    {
        Mage::app()->getStore()->setCurrentCurrencyCode(self::CURRENCY_CODE);

        $this->productMock->setId(9876543210);
        $this->productMock->method('isInStock')->willReturn(true);
        $this->productMock->method('getFinalPrice')->willReturn(self::PRODUCT_PRICE);
        $this->productMock->method('getImageUrl')->willReturn("https://bolt.com/image.png");
        $this->productMock->method('getName')->willReturn("Metal Bolts");

        Mage::register('current_product', $this->productMock);

        $resultJson = $this->currentMock->getCartDataJsForProductPage();
        $result = json_decode($resultJson);
        $this->assertEquals(
            JSON_ERROR_NONE,
            json_last_error()
        );
        $this->assertEquals(self::CURRENCY_CODE, $result->currency);
        $this->assertEquals(self::PRODUCT_PRICE, $result->total);
        $this->assertCount(1, $result->items);
        $this->assertArraySubset(
            array(
                'reference' => $this->productMock->getId(),
                'price' => $this->productMock->getFinalPrice(),
                'quantity' => 1,
                'image' => $this->productMock->getImageUrl(),
                'name' => $this->productMock->getName(),
            ),
            (array)$result->items[0]
        );
    }

    /**
     * @test
     * Retrieving BoltCheckout config when an exception is thrown
     *
     * @covers ::getCartDataJsForProductPage
     */
    public function getCartDataJsForProductPage_exception()
    {
        Mage::app()->setCurrentStore($this->storeMock);
        $exception = new Exception();
        $this->storeMock->expects($this->once())->method('getCurrentCurrencyCode')
            ->willThrowException($exception);
        $this->helperMock->expects($this->once())->method('notifyException')
            ->with($exception);
        $this->assertEquals(
            '""',
            $this->currentMock->getCartDataJsForProductPage()
        );
    }

    /**
     * @test
     * Retrieving BoltCheckout config when current product is not set
     *
     * @covers ::getCartDataJsForProductPage
     */
    public function getCartDataJsForProductPage_noProduct()
    {
        Mage::app()->setCurrentStore($this->storeMock);
        $this->storeMock->expects($this->once())->method('getCurrentCurrencyCode')->willReturn(self::CURRENCY_CODE);
        Mage::register('current_product', null);
        $this->helperMock->expects($this->once())->method('notifyException')
            ->with(new Exception('Bolt: Cannot find product info'));
        $this->assertEquals(
            '""',
            $this->currentMock->getCartDataJsForProductPage()
        );
    }

    /**
     * @test
     * Retrieving BoltCheckout config when current product is out of stock
     *
     * @covers ::getCartDataJsForProductPage
     */
    public function getCartDataJsForProductPage_productOutOfStock()
    {
        Mage::app()->setCurrentStore($this->storeMock);
        $this->storeMock->expects($this->once())->method('getCurrentCurrencyCode')->willReturn(self::CURRENCY_CODE);
        Mage::register('current_product', $this->productMock);
        $this->productMock->expects(self::once())->method('isInStock')->willReturn(false);
        $this->assertEquals(
            '""',
            $this->currentMock->getCartDataJsForProductPage()
        );
    }

    /**
     * @test
     * Verifies retrieving Bolt javascript callbacks from Bolt helper
     *
     * @covers ::getBoltCallbacks
     */
    public function getBoltCallbacks()
    {
        $this->helperMock->expects($this->once())->method('getBoltCallbacks');
        $this->currentMock->getBoltCallbacks();
    }

    /**
     * @test
     * Verifies generation of Bolt javascript success callback
     *
     * @covers ::buildOnSuccessCallback
     */
    public function buildOnSuccessCallback()
    {
        $this->helperMock->expects($this->once())->method('getMagentoUrl')->with('boltpay/order/save');
        $result = $this->currentMock->buildOnSuccessCallback();
        $this->assertStringStartsWith('function', $result);
    }

    /**
     * @test
     * Verifies generation of Bolt javascript close callback
     *
     * @covers ::buildOnCloseCallback
     */
    public function buildOnCloseCallback()
    {
        $this->helperMock->expects($this->once())->method('getMagentoUrl');
        $result = $this->currentMock->buildOnCloseCallback();
        $this->assertContains('location.href =', $result);
    }

    /**
     * @test
     * Retrieving product page checkout selector from Bolt helper
     *
     * @covers ::getProductPageCheckoutSelector
     */
    public function getProductPageCheckoutSelector()
    {
        $this->helperMock->expects($this->once())->method('getProductPageCheckoutSelector');
        $this->currentMock->getProductPageCheckoutSelector();
    }

    /**
     * Provides Magento product types and their support status
     * Used by @see isSupportedProductType
     *
     * @return array of product types and their support status
     */
    public function supportedProductTypesProvider()
    {
        return array(
            array(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, true),
            array(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE, false),
            array(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE, false),
            array(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL, false),
            array(Mage_Catalog_Model_Product_Type::TYPE_GROUPED, false)
        );
    }

    /**
     * Provides permutation of boolean values for whether the product page should have Bolt enabled
     * Used by @see isEnabledProductPageCheckout
     *
     * @return array of arrays of three boolean values with the last value being the && of the first two
     */
    public function isEnabledProductPageCheckoutProvider()
    {
        return array(
            array(true, true, true),
            array(true, false, false),
            array(false, true, false),
            array(false, false, false)
        );
    }

    /**
     * @test
     * Product support status when product is not set
     *
     * @covers ::isSupportedProductType
     */
    public function isSupportedProductType_noProduct()
    {
        Mage::unregister('current_product');
        $this->assertFalse($this->currentMock->isSupportedProductType());
    }

    /**
     * @test
     * Retrieving array of supported product types
     *
     * @covers ::getProductSupportedTypes
     */
    public function getProductSupportedTypes()
    {
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getProductSupportedTypes'
        );
        $this->assertContains(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, $result);
        $this->assertCount(1, $result);
    }
}
