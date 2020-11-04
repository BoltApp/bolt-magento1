<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_UrlTrait
 */
class Bolt_Boltpay_Helper_UrlTraitTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_UrlTrait Mock of the current trait
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Url Mock instance of core/url model
     */
    private $urlModelMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Adminhtml_Model_Url Mock instance of adminhtml/url model
     */
    private $adminUrlModelMock;

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_UrlTrait')
            ->getMockForTrait();
        $this->urlModelMock = $this->getMockBuilder('Mage_Core_Model_Url')
            ->setMethods(array('getUrl'))
            ->enableProxyingToOriginalMethods()
            ->getMock();
        $this->adminUrlModelMock = $this->getMockBuilder('Mage_Adminhtml_Model_Url')
            ->setMethods(array('getUrl'))
            ->enableProxyingToOriginalMethods()
            ->getMock();
    }

    /**
     * Restore original Mage values using the mocking helper and cleanup global variable
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
        unset($_SERVER['HTTPS']);
    }

    /**
     * @test
     * @covers Bolt_Boltpay_Helper_UrlTrait::getApiUrl
     * @dataProvider providerGetApiUrl
     */
    public function getApiUrl($test, $customValue, $result) {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', $test);
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/custom_api', $customValue);
        $this->assertEquals(
            $result,
            $this->currentMock->getApiUrl()
        );
    }

    public function providerGetApiUrl() {
        return [
            [false, '', 'https://api.bolt.com/'],
            [false, 'https://api.vitaliy.dev.bolt.me/', 'https://api.bolt.com/'],
            [true, '', 'https://api-sandbox.bolt.com/'],
            [true, 'https://wrong.url.com/', 'https://api-sandbox.bolt.com/'],
            [true, 'https://api.vitaliy.dev.bolt.me/', 'https://api.vitaliy.dev.bolt.me/'],
        ];
    }

    /**
     * @test
     * @covers Bolt_Boltpay_Helper_UrlTrait::getBoltMerchantUrl
     * @dataProvider providerGetBoltMerchantUrl
     */
    public function getBoltMerchantUrl($test, $customValue, $result) {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', $test);
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/custom_merchant', $customValue);
        $this->assertEquals(
            $result,
            $this->currentMock->getBoltMerchantUrl()
        );
    }

    public function providerGetBoltMerchantUrl() {
        return [
            [false, '', 'https://merchant.bolt.com'],
            [false, 'https://merchant.vitaliy.dev.bolt.me', 'https://merchant.bolt.com'],
            [true, '', 'https://merchant-sandbox.bolt.com'],
            [true, 'https://wrong.url.com', 'https://merchant-sandbox.bolt.com'],
            [true, 'https://merchant.vitaliy.dev.bolt.me', 'https://merchant.vitaliy.dev.bolt.me'],
        ];
    }

    /**
     * @test
     * @covers Bolt_Boltpay_Helper_UrlTrait::getJsUrl
     * @dataProvider providerGetJsUrl
     */
    public function getJsUrl($test, $customValue, $result) {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', $test);
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/custom_js', $customValue);
        $this->assertEquals(
            $result,
            $this->currentMock->getJsUrl()
        );
    }

    public function providerGetJsUrl() {
        return [
            [false, '', 'https://connect.bolt.com'],
            [false, 'https://connect.vitaliy.dev.bolt.me', 'https://connect.bolt.com'],
            [true, '', 'https://connect-sandbox.bolt.com'],
            [true, 'https://wrong.url.com', 'https://connect-sandbox.bolt.com'],
            [true, 'https://connect.vitaliy.dev.bolt.me', 'https://connect.vitaliy.dev.bolt.me'],
        ];
    }

    /**
     * @test
     * that getJsUrl returns production JS url appended with connect filename when sandbox mode is set to false in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getConnectJsUrl
     *
     * @throws ReflectionException if trait doesn't have jsUrlProd property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getConnectJsUrl_withTestConfigurationSetToFalse_returnsProductionJSUrlAppendedWithConnectFilename()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', false);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'jsUrlProd') . "/connect.js",
            $this->currentMock->getConnectJsUrl()
        );
    }

    /**
     * @test
     * that getJsUrl returns production JS url appended with connect filename when sandbox mode is set to true in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getConnectJsUrl
     *
     * @throws ReflectionException if trait doesn't have jsUrlTest property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getConnectJsUrl_withTestConfigurationSetToTrue_returnsSandboxJSUrlAppendedWithConnectFilename()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', true);
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/ls', '');
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'jsUrlTest') . "/connect.js",
            $this->currentMock->getConnectJsUrl()
        );
    }

    /**
     * @test
     * That method calls Mage::getUrl without _secure parameter
     * When store is configured to not use secure URLs
     *
     * @covers       Bolt_Boltpay_Helper_UrlTrait::getMagentoUrl
     * @dataProvider getMagentoUrlProvider
     *
     * @param string $route path used to generate url
     * @param array  $params to add to generated url
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if _isFrontSecure property doesn't exist in store object
     */
    public function getMagentoUrl_fromFrontendWithUnsecureUrl_delegatesCallToCoreUrlModelWithoutSecureParameter($route, $params)
    {
        Bolt_Boltpay_TestHelper::setStoreProperty('_isFrontSecure', false);

        $this->urlModelMock->expects($this->once())->method('getUrl')
            ->with($route, $this->logicalNot(new PHPUnit_Framework_Constraint_ArraySubset(array('_secure' => true))));

        Bolt_Boltpay_TestHelper::stubModel('core/url', $this->urlModelMock);

        $result = $this->currentMock->getMagentoUrl($route, $params, false);

        $this->assertStringStartsWith(
            Mage::getStoreConfig('web/unsecure/base_link_url'),
            $result
        );
    }

    /**
     * @test
     * that method calls Mage::getUrl with _secure parameter set to true
     * When requested via HTTPS and store is configured to use secure URLs
     *
     * @covers       Bolt_Boltpay_Helper_UrlTrait::getMagentoUrl
     * @dataProvider getMagentoUrlProvider
     *
     * @param string $route path used to generate url
     * @param array  $params to add to generated url
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if _isFrontSecure property doesn't exist in store object
     */
    public function getMagentoUrl_fromFrontendWithSecureUrl_delegatesCallToCoreUrlModelWithSecureParameter($route, $params)
    {
        $_SERVER['HTTPS'] = 'on';
        Bolt_Boltpay_TestHelper::setStoreProperty('_isFrontSecure', true);

        $this->urlModelMock->expects($this->once())->method('getUrl')
            ->with($route, new PHPUnit_Framework_Constraint_ArraySubset(array('_secure' => true)));

        Bolt_Boltpay_TestHelper::stubModel('core/url', $this->urlModelMock);

        $result = $this->currentMock->getMagentoUrl($route, $params, false);

        $this->assertStringStartsWith(
            Mage::getStoreConfig('web/secure/base_link_url'),
            $result
        );
    }

    /**
     * @test
     * That method calls adminhtml helper getUrl without _secure parameter
     * When requested via HTTP and admin is configured to not use secure URLs
     *
     * @covers       Bolt_Boltpay_Helper_UrlTrait::getMagentoUrl
     * @dataProvider getMagentoUrlProvider
     *
     * @param string $route path used to generate url
     * @param array  $params to add to generated url
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if _isAdminSecure property doesn't exist in store object
     */
    public function getMagentoUrl_fromBackendWithUnsecureUrl_delegatesCallToAdminUrlModelWithoutSecureParameter($route, $params)
    {
        $_SERVER['HTTPS'] = false;
        Bolt_Boltpay_TestHelper::setStoreProperty('_isAdminSecure', false);
        $this->adminUrlModelMock->expects($this->once())->method('getUrl')
            ->with($route, $this->logicalNot(new PHPUnit_Framework_Constraint_ArraySubset(array('_secure' => true))));
        Bolt_Boltpay_TestHelper::stubModel('adminhtml/url', $this->adminUrlModelMock);
        $this->currentMock->getMagentoUrl($route, $params, true);
    }

    /**
     * @test
     * That method calls adminhtml helper getUrl with _secure parameter
     * When requested via HTTPS and admin is configured to use secure URLs
     *
     * @covers       Bolt_Boltpay_Helper_UrlTrait::getMagentoUrl
     * @dataProvider getMagentoUrlProvider
     *
     * @param string $route path used to generate url
     * @param array  $params to add to generated url
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if _isAdminSecure property doesn't exist in store object
     */
    public function getMagentoUrl_fromBackendWithSecureUrl_delegatesCallToAdminUrlModelWithSecureParameter($route, $params)
    {
        $_SERVER['HTTPS'] = 'on';
        Bolt_Boltpay_TestHelper::setStoreProperty('_isAdminSecure', true);

        $this->adminUrlModelMock->expects($this->once())->method('getUrl')
            ->with($route, new PHPUnit_Framework_Constraint_ArraySubset(array('_secure' => true)));
        Bolt_Boltpay_TestHelper::stubModel('adminhtml/url', $this->adminUrlModelMock);
        $this->currentMock->getMagentoUrl($route, $params, true);
    }

    /**
     * Data provider for various combinations of Magento URL routes and parameters
     *
     * @return array consisting of ($route, $params)
     */
    public function getMagentoUrlProvider()
    {
        return array(
            'Homepage'             => array(
                'route'  => 'cms/index/index',
                'params' => array()
            ),
            'Product Details Page' => array(
                'route'  => 'catalog/product/view',
                'params' => array('id' => 1)
            ),
            'Customer Login Page'  => array(
                'route'  => 'customer/account/login',
                'params' => array('referrer' => base64_encode('http://localhost'))
            ),
        );
    }

    /**
     * @test
     * @covers ::validateCustomUrl
     * @dataProvider providerValidateCustomUrl
     *
     * @param $url
     * @param $expected
     * @throws \ReflectionException
     */
    public function validateCustomUrl($url, $expected)
    {
        $result = $this->currentMock->validateCustomUrl($url);
        $this->assertEquals($expected, $result);
    }

    public function providerValidateCustomUrl()
    {
        return [
            ['https://test.bolt.me', true],
            ['https://test.bolt.me/', true],
            ['https://api.test.bolt.me/', true],
            ['https://test.bolt.com', true],
            ['https://connect-staging.bolt.com', true],
            ['https://test .bolt.com', false],
            ['https://testbolt.me', false],
            ['https://test.com', false],
            ['test.bolt.me', false],
            ['gopher://127.0.0.1:6379/_FLUSHALL%0D%0Abolt.me', false],

        ];
    }
}
