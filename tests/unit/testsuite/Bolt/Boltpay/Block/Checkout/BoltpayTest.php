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
        
    }

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
     * @group inProgress
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
}
