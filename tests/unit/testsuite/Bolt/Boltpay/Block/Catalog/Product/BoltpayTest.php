<?php

class Bolt_Boltpay_Block_Catalod_Product_BoltpayTest  extends PHPUnit_Framework_TestCase
{
    private $app;

    private $mockBuilder;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->mockBuilder = $this->getMockBuilder('Bolt_Boltpay_Block_Catalog_Product_Boltpay')
        ;
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
        $mock = $this->mockBuilder->setMethodsExcept(array('isBoltActive', 'boltHelper'))->getMock();
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
        $mock = $this->mockBuilder->setMethodsExcept(array('isEnabledProductPageCheckout', 'isBoltActive', 'boltHelper'))->getMock();
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

    /**
     * @test
     * @group BlockCatalogProduct
     * @dataProvider getQuoteIdKeyCases
     * @param array $case
     */
    public function getQuoteIdKey(array $case)
    {
        $this->app->getStore()->setId($case['store_id']);
        $mock = $this->mockBuilder->setMethodsExcept(array('getQuoteIdKey'))->getMock();
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($mock, 'getQuoteIdKey');
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
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
     * @group BlockCatalogProduct
     */
    public function getProductSupportedTypes()
    {
        $expect = array(
            'simple',
            'virtual'
        );
        $mock = $this->mockBuilder->setMethodsExcept(array('getProductSupportedTypes'))->getMock();
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($mock, 'getProductSupportedTypes');
        $this->assertInternalType('array', $result);
        $this->assertEquals($expect, $result);
        
    }
}
