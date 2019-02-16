<?php

require_once('TestHelper.php');

class Bolt_Boltpay_Block_Checkout_BoltpayTest extends PHPUnit_Framework_TestCase
{
    private $app = null;

    private $currentMock;

    /**
     * @var $testHelper Bolt_Boltpay_TestHelper
     */
    private $testHelper = null;

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

        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    /**
     * @inheritdoc
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
            'authcapture' => $autoCapture,
            'orderToken' => md5('bolt'),
        );

        $this->assertEquals($cartData, $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildCartDataWithEmptyTokenField()
    {
        $autoCapture = true;
        $this->app->getStore()->setConfig('payment/boltpay/auto_capture', $autoCapture);

        // Prepare test response object
        $testBoltResponse = new stdClass();

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'authcapture'   => $autoCapture,
            'orderToken'    => '',
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithEmptyTokenField()');
    }

    /**
     * @inheritdoc
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
            'authcapture' => $autoCapture,
            'orderToken' => md5('bolt'),
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithoutAutoCapture()');
    }

    /**
     * @inheritdoc
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
            'authcapture' => $autoCapture,
            'orderToken' => md5('bolt'),
            'error' => $apiErrorMessage
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithApiError()');
    }

    /**
     * @inheritdoc
     */
    public function testGetCartURL()
    {
        $expect = Mage::helper('boltpay/url')->getMagentoUrl('checkout/cart');

        $result = $this->currentMock->getCartUrl();

        $this->assertEquals($expect, $result);
    }

    public function testGetSelectorsCSS()
    {
        $style = '.test-selector { color: red; }';

        $this->app->getStore()->setConfig('payment/boltpay/additional_css', $style);

        $result = $this->currentMock->getAdditionalCSS();

        $this->assertEquals($style, $result);
    }

    /**
     * @inheritdoc
     */
    public function testGetSuccessURL()
    {
        $url = 'checkout/onepage/success';
        $this->app->getStore()->setConfig('payment/boltpay/successpage', $url);
        $expect = Mage::helper('boltpay/url')->getMagentoUrl($url);

        $result = $this->currentMock->getSuccessUrl();

        $this->assertEquals($expect, $result);
    }

    /**
     * @inheritdoc
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
                'authcapture' => $autoCapture,
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
     */
    public function testGetSaveOrderURL()
    {
        $expect = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        $result = $this->currentMock->getSaveOrderUrl();

        $this->assertEquals($expect, $result);
    }
}
