<?php
class Bolt_Boltpay_Helper_CatalogHelperTest extends PHPUnit_Framework_TestCase
{
    private $app;
    
    private $mockBuilder;
    
    public static $productId;
    public static $orderId;
    public static $quoteId;
    public static $addressData = array(
        'firstname' => 'Luke',
        'lastname' => 'Skywalker',
        'street' => 'Sample Street 10',
        'city' => 'Los Angeles',
        'postcode' => '90014',
        'telephone' => '+1 867 345 123 5681',
        'country_id' => 'US',
        'region_id' => 12
    );
    
    
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 2);
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
        $mock = $this->mockBuilder->setMethods(null)->getMock();
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
     */
    public function getQuoteNew()
    {
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), null);
        $mock = $this->mockBuilder->setMethods(array('getSession'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        // Start testing
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(null, $quoteId);
    }

    /**
     * @test
     * @group HelperCatalog
     */
    public function getQuoteExistingQuote()
    {
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), self::$quoteId);
        $mock = $this->mockBuilder->setMethods(array('getSession'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        // Start testing
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(self::$quoteId, $quoteId);
    }

    /**
     * @test
     * @group Fix
     * @group HelperCatalog
     */
    public function getQuoteExistingQuoteAndOrder()
    {
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), self::$quoteId);
        $mock = $this->mockBuilder->setMethods(array('getSession'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        // Start testing
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(self::$quoteId, $quoteId);
    }

    /**
     * @test
     * @group HelperCatalog
     */
    public function getQuoteExistingQuoteAndOrderWithQuote()
    {
        $this->app->getStore()->setConfig('carriers/flatrate/active', 1);
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore(self::$quoteId);
        $product = Mage::getModel('catalog/product')->load(self::$productId);
        
        $quote->setParentQuoteId($quote->getId());
        $quote->reserveOrderId();
        $quote->addProduct($product);
        
        
        $quote->getBillingAddress()->addData(self::$addressData);
        $quote->getShippingAddress()->addData(self::$addressData);
        $quote->getShippingAddress()->setTotalsCollectedFlag(false)->collectShippingRates()->setShippingMethod('flatrate_flatrate');
        $quote->getPayment()->importData(array('method' => 'boltpay'));
        $quote->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        /** @var Mage_Sales_Model_Order $order */
        $order = $service->getOrder();
        $session = Mage::getSingleton('catalog/session');
        $session->setData('ppc_quote_id_'.$this->app->getStore()->getId(), self::$quoteId);
        $mock = $this->mockBuilder->setMethods(array('getSession'))->getMock();
        $mock->expects($this->once())->method('getSession')->will($this->returnValue($session));
        // Start testing
        $result = $mock->getQuote();
        $this->assertInstanceOf(Mage_Sales_Model_Quote::class, $result);
        $quoteId = $result->getId();
        $this->assertEquals(null, $quoteId);
        Mage::register('isSecureArea', true);
        $order->delete();
        Mage::unregister('isSecureArea');
    }

    /**
     * @test
     * @group HelperCatalog
     * @dataProvider getProductRequestCases
     * @param array $case
     */
    public function getProductRequest(array $case)
    {
        $mock = $this->mockBuilder->setMethods(null)->getMock();
        $result = $mock->getProductRequest($case['request']);
        $this->assertInstanceOf(Varien_Object::class, $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getProductRequestCases()
    {
        $cases = array();
        $request = array(
            'qty' => 1,
            'sku' => 'jdbfkjbsjkbk'
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object(),
                'request' => null
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object(),
                'request' => ''
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object(array('qty' => 1)),
                'request' => 1
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object(),
                'request' => new Varien_Object()
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object($request),
                'request' => new Varien_Object($request)
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object('dummy.text'),
                'request' => 'dummy.text'
            )
        );
        $cases[] = array(
            'case' => array(
                'expect' => new Varien_Object($request),
                'request' => $request
            )
        );
        
        return $cases;
    }

}
