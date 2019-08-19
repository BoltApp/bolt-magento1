<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Helper_Data
 *
 * Base Magento Bolt Helper class
 *
 * TODO: Move the locally defined methods back to the Block, where they are most appropriately defined
 */
class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    use Bolt_Boltpay_Helper_ApiTrait;
    use Bolt_Boltpay_Helper_GeneralTrait;
    use Bolt_Boltpay_Helper_LoggerTrait;
    use Bolt_Boltpay_Helper_TransactionTrait;
    use Bolt_Boltpay_Helper_DataDogTrait;

    /**
     * Collect Bolt callbacks for js config.
     *
     * @param string $checkoutType
     * @param bool $isVirtualQuote
     *
     * @return string
     */
    public function getBoltCallbacks($checkoutType, $isVirtualQuote = false)
    {
        //////////////////////////////////////////////////////
        // Collect the event Javascripts
        // We execute these events as early as possible, typically
        // before Bolt defined event JS to give merchants the
        // opportunity to do full overrides
        //////////////////////////////////////////////////////

        $checkCustom = $this->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckoutStartCustom = $this->getPaymentBoltpayConfig('on_checkout_start', $checkoutType);
        $onEmailEnterCustom = $this->getPaymentBoltpayConfig('on_email_enter', $checkoutType);
        $onShippingDetailsCompleteCustom = $this->getPaymentBoltpayConfig('on_shipping_details_complete', $checkoutType);
        $onShippingOptionsCompleteCustom = $this->getPaymentBoltpayConfig('on_shipping_options_complete', $checkoutType);
        $onPaymentSubmitCustom = $this->getPaymentBoltpayConfig('on_payment_submit', $checkoutType);
        $successCustom = $this->getPaymentBoltpayConfig('success', $checkoutType);
        $closeCustom = $this->getPaymentBoltpayConfig('close', $checkoutType);

        $onCheckCallback = $this->buildOnCheckCallback($checkoutType, $isVirtualQuote);
        $onSuccessCallback = $this->buildOnSuccessCallback($successCustom, $checkoutType);
        $onCloseCallback = $this->buildOnCloseCallback($closeCustom, $checkoutType);

        return "{
                  check: function() {
                    if (do_checks++) {
                        $checkCustom
                        $onCheckCallback
                    }
                    return true;
                  },
                  onCheckoutStart: function() {
                    // This function is called after the checkout form is presented to the user.
                    $onCheckoutStartCustom
                  },
                  onEmailEnter: function(email) {
                    // This function is called after the user enters their email address.
                    $onEmailEnterCustom
                  },
                  onShippingDetailsComplete: function() {
                    // This function is called when the user proceeds to the shipping options page.
                    // This is applicable only to multi-step checkout.
                    $onShippingDetailsCompleteCustom
                  },
                  onShippingOptionsComplete: function() {
                    // This function is called when the user proceeds to the payment details page.
                    // This is applicable only to multi-step checkout.
                    $onShippingOptionsCompleteCustom
                  },
                  onPaymentSubmit: function() {
                    // This function is called after the user clicks the pay button.
                    $onPaymentSubmitCustom
                  },
                  success: $onSuccessCallback,
                  close: function() {
                     $onCloseCallback
                  }
                }";
    }

    /**
     * @param      $checkoutType
     * @param bool $isVirtualQuote
     *
     * @return string
     */
    public function buildOnCheckCallback($checkoutType, $isVirtualQuote = false)
    {
        switch ($checkoutType) {
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN:
                return
                    "
                    if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
        
                        var is_valid = true;
        
                        if (!editForm.validate()) {
                            return false;
                        } ". ($isVirtualQuote ? "" : " else {
                            var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                            if (typeof shipping_method === 'undefined') {
                                alert('".$this->__('Please select a shipping method.')."');
                                return false;
                            }
                        } "). "
        
                        bolt_hidden.classList.add('required-entry');
                    }
                    ";
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT:
                return
                    "
                    if (!checkout.validate()) return false;
                    ";
            default:
                return '';
        }
    }

    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($successCustom, $checkoutType)
    {
        $saveOrderUrl = $this->getMagentoUrl("boltpay/order/save/checkoutType/$checkoutType");

        return ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) ?
            "function(transaction, callback) {
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
            }"
            : "function(transaction, callback) {
                window.bolt_transaction_reference = transaction.reference;
                $successCustom
                callback();
            }";
    }

    /**
     * @param $closeCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnCloseCallback($closeCustom, $checkoutType)
    {
        // For frontend URLs, we want to "session id process" the URL to get the 
        // final format URL which may or may not contain the __SID=(S|U) parameter
        $successUrl = Mage::getModel('core/url')->sessionUrlVar(
            $this->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'))
        );
        $javascript = "";
        switch ($checkoutType) {
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN:
                $javascript .=
                    "
                    if (window.order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                        $closeCustom
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
                        order.submit();
                    }
                    ";
                break;
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE:
                $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
                $successUrl = $this->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'), array('checkoutType' => $checkoutType, 'session_quote_id' => $quoteId));
                break;
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT:
                $javascript .=
                    "
                    $closeCustom
                    isFireCheckoutFormValid = false;
                    initBoltButtons();
                    ";
                $closeCustom = '';
                // fall-through
            default:
                // Backup success page forwarding for Firecheckout, Onepage Checkout, Multi-Checkout/Mini-Cart
                // Generally all checkouts should fall-through to this
                $appendChar = (strpos($successUrl, '?') === false) ? '?' : '&';

                $javascript .=
                    "
                    $closeCustom
                    if (window.bolt_transaction_reference) {
                         window.location = '$successUrl'+'$appendChar'+'bolt_transaction_reference='+window.bolt_transaction_reference;
                    }
                    ";
        }

        return $javascript;
    }
}
