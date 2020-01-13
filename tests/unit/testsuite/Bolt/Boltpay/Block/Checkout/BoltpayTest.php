<?php

require_once('TestHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Checkout_Boltpay
 */
class Bolt_Boltpay_Block_Checkout_BoltpayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Mage_Core_Model_App Used to manipulate the Magento application environment
     */
    private $app = null;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data The mocked Bolt Helper class which is used by the current mock
     */
    private $helperMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Checkout_Boltpay The mocked instance of the block being tested
     */
    private $currentMock;

    /**
     * @var $testHelper Bolt_Boltpay_TestHelper
     */
    private $testHelper = null;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        $this->app = Mage::app('default');

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Checkout_Boltpay')
            ->setMethods(array('isAdminAndUseJsInAdmin'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;

        Mage::unregister('_helper/boltpay');
        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array('notifyException', 'logException')
            )
            ->getMock();

        Mage::register('_helper/boltpay', $this->helperMock);

        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    /**
     * Resets Magento registry values after all test have run
     */
    public static function tearDownAfterClass()
    {
        Mage::unregister('_helper/boltpay');
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testBuildCartData()
    {
        $autoCapture = true;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'orderToken' => md5('bolt'),
        );

        $this->assertEquals($cartData, $result);
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testBuildCartDataWithEmptyTokenField()
    {
        $autoCapture = true;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);

        // Prepare test response object
        $testBoltResponse = new stdClass();

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'orderToken'    => '',
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithEmptyTokenField()');
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testBuildCartDataWithoutAutoCapture()
    {
        $autoCapture = false;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'orderToken' => md5('bolt'),
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithoutAutoCapture()');
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testBuildCartDataWithApiError()
    {
        $autoCapture = true;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');

        $apiErrorMessage = 'Some error from api.';
        Mage::register('bolt_api_error', $apiErrorMessage);

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'orderToken' => md5('bolt'),
            'error' => $apiErrorMessage
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithApiError()');
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testGetCartURL()
    {
        $expected = Mage::helper('boltpay')->getMagentoUrl('checkout/cart');

        $result = $this->currentMock->getCartUrl();

        $this->assertEquals($expected, $result);
    }

    public function testGetSelectorsCSS()
    {
        $style = '.test-selector { color: red; }';

        $this->app->getStore()->setConfig('payment/boltpay/additional_css', $style);

        $result = $this->currentMock->getAdditionalCSS();

        $this->assertEquals($style, $result);
    }

    /**
     * Test that additional Js is present
     * @group Block
     */
    public function testGetAdditionalJs()
    {
        $js = 'jQuery("body div").text("Hello, world.")';

        $this->app->getStore()->setConfig('payment/boltpay/additional_js', $js);

        $result = $this->currentMock->getAdditionalJs();

        $this->assertEquals($js, $result);
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testGetSuccessURL()
    {
        $url = 'checkout/onepage/success';
        $this->app->getStore()->setConfig('payment/boltpay/successpage', $url);
        $expected = Mage::helper('boltpay')->getMagentoUrl($url);

        $result = $this->currentMock->getSuccessUrl();

        $this->assertEquals($expected, $result);
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testBuildBoltCheckoutJavascript()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Checkout_Boltpay')
            ->setMethods(array('buildOnCheckCallback', 'buildOnSuccessCallback', 'buildOnCloseCallback'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;

        $autoCapture = true;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);
        Mage::app()->getRequest()->setRouteName('checkout')->setControllerName('cart');

        $cartData = json_encode (
            array(
                'orderToken' => md5('bolt')
            )
        );

        $promiseOfCartData =
            "
            new Promise( 
                function (resolve, reject) {
                    resolve($cartData);
                }
            )
            "
        ;

        $hintData = array();

        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;

        $this->app->getStore()->setConfig('payment/boltpay/check', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_checkout_start', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_shipping_details_complete', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_shipping_options_complete', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_payment_submit', '');
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $this->app->getStore()->setConfig('payment/boltpay/close', '');

        $quote = Mage::getModel('sales/quote');
        $quote->setId(6);

        $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);
        $onSuccessCallback = 'function(transaction, callback) { console.log(test) }';

        $expected = $this->testHelper->buildCartDataJs($checkoutType, $promiseOfCartData, $quote, $jsonHints);

        $this->currentMock
            ->method('buildOnCheckCallback')
            ->will($this->returnValue(''));
        $this->currentMock
            ->method('buildOnSuccessCallback')
            ->will($this->returnValue($onSuccessCallback));
        $this->currentMock
            ->method('buildOnCloseCallback')
            ->will($this->returnValue(''));

        $result = $this->currentMock->buildBoltCheckoutJavascript($checkoutType, $quote, $hintData, $promiseOfCartData);

        $this->assertEquals(preg_replace('/\s/', '', $expected), preg_replace('/\s/', '', $result));
    }

    /**
     * @inheritdoc
     * @group Block
     */
    public function testGetSaveOrderURL()
    {
        $expected = Mage::helper('boltpay')->getMagentoUrl('boltpay/order/save');

        $result = $this->currentMock->getSaveOrderUrl();

        $this->assertEquals($expected, $result);
    }

    /**
     * Sets dependencies used by test for {@see Bolt_Boltpay_Block_Checkout_Boltpay::getPublishableKeyForThisPage()}
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     *
     * @throws Exception if there is a failure retrieving the focus mock's Request object
     */
    private function setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName) {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Checkout_Boltpay')
            ->setMethods(array('getPublishableKey'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;
        $this->currentMock->getRequest()
            ->setRouteName($routeName)
            ->setControllerName($controllerName);

        $mockProxy = new Bolt_Boltpay_Block_Checkout_Boltpay();

        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_multipage', $multiStepKey);
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_onepage', $paymentOnlyKey);

        $this->currentMock->expects($this->exactly(2))->method('getPublishableKey')
            ->withConsecutive(
                [$this->equalTo('multi-page')],
                [$this->equalTo('one-page')]
            )
            ->willReturnOnConsecutiveCalls(
                $mockProxy->getPublishableKey('multi-page'),
                $mockProxy->getPublishableKey('one-page')
            )
        ;
    }

    /**
     * @test
     * that the appropriate product key is returned for a particular page when at least a multi-step key or payment only
     * key has been configured
     *
     * @covers ::getPublishableKeyForThisPage
     * @dataProvider getPublishableKeyForThisPage_withAtLeastOneKeyConfiguredProvider
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     * @param string      $expectedReturnValue      The key that is expected to be returned by the mock
     *
     * @throws Exception if there is a problem in setting up test dependencies
     */
    public function getPublishableKeyForThisPage_withAtLeastOneKeyConfigured($multiStepKey, $paymentOnlyKey, $routeName, $controllerName, $expectedReturnValue)
    {
        $this->setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName);

        $this->helperMock->expects($this->never())->method('logException');
        $this->helperMock->expects($this->never())->method('notifyException');

        $actualMultiStepKey =  $this->app->getStore()->getConfig('payment/boltpay/publishable_key_multipage');
        $actualPaymentOnlyKey = $this->app->getStore()->getConfig('payment/boltpay/publishable_key_onepage');

        $actualReturnedValue = $this->currentMock->getPublishableKeyForThisPage();
        $this->assertTrue($actualMultiStepKey || $actualPaymentOnlyKey);
        $this->assertEquals($multiStepKey, $actualMultiStepKey);
        $this->assertEquals($paymentOnlyKey, $actualPaymentOnlyKey);
        $this->assertEquals($expectedReturnValue, $actualReturnedValue);
    }

    /**
     * Provides data for a multi-step key, or a payment-only key, or for both publishable keys in the context of
     * the standard cart page, a custom cart page, the standard checkout page, a standard product page, and any
     * unexpected page identified by route and controller name for
     * {@see Bolt_Boltpay_Block_Checkout_BoltpayTest::getPublishableKeyForThisPage_withAtLeastOneKeyConfigured()}
     *
     * @return array[] in the format of [$multiStepKey, $paymentOnlyKey, $routeName, $controllerName, $expectedReturnValue]
     */
    public function getPublishableKeyForThisPage_withAtLeastOneKeyConfiguredProvider()
    {
        return [
            "When both keys exist on standard cart page, multi-step is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-standard-cart",
                "paymentOnlyKey" => "payOnly+multi-key-on-standard-cart",
                "routeName" => "checkout",
                "controllerName" => "cart",
                "expectedReturnValue" => "multi+payOnly-key-on-standard-cart"
            ],
            "When only multi-step key exists on standard cart page, empty string payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi-key-on-standard-cart",
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "cart",
                "expectedReturnValue" => "multi-key-on-standard-cart"
            ],
            "When only multi-step key exists on standard cart page, null payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi-key-on-standard-cart",
                "paymentOnlyKey" => null,
                "routeName" => "checkout",
                "controllerName" => "cart",
                "expectedReturnValue" => "multi-key-on-standard-cart"
            ],
            "When only payment-only key exists on standard cart page, null multi-step, payment-only is used" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "payOnly-key-on-standard-cart",
                "routeName" => "checkout",
                "controllerName" => "cart",
                "expectedReturnValue" => "payOnly-key-on-standard-cart"
            ],
            "When both keys exist on custom cart page, multi-step is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-custom-cart",
                "paymentOnlyKey" => "payOnly+multi-key-on-custom-cart",
                "routeName" => "custom",
                "controllerName" => "cart",
                "expectedReturnValue" => "multi+payOnly-key-on-custom-cart"
            ],
            "When only multi-step key exists on custom cart page, null payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi-key-on-custom-cart",
                "paymentOnlyKey" => null,
                "routeName" => "custom",
                "controllerName" => "cart",
                "expectedReturnValue" => "multi-key-on-custom-cart"
            ],
            "When only payment-only key exists on custom cart page, null multi-step, payment-only is used" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "payOnly-key-on-custom-cart",
                "routeName" => "custom",
                "controllerName" => "cart",
                "expectedReturnValue" => "payOnly-key-on-custom-cart"
            ],
            "When both keys exist on standard onepage, payment-only is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-onepage",
                "paymentOnlyKey" => "payOnly+multi-key-on-onepage",
                "routeName" => "checkout",
                "controllerName" => "onepage",
                "expectedReturnValue" => "payOnly+multi-key-on-onepage"
            ],
            "When only payment-only key exists on standard onepage, null multi-step, payment-only is used" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "payOnly-key-on-onepage",
                "routeName" => "checkout",
                "controllerName" => "onepage",
                "expectedReturnValue" => "payOnly-key-on-onepage"
            ],
            "When only multi-step key exists on standard onepage, empty string payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi-key-on-onepage",
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "onepage",
                "expectedReturnValue" => "multi-key-on-onepage"
            ],
            "When both keys exist on unexpected page, payment-only is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-homepage",
                "paymentOnlyKey" => "payOnly+multi-key-on-homepage",
                "routeName" => "cms",
                "controllerName" => "index",
                "expectedReturnValue" => "payOnly+multi-key-on-homepage"
            ],
            "When only payment-only key exists on unexpected page, empty string multi-step, payment-only is used" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "payOnly-key-on-homepage",
                "routeName" => "cms",
                "controllerName" => "index",
                "expectedReturnValue" => "payOnly-key-on-homepage"
            ],
            "When only multi-step key exists on unexpected page, empty string payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-homepage",
                "paymentOnlyKey" => "",
                "routeName" => "cms",
                "controllerName" => "index",
                "expectedReturnValue" => "multi+payOnly-key-on-homepage"
            ],
            "When both keys exist on product page, multi-step is used" =>
            [
                "multiStepKey" => "multi+payOnly-key-on-product",
                "paymentOnlyKey" => "payOnly+multi-key-on-product",
                "routeName" => "catalog",
                "controllerName" => "product",
                "expectedReturnValue" => "multi+payOnly-key-on-product"
            ],
            "When only multi-step key exists on product page, null payment-only, multi-step is used" =>
            [
                "multiStepKey" => "multi-key-on-product",
                "paymentOnlyKey" => null,
                "routeName" => "catalog",
                "controllerName" => "product",
                "expectedReturnValue" => "multi-key-on-product"
            ],
            "When only payment-only key exists on product page, null multi-step, payment-only is used" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "payOnly-key-on-product",
                "routeName" => "catalog",
                "controllerName" => "product",
                "expectedReturnValue" => "payOnly-key-on-product"
            ]
        ];
    }

    /**
     * @test
     * that an exception is thrown for all variants if neither a multi-step nor a payment-only key has been configured
     *
     * @covers ::getPublishableKeyForThisPage
     * @dataProvider getPublishableKeyForThisPage_whenNoKeyIsConfiguredProvider
     * @expectedException Bolt_Boltpay_BoltException
     * @expectedExceptionMessage No publishable key has been configured.
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     *
     * @throws Exception if there is a problem in setting up test dependencies
     */
    public function getPublishableKeyForThisPage_whenNoKeyIsConfigured($multiStepKey, $paymentOnlyKey, $routeName, $controllerName)
    {
        $this->setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName);

        $this->helperMock->expects($this->once())->method('logException');
        $this->helperMock->expects($this->once())->method('notifyException');

        $actualMultiStepKey =  $this->app->getStore()->getConfig('payment/boltpay/publishable_key_multipage');
        $actualPaymentOnlyKey = $this->app->getStore()->getConfig('payment/boltpay/publishable_key_onepage');

        $this->currentMock->getPublishableKeyForThisPage();
        $this->assertFalse($actualMultiStepKey || $actualPaymentOnlyKey, "No key should be configured: multi [$actualMultiStepKey], paymentOnly [$actualPaymentOnlyKey]");
        $this->assertEquals($multiStepKey, $actualMultiStepKey);
        $this->assertEquals($paymentOnlyKey, $actualPaymentOnlyKey);
    }

    /**
     * Provides data with no publishable keys in the context of the standard cart page, a custom cart page,
     * the standard checkout page, a standard product page, and any unexpected page identified by route and controller
     * name for {@see Bolt_Boltpay_Block_Checkout_BoltpayTest::getPublishableKeyForThisPage_whenNoKeyIsConfigured()}
     *
     * @return array[] in the format of [$multiStepKey, $paymentOnlyKey, $routeName, $controllerName]
     */
    public function getPublishableKeyForThisPage_whenNoKeyIsConfiguredProvider()
    {
        return [
            "When no keys are configure, null multi-step and empty string payment-only, on standard cart page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "cart"
            ],
            "When no keys are configure, empty string multi-step and null payment-only, on standard cart page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => null,
                "routeName" => "checkout",
                "controllerName" => "cart"
            ],
            "When no keys are configure, empty strings, on standard cart page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "cart"
            ],
            "When no keys are configure, nulls, on standard cart page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => null,
                "routeName" => "checkout",
                "controllerName" => "cart"
            ],
            "When no keys are configure, null multi-step and empty string payment-only, on custom cart page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "",
                "routeName" => "custom",
                "controllerName" => "cart"
            ],
            "When no keys are configure, empty string multi-step and null payment-only, on custom cart page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => null,
                "routeName" => "custom",
                "controllerName" => "cart"
            ],
            "When no keys are configure, empty strings, on custom cart page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "",
                "routeName" => "custom",
                "controllerName" => "cart"
            ],
            "When no keys are configure, nulls, on custom cart page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => null,
                "routeName" => "custom",
                "controllerName" => "cart"
            ],
            "When no keys are configure, null multi-step and empty string payment-only, on standard onepage" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "onepage"
            ],
            "When no keys are configure, empty string multi-step and null payment-only, on standard onepage" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => null,
                "routeName" => "checkout",
                "controllerName" => "onepage"
            ],
            "When no keys are configure, empty strings, on standard onepage" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "",
                "routeName" => "checkout",
                "controllerName" => "onepage"
            ],
            "When no keys are configure, nulls, on standard onepage" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => null,
                "routeName" => "checkout",
                "controllerName" => "onepage"
            ],
            "When no keys are configure, null multi-step and empty string payment-only, on unexpected page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "",
                "routeName" => "cms",
                "controllerName" => "index"
            ],
            "When no keys are configure, empty string multi-step and null payment-only, on unexpected page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => null,
                "routeName" => "cms",
                "controllerName" => "index"
            ],
            "When no keys are configure, empty strings, on unexpected page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "",
                "routeName" => "cms",
                "controllerName" => "index"
            ],
            "When no keys are configure, nulls, on unexpected page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => null,
                "routeName" => "cms",
                "controllerName" => "index"
            ],
            "When no keys are configure, null multi-step and empty string payment-only, on product page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => "",
                "routeName" => "catalog",
                "controllerName" => "product"
            ],
            "When no keys are configure, empty string multi-step and null payment-only, on product page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => null,
                "routeName" => "catalog",
                "controllerName" => "product"
            ],
            "When no keys are configure, empty strings, on product page" =>
            [
                "multiStepKey" => "",
                "paymentOnlyKey" => "",
                "routeName" => "catalog",
                "controllerName" => "product"
            ],
            "When no keys are configure, nulls, on product page" =>
            [
                "multiStepKey" => null,
                "paymentOnlyKey" => null,
                "routeName" => "catalog",
                "controllerName" => "product"
            ]
        ];
    }

    /**
     * @test
     * @group Block
     * @dataProvider isTestModeData
     */
    public function isTestMode($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/test', $data['test']);
        $result = $this->currentMock->isTestMode();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return boolean[][][]
     */
    public function isTestModeData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => true,
                    'test' => true
                )
            ),
            array(
                'data' => array(
                    'expect' => false,
                    'test' => false
                )
            )
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider getConfigSelectorsData
     */
    public function getConfigSelectors($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/selectors', $data['selectors']);
        $result = $this->currentMock->getConfigSelectors();
        $this->assertInternalType('string', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return string[][][]
     */
    public function getConfigSelectorsData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => '[]',
                    'selectors' => ''
                )
            ),
            array(
                'data' => array(
                    'expect' => '[".btn"]',
                    'selectors' => '.btn'
                )
            ),
            array(
                'data' => array(
                    'expect' => '[".btn"," div.checkout"]',
                    'selectors' => '.btn, div.checkout'
                )
            ),
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider isBoltOnlyPaymentData
     */
    public function isBoltOnlyPayment($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', $data['skip_payment']);
        $result = $this->currentMock->isBoltOnlyPayment();
        $this->assertInternalType('string', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return array[]
     */
    public function isBoltOnlyPaymentData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => '1',
                    'skip_payment' => true
                )
            ),
            array(
                'data' => array(
                    'expect' => '',
                    'skip_payment' => false
                )
            ),
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider isCustomerGroupDisabledData
     */
    public function isCustomerGroupDisabled($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/bolt_disabled_customer_groups', $data['groups']);
        $result = $this->testHelper->callNonPublicFunction($this->currentMock, 'isCustomerGroupDisabled', array($data['customerGroupId']));
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function isCustomerGroupDisabledData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => false,
                    'customerGroupId' => 0,
                    'groups' => ''
                )
            ),
            array(
                'data' => array(
                    'expect' => false,
                    'customerGroupId' => 0,
                    'groups' => '0'
                )
            ),
            array(
                'data' => array(
                    'expect' => false,
                    'customerGroupId' => 1,
                    'groups' => ''
                )
            ),
            array(
                'data' => array(
                    'expect' => true,
                    'customerGroupId' => 1,
                    'groups' => '1,2'
                )
            ),
            array(
                'data' => array(
                    'expect' => false,
                    'customerGroupId' => 0,
                    'groups' => '1,2'
                )
            ),
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider isBoltActiveData
     */
    public function isBoltActive($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/active', $data['active']);
        $result = $this->currentMock->isBoltActive();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return boolean[][][]
     */
    public function isBoltActiveData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => true,
                    'active' => true
                )
            )
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider isEnableMerchantScopedAccountData
     */
    public function isEnableMerchantScopedAccount($data)
    {
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/enable_merchant_scoped_account', $data['enabled']);
        $result = $this->currentMock->isEnableMerchantScopedAccount();
        $this->assertInternalType('string', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return boolean[][][]
     */
    public function isEnableMerchantScopedAccountData()
    {
        return array(
            array(
                'data' => array(
                    'expect' => '1',
                    'enabled' => true
                )
            ),
            array(
                'data' => array(
                    'expect' => '',
                    'enabled' => false
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider isAllowedConnectJsOnCurrentPageData
     */
    public function isAllowedConnectJsOnCurrentPage($data)
    {
        $quote = $this->currentMock->getQuote();
        $quote->setCustomerGroupId($data['customerGroupId']);

        Mage::app()->getRequest()->setRouteName($data['route'])->setControllerName($data['controller']);

        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/bolt_disabled_customer_groups', $data['groups']);
        $this->app->getStore()->setConfig('payment/boltpay/active', $data['active']);
        $this->app->getStore()->setConfig('payment/boltpay/add_button_everywhere', $data['everywhere']);

        $result = $this->currentMock->isAllowedConnectJsOnCurrentPage();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($data['expected'], $result);
    }

    public function isAllowedConnectJsOnCurrentPageData()
    {
        return array(
            array(
                'data' => array(
                    'expected' => true,
                    'active' => true,// Bolt is active
                    'customerGroupId' => 0,//Guest
                    'groups' => '',//all groups are allowed
                    'route' => 'checkout',//checkout
                    'controller' => 'cart',// cart
                    'everywhere' => true// Bolt allow everywhree
                )
            ),
            array(
                'data' => array(
                    'expected' => true,
                    'active' => true,
                    'customerGroupId' => 0,
                    'groups' => '',
                    'route' => 'checkout',
                    'controller' => 'cart',
                    'everywhere' => false
                )
            ),
            array(
                'data' => array(
                    'expected' => true,
                    'active' => true,
                    'customerGroupId' => 0,
                    'groups' => '1,2,3',
                    'route' => 'checkout',
                    'controller' => 'cart',
                    'everywhere' => false
                )
            ),
            array(
                'data' => array(
                    'expected' => false,
                    'active' => true,
                    'customerGroupId' => 1,
                    'groups' => '1,2,3',
                    'route' => 'checkout',
                    'controller' => 'cart',
                    'everywhere' => false
                )
            ),
            array(
                'data' => array(
                    'expected' => true,
                    'active' => true,
                    'customerGroupId' => 1,
                    'groups' => '2,3',
                    'route' => 'checkout',
                    'controller' => 'cart',
                    'everywhere' => false
                )
            ),
            array(
                'data' => array(
                    'expected' => false,
                    'active' => false,
                    'customerGroupId' => 1,
                    'groups' => '2,3',
                    'route' => 'product',
                    'controller' => 'view',
                    'everywhere' => false
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Block
     * @dataProvider getQuoteData
     */
    public function getQuote($data)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quote->setIsActive($data['active']);
        $quote->setIsVirtual($data['virtual']);
        $this->assertEquals($quote, $this->currentMock->getQuote());
    }

    /**
     * Test cases
     * @return boolean[][][]
     */
    public function getQuoteData()
    {
        return array(
            array(
                'data' => array(
                    'active' => 1,
                    'virtual' => 0
                )
            ),
        );
    }

}
