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

        $cartData = array(
            'authcapture' => $autoCapture,
            'orderToken' => md5('bolt')
        );

        $hintData = array();

        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;

        $this->app->getStore()->setConfig('payment/boltpay/check', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_checkout_start', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_shipping_details_complete', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_shipping_options_complete', '');
        $this->app->getStore()->setConfig('payment/boltpay/on_payment_submit', '');
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $this->app->getStore()->setConfig('payment/boltpay/close', '');

        $immutableQuoteID = 6;

        $jsonCart = json_encode($cartData);
        $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);
        $onSuccessCallback = 'function(transaction, callback) { console.log(test) }';

        $expected = $this->testHelper->buildCartDataJs($jsonCart, $immutableQuoteID, $jsonHints, array(
            'onSuccessCallback' => $onSuccessCallback
        ));

        $this->currentMock
            ->method('buildOnCheckCallback')
            ->will($this->returnValue(''));
        $this->currentMock
            ->method('buildOnSuccessCallback')
            ->will($this->returnValue($onSuccessCallback));
        $this->currentMock
            ->method('buildOnCloseCallback')
            ->will($this->returnValue(''));

        $result = $this->currentMock->buildBoltCheckoutJavascript($checkoutType, $immutableQuoteID, $hintData, $cartData);

        $this->assertEquals($expected, $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnCheckCallback()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $result = $this->currentMock->buildOnCheckCallback($checkoutType);

        $this->assertEquals('', $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnCheckCallbackIfAdminArea()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $checkCallback = "
             if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                var bolt_hidden = document.getElementById('boltpay_payment_button');
                bolt_hidden.classList.remove('required-entry');

                var is_valid = true;

                if (!editForm.validate()) {
                    is_valid = false;
                } else {
                    var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                    if (typeof shipping_method === 'undefined') {
                        alert('Please select a shipping method.');
                        is_valid = false;
                    }
                }

                bolt_hidden.classList.add('required-entry');
                return is_valid;
            }
        ";

        $result = $this->currentMock->buildOnCheckCallback($checkoutType);

        $this->assertEquals(preg_replace('/\s/', '', $checkCallback), preg_replace('/\s/', '', $result));
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnSuccessCallback()
    {
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $successCustom = "console.log('test')";
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;

        $onSuccessCallback = "function(transaction, callback) {
                new Ajax.Request(
                    '$saveOrderUrl',
                    {
                        method:'post',
                        onSuccess:
                            function() {
                                $successCustom
                                order_completed = true;
                                callback();
                            },
                        parameters: 'reference='+transaction.reference
                    }
                );
            }";

        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);

        $this->assertEquals($onSuccessCallback, $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnSuccessCallbackIfAdminArea()
    {
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $successCustom = "console.log('test')";
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;

        $onSuccessCallback = "function(transaction, callback) {
                $successCustom

                var input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', 'bolt_reference');
                input.setAttribute('value', transaction.reference);
                document.getElementById('edit_form').appendChild(input);

                // order and order.submit should exist for admin
                if ((typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                    order_completed = true;
                    callback();
                }
            }";

        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);

        $this->assertEquals($onSuccessCallback, $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnCloseCallback()
    {
        $successUrl = Mage::helper('boltpay/url')->getMagentoUrl('checkout/onepage/success');
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE;
        $closeCustom = '';

        $expect = "
            if (order_completed) {
                   location.href = '$successUrl';
            }
        ";

        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);

        $this->assertEquals(preg_replace('/\s/', '', $expect), preg_replace('/\s/', '', $result));
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnCloseCallbackIfAdminArea()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $closeCustom = '';

        $expect = "
             if (order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                $closeCustom
                var bolt_hidden = document.getElementById('boltpay_payment_button');
                bolt_hidden.classList.remove('required-entry');
                order.submit();
             }
        ";
        
        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);

        $this->assertEquals(preg_replace('/\s/', '', $expect), preg_replace('/\s/', '', $result));
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
