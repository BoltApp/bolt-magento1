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
        $result = $mock->isBoltActive();
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
}
