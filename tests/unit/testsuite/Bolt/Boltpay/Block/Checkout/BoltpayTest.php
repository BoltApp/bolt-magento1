<?php

class Bolt_Boltpay_Block_Checkout_BoltpayTest extends PHPUnit_Framework_TestCase
{
    private $app = null;

    private $currentMock;

    public function setUp()
    {
        $this->app = Mage::app('default');

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Checkout_Boltpay')
            ->setMethods(array('isAdminAndUseJsInAdmin', 'getUrl'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;

        $this->session  = Mage::getSingleton('customer/session');
        $this->quote    = Mage::getSingleton('checkout/session')->getQuote();
    }

    public function testIsEnableMerchantScopedAccount()
    {

    }

    public function testIsAllowedReplaceScriptOnCurrentPage()
    {

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
        Mage::register('api_error', $apiErrorMessage);

        $result = $this->currentMock->buildCartData($testBoltResponse);

        $cartData = array(
            'authcapture' => $autoCapture,
            'orderToken' => md5('bolt'),
            'error' => $apiErrorMessage
        );

        $this->assertEquals($cartData, $result, 'Something wrong with testBuildCartDataWithApiError()');
    }

    public function testIsBoltOnlyPayment()
    {

    }

    public function testGetPublishableKey()
    {

    }

    public function testGetAdditionalCSS()
    {

    }

    public function testGetIpAddress()
    {

    }

    public function testGetReservedUserId()
    {

    }

    public function testUrl_get_contents()
    {

    }

    public function testCanUseBolt()
    {

    }

    public function testIsBoltActive()
    {

    }

    public function testGetCartDataJs()
    {

    }

    public function testGetLocationEstimate()
    {

    }

    public function testGetQuote()
    {

    }

    public function testIsAllowedConnectJsOnCurrentPage()
    {

    }

    public function testGetTheme()
    {

    }

    public function testGetConfigSelectors()
    {

    }

    public function testGetPublishableKeyForRoute()
    {

    }

    public function testIsTestMode()
    {

    }

    public function testGetCartURL()
    {

    }

    public function testGetSelectorsCSS()
    {

    }

    public function testGetSuccessURL()
    {

    }

    /**
     * @inheritdoc
     */
    public function testBuildBoltCheckoutJavascript()
    {

    }

    public function testGetTrackJsUrl()
    {

    }

    public function testGetCssSuffix()
    {

    }

    public function testGetSaveOrderURL()
    {

    }
}
