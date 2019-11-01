<?php

require_once('TestHelper.php');

class Bolt_Boltpay_Helper_DataTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    private $app = null;

    /**
     * @var $dataHelper Bolt_Boltpay_Helper_Data
     */
    private $dataHelper = null;

    /**
     * @var $testHelper Bolt_Boltpay_TestHelper
     */
    private $testHelper = null;

    /**
     * @var Bolt_Boltpay_Helper_Data
     */
    private $currentMock;

    /**
     * Generate dummy products for testing purposes
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->dataHelper = Mage::helper('boltpay');
        $this->testHelper = new Bolt_Boltpay_TestHelper();

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('isAdminAndUseJsInAdmin'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsFalseIfDisabled()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 0);
        $quote = $this->testHelper->getCheckoutQuote();

        $this->assertFalse($this->dataHelper->canUseBolt($quote));
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsTrueIfSkipPaymentIsEnabled()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->testHelper->createCheckout('guest');
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $quote = $cart->getQuote();

        $result =  $this->dataHelper->canUseBolt($quote);
        $this->assertTrue($result);
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsFalseIfBillingCountryNotWhitelisted()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertFalse($this->dataHelper->canUseBolt($quote));
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsTrueIfBillingCountryIsWhitelisted()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,US,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsTrueIfSkipPaymentEvenIfBillingCountryIsNotWhitelisted()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 1);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    /**
     * @test
     * @group Helper
     */
    public function testCanUseBoltReturnsTrueIfAllowSpecificIsFalse()
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', 1);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', 0);
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();

        $this->assertTrue($this->dataHelper->canUseBolt($quote));
    }

    /**
     * @group Helper
     * @inheritdoc
     */
    public function testBuildOnCheckCallback()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $quote = Mage::getModel('sales/quote');
        $quote->setId(6);

        $result = $this->currentMock->buildOnCheckCallback($checkoutType, $quote);

        $this->assertEquals('', $result);
    }

    /**
     * @group Helper
     * @inheritdoc
     */
    public function testBuildOnCheckCallbackIfAdminArea()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $isVirtualQuote = false;
        $checkCallback = "
                    if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
        
                        var is_valid = true;
        
                        if (!editForm.validate()) {
                            return false;
                        } ". ($isVirtualQuote ? "" : " else {
                            var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                            if (typeof shipping_method === 'undefined') {
                                alert('".Mage::helper('boltpay')->__('Please select a shipping method.')."');
                                return false;
                            }
                        } "). "
        
                        bolt_hidden.classList.add('required-entry');
                    }
                    ";

        $result = $this->currentMock->buildOnCheckCallback($checkoutType, $isVirtualQuote);

        $this->assertEquals(preg_replace('/\s/', '', $checkCallback), preg_replace('/\s/', '', $result));
    }

    /**
     * @inheritdoc
     */
    public function testBuildOnSuccessCallback()
    {
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $successCustom = "console.log('test')";
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $saveOrderUrl = Mage::helper('boltpay')->getMagentoUrl("boltpay/order/save/checkoutType/$checkoutType");

        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);

        $this->assertStringStartsWith('function', $result);
        $this->assertContains($successCustom, $result);
    }

    /**
     * @group Helper
     * @inheritdoc
     */
    public function testBuildOnSuccessCallbackIfAdminArea()
    {
        $this->app->getStore()->setConfig('payment/boltpay/success', '');
        $successCustom = "console.log('test')";
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;

        $expected = "function(transaction, callback) {
                $successCustom

                var input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', 'bolt_reference');
                input.setAttribute('value', transaction.reference);
                document.getElementById('edit_form').appendChild(input);

                // order and order.submit should exist for admin
                if ((typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                    window.order_completed = true;
                    callback();
                }
            }";

        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);

        $this->assertEquals(preg_replace('/\s/', '', $expected), preg_replace('/\s/', '', $result));

    }

    /**
     * @group Helper
     * @inheritdoc
     */
    public function testBuildOnCloseCallback()
    {
        $successUrl = Mage::helper('boltpay')->getMagentoUrl('checkout/onepage/success');
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE;
        $closeCustom = 'console.log("test");';

        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);

        $this->assertContains($closeCustom, $result);
        $this->assertNotEquals($closeCustom, $result);
    }

    /**
     * @group Helper
     * @inheritdoc
     */
    public function testBuildOnCloseCallbackIfAdminArea()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $closeCustom = '';

        $expected = "
             if (window.order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                $closeCustom
                var bolt_hidden = document.getElementById('boltpay_payment_button');
                bolt_hidden.classList.remove('required-entry');
                order.submit();
             }
        ";

        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);

        $this->assertEquals(preg_replace('/\s/', '', $expected), preg_replace('/\s/', '', $result));
    }

    /**
     * @test
     * @group Helper
     * @group inProgress
     * @dataProvider arrayColumnData
     */
    public function arrayColumn($data)
    {
        $result = $this->currentMock->arrayColumn($data['array'], $data['column']);
        $this->assertInternalType('array', $result);
        $this->assertEquals($data['expect'], $result);
    }

    /**
     * Test cases
     * @return string[][][]|string[][][][]|array[][][]|number[][][][][]|string[][][][][]|string[][][][][][]
     */
    public function arrayColumnData()
    {
        $array = array(
            array(
                'id' => 456,
                'first_name' => 'John',
                'last_name' => 'Lennon',
                'cart' => array(
                    'guitar',
                    'vocal'
                )
            ),
            array(
                'id' => 7647654,
                'first_name' => 'Paul',
                'last_name' => 'Mccartney',
                'cart' => array(
                    'bass guitar',
                    'vocal'
                )
            ),
            array(
                'id' => 4756,
                'first_name' => 'George',
                'last_name' => 'Harrison',
                'cart' => array(
                   'guitar'
                )
            ),
            array(
                'id' => 76474,
                'first_name' => 'Ringo',
                'last_name' => 'Starr',
                'cart' => array(
                    'drums'
                )
            ),
            
        );
        return array(
            array(
                'data' => array(
                    'expect' => array('John', 'Paul', 'George', 'Ringo'),
                    'array' => $array,
                    'column' => 'first_name'
                )
            ),
            array(
                'data' => array(
                    'expect' => array('Lennon', 'Mccartney', 'Harrison', 'Starr'),
                    'array' => $array,
                    'column' => 'last_name'
                )
            ),
            array(
                'data' => array(
                    'expect' => array(
                        array(
                            'guitar',
                            'vocal'
                        ),
                        array(
                            'bass guitar',
                            'vocal'
                        ),
                        array(
                            'guitar'
                        ),
                        array(
                            'drums'
                        )
                    ),
                    'array' => $array,
                    'column' => 'cart'
                )
            ),
            
        );
    }

}