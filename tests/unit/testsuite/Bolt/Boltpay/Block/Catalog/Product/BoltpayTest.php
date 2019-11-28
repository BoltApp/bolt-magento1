<?php

require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Catalog_Product_Boltpay
 */
class Bolt_Boltpay_Block_Catalog_Product_BoltpayTest extends PHPUnit_Framework_TestCase
{
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

    /**
     * Reset Magento registry values
     */
    protected function tearDown()
    {
        Mage::unregister('current_product');
        Mage::unregister('_helper/boltpay');
    }

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
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

        $this->productMock->method('isInStock')->willReturn(true);
        $this->productMock->method('getId')->willReturn(9876543210);
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
     * Retrieving Bolt module status from helper
     *
     * @covers ::isBoltActive
     */
    public function isBoltActive()
    {
        $sentinelValue = "I came from the Bolt_Boltpay_Helper_Data::isBoltPayActive method!";
        $this->helperMock->expects($this->once())->method('isBoltPayActive')->willReturn($sentinelValue);
        $this->assertEquals($sentinelValue, $this->currentMock->isBoltActive());
    }

    /**
     * @test
     * Retrieving product page checkout status from Bolt helper
     *
     * @covers ::isEnabledProductPageCheckout
     * @dataProvider isEnabledProductPageCheckoutProvider
     *
     * @param bool $isBoltPayActive               return value for Bolt_Boltpay_Helper_Data::isBoltPayActive
     * @param bool $isEnabledProductPageCheckout  return value for Bolt_Boltpay_Helper_Data::isEnabledProductPageCheckout
     * @param bool $expected                      the result of ($isBoltPayActive && $isEnabledProductPageCheckout)
     */
    public function isEnabledProductPageCheckout($isBoltPayActive, $isEnabledProductPageCheckout, $expected)
    {
        $this->helperMock->expects($this->once())->method('isBoltPayActive')->willReturn($isBoltPayActive);
        $this->helperMock->expects($isBoltPayActive ? $this->once() : $this->never())
            ->method('isEnabledProductPageCheckout')->willReturn($isEnabledProductPageCheckout);
        $this->assertEquals($expected, $this->currentMock->isEnabledProductPageCheckout());
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
     * @test
     * Checking product support status based on product type
     *
     * @covers ::isSupportedProductType
     * @dataProvider supportedProductTypesProvider
     *
     * @param string $productType Magento product type
     * @param bool $expectedStatus Expected support status of product type in Bolt
     * @throws Mage_Core_Exception by Mage::registry when the key is already set, and the graceful parameter is false
     */
    public function isSupportedProductType($productType, $expectedStatus)
    {
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        $product->setData('type_id', $productType);
        Mage::register('current_product', $product);
        $this->assertEquals($expectedStatus, $this->currentMock->isSupportedProductType());
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
        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getProductSupportedTypes'
        );
        $this->assertContains(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, $result);
        $this->assertCount(1, $result);
    }
}