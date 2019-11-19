<?php
class Bolt_Boltpay_Helper_CatalogHelperTest extends PHPUnit_Framework_TestCase
{
    private $app;
    
    private $mockBuilder;
    
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

    public function getQuote()
    {
        
    }
}
