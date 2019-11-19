<?php
class Bolt_Boltpay_Helper_CatalogHelperTest extends PHPUnit_Framework_TestCase
{
    private $app;
    
    private $mockBuilder;
    
    public static $productId;
    public static $orderId;
    public static $quoteId;
    
    
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
        self::$orderId = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        self::$quoteId = Bolt_Boltpay_OrderHelper::createDummyQuote();
    }

    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_OrderHelper::deleteDummyOrder(self::$orderId);
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
        Bolt_Boltpay_OrderHelper::deleteDummyQuote(self::$quoteId);
    }

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->mockBuilder = $this->getMockBuilder(Bolt_Boltpay_Helper_CatalogHelper::class);
    }

    /**
     * @test
     * @group HelperCatalog
     * @dataProvider getQuoteIdKeyCases
     * @param array $case
     */
    public function getQuoteIdKey(array $case)
    {
        $storeId = $this->app->getStore()->getId();
        $this->app->getStore()->setId($case['store_id']);
        $mock = $this->mockBuilder->setMethodsExcept(array('getQuoteIdKey'))->getMock();
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($mock, 'getQuoteIdKey');
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
        $this->app->getStore()->setId($storeId);
    }

    /**
     * Test cases
     * @return array
     */
    public function getQuoteIdKeyCases()
    {
        return array(
            array(
                'case' => array(
                        'expect' => 'ppc_quote_id_1',
                        'store_id' => 1
                    )
            ),
            array(
                    'case' => array(
                            'expect' => 'ppc_quote_id_5',
                            'store_id' => 5
                        )
            ),
        );
    }

    /**
     * @test
     * @group HelperCatalog
     * @group inProgress
     * @param array $case
     */
    public function getQuoteNew()
    {
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), null);
        $mock = $this->mockBuilder->setMethodsExcept(array('getQuote', 'getQuoteIdKey'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(null, $quoteId);
    }

    /**
     * @test
     * @group HelperCatalog
     * @group inProgress
     * @param array $case
     */
    public function getQuoteExisting()
    {
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), self::$quoteId);
        $mock = $this->mockBuilder->setMethodsExcept(array('getQuote', 'getQuoteIdKey'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(self::$quoteId, $quoteId);
    }

//     /**
//      * @test
//      * @group HelperCatalog
//      * @group inProgress
//      * @param array $case
//      */
//     public function getQuoteExistingOrder()
//     {
//         $session = Mage::getSingleton('catalog/session');
//         $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), self::$quoteId);
//         $session->setLastRealOrderId(self::$orderId);
//         $mock = $this->mockBuilder->setMethodsExcept(array('getQuote', 'getQuoteIdKey'))->getMock();
//         $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
//         $result = $mock->getQuote();
//         $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
//         $quoteId = $result->getId();
//         $this->assertEquals(self::$quoteId, $quoteId);
//     }
    
}
