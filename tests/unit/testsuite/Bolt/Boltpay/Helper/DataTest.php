<?php

require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_Data
 */
class Bolt_Boltpay_Helper_DataTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data
     */
    private $currentMock;

    /**
     * @throws Mage_Core_Model_Store_Exception
     */
    public function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods()
            ->getMock();
    }

    /**
     * @test
     *
     * @covers ::buildOnCheckCallback
     */
    public function buildOnCheckCallback_whenCheckoutTypeIsMultiPage_returnsEmptyString()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $result = $this->currentMock->buildOnCheckCallback($checkoutType);
        $this->assertEquals('', $result);
    }

    /**
     * @test
     *
     * @covers ::buildOnCheckCallback
     *
     * @dataProvider buildOnCheckCallback_whenCheckoutTypeIsAdmin_returnsCorrectJsProvider
     */
    public function buildOnCheckCallback_whenCheckoutTypeIsAdmin_returnsCorrectJs($isVirtualQuote)
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $checkCallback = "
            if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                var bolt_hidden = document.getElementById('boltpay_payment_button');
                bolt_hidden.classList.remove('required-entry');

                var is_valid = true;

                if (!editForm.validate()) {
                    return false;
                } " . ($isVirtualQuote ? "" : " else {
                    var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                    if (typeof shipping_method === 'undefined') {
                        alert('" . Mage::helper('boltpay')->__('Please select a shipping method.') . "');
                        return false;
                    }
                } ") . "

                bolt_hidden.classList.add('required-entry');
            }
        ";
        $result = $this->currentMock->buildOnCheckCallback($checkoutType, $isVirtualQuote);
        $this->assertEquals(
            preg_replace('/\s/', '', $checkCallback),
            preg_replace('/\s/', '', $result)
        );
    }

    /**
     * Data provider for {@see buildOnCheckCallback_whenCheckoutTypeIsAdmin_returnsCorrectJs}
     *
     * @return array containing if quote is virtual
     */
    public function buildOnCheckCallback_whenCheckoutTypeIsAdmin_returnsCorrectJsProvider()
    {
        return array(
            array('isVirtualQuote' => true),
            array('isVirtualQuote' => false),
        );
    }

    /**
     * @test
     * that buildOnCheckCallback returns the correct string whether isVirtual quote is true or not
     *
     * @covers ::buildOnCheckCallback
     */
    public function buildOnCheckCallback_whenCheckoutTypeIsFireCheckout_returnsCorrectJs()
    {
        $expected = 'if (!checkout.validate()) return false;';
        $trueResult = $this->currentMock->buildOnCheckCallback(
            Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT,
            true
        );
        $falseResult = $this->currentMock->buildOnCheckCallback(
            Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT,
            false
        );
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $trueResult)
        );
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $falseResult)
        );
    }

    /**
     * @test
     * that buildOnCheckCallback returns quantity check for product page checkout regardless if quote is virtual or not
     *
     * @covers ::buildOnCheckCallback
     */
    public function buildOnCheckCallback_whenCheckoutTypeIsProductPage_returnsBoltConfigPDPValidation()
    {
        $expected = 'if (!boltConfigPDP.validate()) return false;';
        $trueResult = $this->currentMock->buildOnCheckCallback(
            Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE,
            true
        );
        $falseResult = $this->currentMock->buildOnCheckCallback(
            Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE,
            false
        );
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $trueResult)
        );
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $falseResult)
        );
    }

    /**
     * @test
     *
     * @covers ::buildOnSuccessCallback
     */
    public function buildOnSuccessCallback_whenCheckoutTypeIsNotAdmin_returnsCorrectJs()
    {
        $successCustom = "console.log('test');";
        $expected = "function(transaction, callback) {
            window.bolt_transaction_reference = transaction.reference;
            $successCustom
            callback();
        }";
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $result)
        );
    }

    /**
     * @test
     *
     * @covers ::buildOnSuccessCallback
     */
    public function buildOnSuccessCallback_whenCheckoutTypeIsAdmin_returnsCorrectJs()
    {
        $successCustom = "console.log('test');";
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
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $result = $this->currentMock->buildOnSuccessCallback($successCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s+/', '', $expected),
            preg_replace('/\s+/', '', $result)
        );
    }

    /**
     * @test
     *
     * @covers ::buildOnCloseCallback
     */
    public function buildOnCloseCallback_whenCheckoutTypeIsAdmin_returnsCorrectJs()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN;
        $closeCustom = "console.log('test');";
        $expected = "
             if (window.order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                $closeCustom
                var bolt_hidden = document.getElementById('boltpay_payment_button');
                bolt_hidden.classList.remove('required-entry');
                order.submit();
             }
        ";
        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s/', '', $expected),
            preg_replace('/\s/', '', $result)
        );
    }

    /**
     * @test
     *
     * @covers ::buildOnCloseCallback
     */
    public function buildOnCloseCallback_whenCheckoutTypeIsProductPage_returnsEmptyString()
    {
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE;
        $closeCustom = "console.log('test');";
        $expected = "";
        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s/', '', $expected),
            preg_replace('/\s/', '', $result)
        );
    }

    /**
     * @test
     *
     * @covers ::buildOnCloseCallback
     *
     * @dataProvider buildOnCloseCallback_whenCheckoutTypeIsNotAdminOrProductPage_returnsCorrectJsProvider
     */
    public function buildOnCloseCallback_whenCheckoutTypeIsFirecheckout_returnsCorrectJs($successUrl, $appendChar)
    {
        $urlMock = $this->getMockBuilder('Mage_Core_Model_Url')
            ->setMethods(array('sessionUrlVar'))
            ->getMock();

        $urlMock->expects($this->once())->method('sessionUrlVar')
            ->willReturn($successUrl);

        TestHelper::stubModel('core/url', $urlMock);

        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT;
        $closeCustom = "console.log('test');";
        $expected = "
            $closeCustom
            isFireCheckoutFormValid = false;
            initBoltButtons();
            if (window.bolt_transaction_reference) {
                 window.location = '$successUrl'+'$appendChar'+'bolt_transaction_reference='+window.bolt_transaction_reference;
            }
        ";
        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s/', '', $expected),
            preg_replace('/\s/', '', $result)
        );

        TestHelper::restoreOriginals();
    }

    /**
     * @test
     *
     * @covers ::buildOnCloseCallback
     *
     * @dataProvider buildOnCloseCallback_whenCheckoutTypeIsNotAdminOrProductPage_returnsCorrectJsProvider
     */
    public function buildOnCloseCallback_whenCheckoutTypeIsMultiPage_returnsCorrectJs($successUrl, $appendChar)
    {
        $urlMock = $this->getMockBuilder('Mage_Core_Model_Url')
            ->setMethods(array('sessionUrlVar'))
            ->getMock();

        $urlMock->expects($this->once())->method('sessionUrlVar')
            ->willReturn($successUrl);

        TestHelper::stubModel('core/url', $urlMock);

        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;
        $closeCustom = "console.log('test');";
        $expected = "
            $closeCustom
            if (window.bolt_transaction_reference) {
                 window.location = '$successUrl'+'$appendChar'+'bolt_transaction_reference='+window.bolt_transaction_reference;
            }
        ";
        $result = $this->currentMock->buildOnCloseCallback($closeCustom, $checkoutType);
        $this->assertEquals(
            preg_replace('/\s/', '', $expected),
            preg_replace('/\s/', '', $result)
        );

        TestHelper::restoreOriginals();
    }

    /**
     * Data provider for {@see buildOnCloseCallback_whenCheckoutTypeIsFirecheckout_returnsCorrectJs}
     *               and {@see buildOnCloseCallback_whenCheckoutTypeIsMultiPage_returnsCorrectJs}
     *
     * @return array
     */
    public function buildOnCloseCallback_whenCheckoutTypeIsNotAdminOrProductPage_returnsCorrectJsProvider()
    {
        return array(
            array('successUrl' => 'http://test.com/success', 'appendChar' => '?'),
            array('successUrl' => 'http://test.com/success?blah=1', 'appendChar' => '&'),
        );
    }

    /**
     * @test
     *
     * @covers ::getBoltCallbacks
     */
    public function getBoltCallbacks_returnsCorrectJs()
    {
        TestHelper::stubConfigValue('payment/boltpay/check', '');
        TestHelper::stubConfigValue('payment/boltpay/on_checkout_start', '');
        TestHelper::stubConfigValue('payment/boltpay/on_email_enter', '');
        TestHelper::stubConfigValue('payment/boltpay/on_shipping_details_complete', '');
        TestHelper::stubConfigValue('payment/boltpay/on_shipping_options_complete', '');
        TestHelper::stubConfigValue('payment/boltpay/on_payment_submit', '');
        TestHelper::stubConfigValue('payment/boltpay/success', '');
        TestHelper::stubConfigValue('payment/boltpay/close', '');

        $expected = "{
          check: function() {
            if (do_checks++) {
                if (!boltConfigPDP.validate()) return false;
            }
            return true;
          },
          onCheckoutStart: function() {
            // This function is called after the checkout form is presented to the user.
          },
          onEmailEnter: function(email) {
            // This function is called after the user enters their email address.
          },
          onShippingDetailsComplete: function() {
            // This function is called when the user proceeds to the shipping options page.
            // This is applicable only to multi-step checkout.
          },
          onShippingOptionsComplete: function() {
            // This function is called when the user proceeds to the payment details page.
            // This is applicable only to multi-step checkout.
          },
          onPaymentSubmit: function() {
            // This function is called after the user clicks the pay button.
          },
          success: function(transaction, callback) {
            window.bolt_transaction_reference = transaction.reference;
            callback();
          },
          close: function() {
          }
        }";
        $result = $this->currentMock->getBoltCallbacks(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE);
        $this->assertEquals(
            preg_replace('/\s/', '', $expected),
            preg_replace('/\s/', '', $result)
        );

        TestHelper::restoreOriginals();
    }
}
