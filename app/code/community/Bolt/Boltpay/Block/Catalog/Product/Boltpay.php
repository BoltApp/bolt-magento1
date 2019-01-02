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
            $totalAmount = (float) 0;

            /** @var Mage_Catalog_Model_Product $_product */
            $_product = Mage::registry('current_product');
            if (!$_product) {
                $msg = 'Bolt: Cannot find product info';
                Mage::helper('boltpay/bugsnag')->notifyException($msg);
                return '""';
            }

            if (!$_product->isInStock()) {
                return '""';
            }

            $productCheckoutCartItem[] = [
                'reference' => $_product->getId(),
                'price' => $_product->getPrice(),
                'quantity' => 1,
                'image' => $_product->getImageUrl(),
                'name' => $_product->getName(),
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
            return '""';
        }
    }

    /**
     * Collect callbacks for BoltCheckout.configureProductCheckout
     *
     * @param string $checkoutType
     * @param bool   $isVirtualQuote
     *
     * @return string
     */
    public function getBoltCallbacks($checkoutType = self::CHECKOUT_TYPE_MULTI_PAGE, $isVirtualQuote = false )
    {
        /* @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        return $boltHelper->getBoltCallbacks($checkoutType, $isVirtualQuote);
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

    /**
     * @return bool
     */
    public function isSupportedProductType()
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::registry('current_product');

        return ($product && in_array($product->getTypeId(), $this->getProductSupportedTypes()));
    }

    /**
     * @return array
     */
    protected function getProductSupportedTypes()
    {
        return [
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
        ];
    }
}
