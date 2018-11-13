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
    const CHECKOUT_TYPE_MULTI_PAGE  = 'multi-page';

    /**
     * Initiates sets up BoltCheckout. with generated data.
     * In BoltCheckout.configureProductCheckout success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJsForProductPage()
    {
        try {
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

            return $this->configureProductCheckout(self::CHECKOUT_TYPE_MULTI_PAGE, $productCheckoutCart);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * @param      $checkoutType
     * @param      $productCheckoutCart
     *
     * @return string
     */
    public function configureProductCheckout($checkoutType, $productCheckoutCart)
    {
        /* @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        $jsonCart = json_encode($productCheckoutCart);

        //////////////////////////////////////////////////////
        // Collect the javascript events.
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
        $onSuccessCallback = $this->buildOnSuccessCallback($successCustom);
        $onCloseCallback = $this->buildOnCloseCallback($closeCustom);

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
     * @return string
     */
    public function buildOnSuccessCallback($successCustom = '')
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        return "function(transaction, callback) {
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
     * @return string
     */
    public function buildOnCloseCallback($closeCustom = '')
    {
        $successUrl = Mage::helper('boltpay/url')->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $javascript = $closeCustom;

        return $javascript .
            "if (order_completed) {
                location.href = '$successUrl';
            }
            ";
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
