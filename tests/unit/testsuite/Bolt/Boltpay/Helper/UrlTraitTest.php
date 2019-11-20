<?php
/**
 * Unit tests for Bolt_Boltpay_Helper_ConfigTrait class
 * @author aymelyanov <ayemelyanov@bolt.com>
 *
 */
class Bolt_Boltpay_Helper_UrlTraitTest extends PHPUnit_Framework_TestCase
{
    private $app;

    private $mock;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->mock = $this->getMockForTrait(Bolt_Boltpay_Helper_UrlTrait::class);
    }

    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getBoltMerchantUrlCases
     * @param array $case
     */
    public function getBoltMerchantUrl(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        $result = $this->mock->getBoltMerchantUrl($case['store_id']);
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test Cases
     * @return array
     */
    public function getBoltMerchantUrlCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => 'https://merchant.bolt.com',
                    'test' => false,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant.bolt.com',
                    'test' => false,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://merchant-sandbox.bolt.com',
//                     'test' => false,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://merchant-sandbox.bolt.com',
//                     'test' => false,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://merchant.bolt.com',
                    'test' => false,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant.bolt.com',
                    'test' => false,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant.bolt.com',
                    'test' => false,
                    'store_id' => '1'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://merchant-sandbox.bolt.com',
//                     'test' => true,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://merchant-sandbox.bolt.com',
//                     'test' => true,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://merchant-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://merchant-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => '1'
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getBoltMerchantUrlExceptionCases
     * @expectedException Mage_Core_Model_Store_Exception
     * @param array $case
     */
    public function getBoltMerchantUrlException(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        //$this->expectException(Mage_Core_Model_Store_Exception::class);
        $this->mock->getBoltMerchantUrl($case['store_id']);
    }
    /**
     * Test Cases
     * @return array
     */
    public function getBoltMerchantUrlExceptionCases()
    {
        return array(
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 1000
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 1000
                )
            ),
       );
    }

    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getApiUrlCases
     * @param array $case
     */
    public function getApiUrl(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        $result = $this->mock->getApiUrl($case['store_id']);
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test Cases
     * @return array
     */
    public function getApiUrlCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => 'https://api.bolt.com/',
                    'test' => false,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api.bolt.com/',
                    'test' => false,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://api-sandbox.bolt.com/',
//                     'test' => false,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://api-sandbox.bolt.com/',
//                     'test' => false,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://api.bolt.com/',
                    'test' => false,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api.bolt.com/',
                    'test' => false,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api.bolt.com/',
                    'test' => false,
                    'store_id' => '1'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api-sandbox.bolt.com/',
                    'test' => true,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api-sandbox.bolt.com/',
                    'test' => true,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://api-sandbox.bolt.com/',
//                     'test' => true,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://api-sandbox.bolt.com/',
//                     'test' => true,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://api-sandbox.bolt.com/',
                    'test' => true,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api-sandbox.bolt.com/',
                    'test' => true,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://api-sandbox.bolt.com/',
                    'test' => true,
                    'store_id' => '1'
                )
            ),
        );
    }
    
    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getApiUrlExceptionCases
     * @expectedException Mage_Core_Model_Store_Exception
     * @param array $case
     */
    public function getApiUrlException(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        //$this->expectException(Mage_Core_Model_Store_Exception::class);
        $this->mock->getApiUrl($case['store_id']);
    }
    /**
     * Test Cases
     * @return array
     */
    public function getApiUrlExceptionCases()
    {
        return array(
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 1000
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 1000
                )
            ),
        );
    }
    
    
    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getJsUrlCases
     * @param array $case
     */
    public function getJsUrl(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        $result = $this->mock->getJsUrl($case['store_id']);
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }
    
    /**
     * Test Cases
     * @return array
     */
    public function getJsUrlCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com',
                    'test' => false,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com',
                    'test' => false,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://connect-sandbox.bolt.com',
//                     'test' => false,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://connect-sandbox.bolt.com',
//                     'test' => false,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com',
                    'test' => false,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com',
                    'test' => false,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com',
                    'test' => false,
                    'store_id' => '1'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => null
                )
            ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://connect-sandbox.bolt.com',
//                     'test' => true,
//                     'store_id' => false
//                 )
//             ),
//             array(
//                 'case' => array(
//                     'expect' => 'https://connect-sandbox.bolt.com',
//                     'test' => true,
//                     'store_id' => 0
//                 )
//             ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com',
                    'test' => true,
                    'store_id' => '1'
                )
            ),
        );
    }
    
    /**
     * @test
     * @group Trait
     * @group UrlTrait
     * @dataProvider getJsUrlExceptionCases
     * @expectedException Mage_Core_Model_Store_Exception
     * @param array $case
     */
    public function getJsUrlException(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        //$this->expectException(Mage_Core_Model_Store_Exception::class);
        $this->mock->getJsUrl($case['store_id']);
    }
    /**
     * Test Cases
     * @return array
     */
    public function getJsUrlExceptionCases()
    {
        return array(
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => false,
                    'store_id' => 1000
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 'some text'
                )
            ),
            array(
                'case' => array(
                    'test' => true,
                    'store_id' => 1000
                )
            ),
        );
    }

    /**
     * @test
     * @group UrlTrait
     * @dataProvider getConnectJsUrlCases
     * @param array $case
     */
    public function getConnectJsUrl(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/test', $case['test']);
        $result = $this->mock->getConnectJsUrl();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getConnectJsUrlCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com/connect.js',
                    'test' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com/connect.js',
                    'test' => '0'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com/connect.js',
                    'test' => '1'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect.bolt.com/connect.js',
                    'test' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => 'https://connect-sandbox.bolt.com/connect.js',
                    'test' => true
                )
            ),
            
        );
    }

    /**
     * @test
     * @group UrlTrait
     * @group iks
     * @dataProvider getMagentoUrlCases
     * @param array $case
     */
    public function getMagentoUrl(array $case)
    {
        $result = $this->mock->getMagentoUrl($case['route'], $case['patams'], $case['is_admin']);
        $this->assertInternalType('string', $result);
        //$this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getMagentoUrlCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'route' => '',
                    'patams' => array(),
                    'is_admin' => ''
                )
            ),
        );
    }
}
