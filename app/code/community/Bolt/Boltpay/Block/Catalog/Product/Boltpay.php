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
     * Initiates sets up BoltCheckout config.
     *
     * @return string
     */
    public function getCartDataJsForProductPage()
    {
        try {
            $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

            $productCheckoutCartItem = [];

            $_product = Mage::registry('current_product');
            if (!$_product) {
                $msg = 'Bolt: Cannot find product info';
                Mage::helper('boltpay/bugsnag')->notifyException($msg);
                return '""';
            }

            $productCheckoutCartItem[] = [
                'reference' => $_product->getId(),
                'price'     => $_product->getPrice(),
                'quantity'  => 1,
                'image'     => $_product->getImageUrl(),
                'name'  => $_product->getName(),
            ];
            $totalAmount = $_product->getPrice();

            $productCheckoutCart = [
                'currency' => $currency,
                'items' => $productCheckoutCartItem,
                'total' => $totalAmount,
            ];

             return json_encode($productCheckoutCart);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }

        return '""';
    }

    /**
     * Collect callbacks for BoltCheckout.configureProductCheckout
     *
     * @param string $checkoutType
     * @return string
     */
    public function getBoltCallbacks($checkoutType = self::CHECKOUT_TYPE_MULTI_PAGE)
    {
        /* @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        $checkCustom = $boltHelper->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckoutStartCustom = $boltHelper->getPaymentBoltpayConfig('on_checkout_start', $checkoutType);
        $onShippingDetailsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_details_complete', $checkoutType);
        $onShippingOptionsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_options_complete', $checkoutType);
        $onPaymentSubmitCustom = $boltHelper->getPaymentBoltpayConfig('on_payment_submit', $checkoutType);
        $successCustom = $boltHelper->getPaymentBoltpayConfig('success', $checkoutType);
        $closeCustom = $boltHelper->getPaymentBoltpayConfig('close', $checkoutType);

        $onSuccessCallback = $this->buildOnSuccessCallback($successCustom);
        $onCloseCallback = $this->buildOnCloseCallback($closeCustom);

        return "{
                  check: function() {
                    $checkCustom
                    return true;
                  },
                  onCheckoutStart: function() {
                    $onCheckoutStartCustom
                  },
                  onShippingDetailsComplete: function() {
                    $onShippingDetailsCompleteCustom
                  },
                  onShippingOptionsComplete: function() {
                    $onShippingOptionsCompleteCustom
                  },
                  onPaymentSubmit: function() {
                    $onPaymentSubmitCustom
                  },
                  success: $onSuccessCallback,
                  close: function() {
                     $onCloseCallback
                  }
                }";
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
