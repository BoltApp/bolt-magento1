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
     * that getBoltMerchantUrl returns production merchant url when sandbox mode is set to false in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getBoltMerchantUrl
     *
     * @throws ReflectionException if trait doesn't have merchantUrlProd property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getBoltMerchantUrl_withTestConfigurationSetToFalse_returnsProductionMerchantUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', false);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'merchantUrlProd'),
            $this->currentMock->getBoltMerchantUrl()
        );
    }

    /**
     * @test
     * that getBoltMerchantUrl returns sandbox merchant url when sandbox mode is set to true in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getBoltMerchantUrl
     *
     * @throws ReflectionException if trait doesn't have merchantUrlSandbox property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getBoltMerchantUrl_withTestConfigurationSetToTrue_returnsSandboxMerchantUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', true);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'merchantUrlSandbox'),
            $this->currentMock->getBoltMerchantUrl()
        );
    }

    /**
     * @test
     * that getApiUrl returns production API url when sandbox mode is set to false in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getApiUrl
     *
     * @throws ReflectionException if trait doesn't have apiUrlProd property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getApiUrl_withTestConfigurationSetToFalse_returnsProductionAPIUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', false);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'apiUrlProd'),
            $this->currentMock->getApiUrl()
        );
    }

    /**
     * @test
     * that getApiUrl returns sandbox API url when sandbox mode is set to true in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getApiUrl
     *
     * @throws ReflectionException if trait doesn't have apiUrlTest property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getApiUrl_withTestConfigurationSetToTrue_returnsSandboxAPIUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', true);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'apiUrlTest'),
            $this->currentMock->getApiUrl()
        );
    }

    /**
     * @test
     * that getJsUrl returns production JS url when sandbox mode is set to false in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getJsUrl
     *
     * @throws ReflectionException if trait doesn't have jsUrlProd property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getJsUrl_withTestConfigurationSetToFalse_returnsProductionJSUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', false);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'jsUrlProd'),
            $this->currentMock->getJsUrl()
        );
    }

    /**
     * @test
     * that getJsUrl returns sandbox JS url when sandbox mode is set to true in configuration
     *
     * @covers Bolt_Boltpay_Helper_UrlTrait::getJsUrl
     *
     * @throws ReflectionException if trait doesn't have jsUrlTest property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getJsUrl_withTestConfigurationSetToFalse_returnsSandboxJSUrl()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/test', true);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::getNonPublicProperty($this->currentMock, 'jsUrlTest'),
            $this->currentMock->getJsUrl()
        );
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
}
