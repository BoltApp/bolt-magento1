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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
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
    use Bolt_Boltpay_Helper_GeneralTrait;
    use Bolt_Boltpay_Helper_LoggerTrait;
    use Bolt_Boltpay_Helper_TransactionTrait;
    use Bolt_Boltpay_Helper_DataDogTrait;
    use Bolt_Boltpay_Helper_GraphQLTrait;

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
                    $checkCustom
                    $onCheckCallback
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
     * @param string $checkoutType
     * @param bool $isVirtualQuote
     *
     * @return string
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function buildOnCheckCallback($checkoutType, $isVirtualQuote = false)
    {
        if ( $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN ) {
            $checkoutTokenUrl = $this->getMagentoUrl("adminhtml/sales_order_create/create/checkoutType/$checkoutType", array(), true);
        } else {
            $checkoutTokenUrl = $this->getMagentoUrl("boltpay/order/create/checkoutType/$checkoutType");
        }

        $ajaxCall = "
            new Ajax.Request('$checkoutTokenUrl', {
                method:'post',
                parameters: '',
                onSuccess: function(response) {
                    if(response.responseJSON.error) {
                        // TODO: Consider informing the user of the error.  This could be handled Bolt-server-side
                        rejectPromise(response.responseJSON.error_messages);
                        location.reload();
                    } else {                                     
                        resolvePromise(response.responseJSON.cart_data);
                    }                   
                },
                onFailure: function(error) { rejectPromise(error); }
            }); 
        ";
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
                        $ajaxCall
                    }
                    ";
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT:
                return
                    "
                    if (!checkout.validate()) return false;
                    $ajaxCall
                    ";
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE:
                return /** @lang JavaScript */ 'if (!boltConfigPDP.validate()) return false;';
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE:
                return /** @lang JavaScript */ <<<JS
if (checkError){
    if (typeof BoltPopup !== 'undefined' && typeof checkError === 'string') {
        BoltPopup.setMessage(checkError);
        BoltPopup.show();
    }
    return false;
}
JS;
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
     * Generates javascript on close callback for Bolt modal based on provided checkout type
     *
     * @param string $closeCustom javascript code to be prepended to result callback
     * @param string $checkoutType to create callback for
     *
     * @return string on-close callback
     *
     * @throws Mage_Core_Model_Store_Exception if unable to get success url
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
                         window.location = '$successUrl'+'$appendChar'+'bolt_transaction_reference='+window.bolt_transaction_reference+'&checkoutType=$checkoutType';
                    }
                    ";
        }

        return $javascript;
    }
}
