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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Block_Catalog_Product_Boltpay
 *
 * This block is used in boltpay/catalog/product/configure_checkout.phtml and boltpay/catalog/product/button.phtml templates
 *
 * This is defined in boltpay.xml config file
 *
 * The purpose is to add the Bolt to the Product Page,
 * create the order on Bolt side through the javascript BoltCheckout.configureProductCheckout process.
 *
 */
class Bolt_Boltpay_Block_Catalog_Product_Boltpay extends Mage_Core_Block_Template
{
    const CHECKOUT_TYPE_ADMIN       = 'admin';
    const CHECKOUT_TYPE_MULTI_PAGE  = 'multi-page';
    const CHECKOUT_TYPE_ONE_PAGE    = 'one-page';

    const CHECKOUT_TYPE_FIRECHECKOUT = 'firecheckout';

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJsForProductPage($checkoutType = self::CHECKOUT_TYPE_MULTI_PAGE)
    {
        try {
//            $hintData = $this->getAddressHints($sessionQuote, $checkoutType);
            $hintData = array( "prefill" => [] );

            // TODO: get store currency from config.
//            $currency = $immutableQuote->getQuoteCurrencyCode();
            $currency = 'USD';

            $productCheckoutCartItem = [];

            $_product = Mage::registry('current_product');
            if (!$_product) {
                throw new Exception('Bolt: Cannot find product info');
            }

            $productCheckoutCartItem[] = [
                'reference' => $_product->getId(),
                'price'     => $_product->getPrice(),
                'quantity'  => 1, // TODO: add determination the price by qty field
                'image'     => $_product->getImageUrl(),
                'name'  => $_product->getName(),
            ];
            $totalAmount = $_product->getPrice();

            $productCheckoutCart = [
                'currency' => $currency,
                'items' => $productCheckoutCartItem,
                'total' => $totalAmount,
            ];

            return $this->configureProductCheckout($checkoutType, $hintData, $productCheckoutCart);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * @param      $checkoutType
     * @param null $hintData
     * @param      $productCheckoutCart
     * @return string
     */
    public function configureProductCheckout($checkoutType, $hintData = null, $productCheckoutCart)
    {
        /* @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        $jsonCart = json_encode($productCheckoutCart);
//        $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);

        //////////////////////////////////////////////////////
        // Collect the event Javascripts
        // We execute these events as early as possible, typically
        // before Bolt defined event JS to give merchants the
        // opportunity to do full overrides
        //////////////////////////////////////////////////////
        $checkCustom = $boltHelper->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckoutStartCustom = $boltHelper->getPaymentBoltpayConfig('on_checkout_start', $checkoutType);
        $onShippingDetailsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_details_complete', $checkoutType);
        $onShippingOptionsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_options_complete', $checkoutType);
        $onPaymentSubmitCustom = $boltHelper->getPaymentBoltpayConfig('on_payment_submit', $checkoutType);
        $successCustom = $boltHelper->getPaymentBoltpayConfig('success', $checkoutType);
        $closeCustom = $boltHelper->getPaymentBoltpayConfig('close', $checkoutType);

        $onCheckCallback = '';
        $onSuccessCallback = $this->buildOnSuccessCallback($checkoutType, $successCustom);
        $onCloseCallback = $this->buildOnCloseCallback($checkoutType, $closeCustom);

        return ("
            var jsonProductCart = $jsonCart;
            var jsonProductHints = null;

            var productPageCheckoutSelector = '". $this->escapeHtml($this->getProductPageCheckoutSelector())."';
            var order_completed = false;

            BoltCheckout.configureProductCheckout(
                jsonProductCart,
                jsonProductHints,
                {
                  check: function() {
                    $checkCustom
                    $onCheckCallback
                    return true;
                  },
                  
                  onCheckoutStart: function() {
                    // This function is called after the checkout form is presented to the user.
                    $onCheckoutStartCustom
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
                },
                { checkoutButtonClassName: 'bolt-product-checkout-button' }
        );");
    }

    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($checkoutType, $successCustom = '')
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        return ($checkoutType === self::CHECKOUT_TYPE_ADMIN) ?
            "function(transaction, callback) {
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
            }"
            : "function(transaction, callback) {
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
    }

    /**
     * @param $closeCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnCloseCallback($checkoutType, $closeCustom = '')
    {
        $successUrl = Mage::helper('boltpay/url')->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $javascript = "";
        switch ($checkoutType) {
            case self::CHECKOUT_TYPE_ADMIN:
                return
                    "
                    if (order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                        $closeCustom
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
                        order.submit();
                    }
                    ";
            case self::CHECKOUT_TYPE_FIRECHECKOUT:
                return "
                    isFireCheckoutFormValid = false;
                    initBoltButtons();
                    ";
            default:
                return $javascript.
                    "
                    if (order_completed) {
                        location.href = '$successUrl';
                    }
                    ";
        }
    }

    /**
     * @param $checkoutType
     * @return bool
     */
    protected function isAdminAndUseJsInAdmin($checkoutType)
    {
        return ($checkoutType === self::CHECKOUT_TYPE_ADMIN) && !Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin');
    }

    /**
     * Return the session object depending of checkout type.
     *
     * @param $checkoutType
     * @return Mage_Checkout_Model_Session
     */
    protected function getSessionObject($checkoutType)
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get session quote regarding the checkout type
     *
     * @param $checkoutType
     * @return Mage_Sales_Model_Quote
     */
    protected function getSessionQuote($checkoutType)
    {
        // Admin and Store front use different session objects.  We get the appropriate one here.
        $session = $this->getSessionObject($checkoutType);

        return $session->getQuote();
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return bool
     */
    public function isBoltActive()
    {
        return $this->helper('boltpay')->isBoltPayActive();
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout()
    {
        return ($this->isBoltActive() && $this->helper('boltpay')->isEnabledProductPageCheckout());
    }

    /**
     * @return string
     */
    public function getProductPageCheckoutSelector()
    {
        return $this->helper('boltpay')->getProductPageCheckoutSelector();
    }
}
