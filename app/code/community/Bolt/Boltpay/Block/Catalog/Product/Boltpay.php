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
 * Class Bolt_Boltpay_Block_Catalog_Product_Boltpay
 *
 * This block is used in boltpay/catalog/product/configure_checkout.phtml and boltpay/catalog/product/button.phtml templates
 *
 * This is defined in boltpay.xml config file
 *
 * The purpose is to add the Bolt to the Product Page,
 * create the order on Bolt side through the javascript BoltCheckout.configureProductCheckout process.
 */
class Bolt_Boltpay_Block_Catalog_Product_Boltpay extends Bolt_Boltpay_Block_Checkout_Boltpay
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Get Product Tier Price
     * @return mixed
     * @deprecated
     */
    public function getProductTierPrice()
    {
        return Mage::registry('current_product')->getData('tier_price');
    }

    /**
     * Generates BoltCheckout cart configuration for product page checkout
     *
     * @return string JSON encoded Bolt cart data containing current product
     * @deprecated
     */
    public function getCartDataJsForProductPage()
    {
        try {
            $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

            $productCheckoutCartItem = [];

            /** @var Mage_Catalog_Model_Product $_product */
            $_product = Mage::registry('current_product');
            if (!$_product) {
                $msg = 'Bolt: Cannot find product info';
                $this->boltHelper()->notifyException(new Exception($msg));
                return '""';
            }

            if (!$_product->isInStock()) {
                return '""';
            }

            $productCheckoutCartItem[] = [
                'reference' => $_product->getId(),
                'price'     => $_product->getFinalPrice(),
                'quantity'  => 1,
                'image'     => $_product->getImageUrl(),
                'name'      => $_product->getName(),
            ];
            $totalAmount = $_product->getFinalPrice();


            $productCheckoutCart = [
                'currency' => $currency,
                'items'    => $productCheckoutCartItem,
                'total'    => $totalAmount,
            ];

             return json_encode($productCheckoutCart);
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
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
     * @deprecated
     */
    public function getBoltCallbacks($checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE, $isVirtualQuote = false )
    {
        return $this->boltHelper()->getBoltCallbacks($checkoutType, $isVirtualQuote);
    }

    /**
     * Generates on success callback for product page checkout
     *
     * @param string $successCustom javascript callback to be included in AJAX success callback
     *
     * @return string on success javascript callback
     *
     * @throws Mage_Core_Model_Store_Exception if unable to get save order URL
     *
     * @deprecated
     */
    public function buildOnSuccessCallback($successCustom = '')
    {
        $saveOrderUrl = $this->boltHelper()->getMagentoUrl('boltpay/order/save');

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
     * Generates on close callback for product page checkout
     *
     * @param string $closeCustom callback to be included in returned javascript
     *
     * @return string on close javascript callback
     *
     * @throws Mage_Core_Model_Store_Exception if unable to get success URL
     *
     * @deprecated
     */
    public function buildOnCloseCallback($closeCustom = '')
    {
        $successUrl = $this->boltHelper()->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $javascript = $closeCustom;

        return $javascript .
            "if (order_completed) {
                location.href = '$successUrl';
            }
            ";
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout()
    {
        return ($this->isBoltActive() && $this->boltHelper()->isEnabledProductPageCheckout());
    }

    /**
     * @return string
     */
    public function getProductPageCheckoutSelector()
    {
        return $this->boltHelper()->getProductPageCheckoutSelector();
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
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
            Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE,
            Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
            Mage_Catalog_Model_Product_Type::TYPE_GROUPED,
            Mage_Catalog_Model_Product_Type::TYPE_BUNDLE,
        ];
    }

    /**
     * Returns product data needed for price calculation in JSON format
     *
     * @return string
     */
    public function getProductJSON()
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::registry('current_product');

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = $product->getStockItem();
        $productData = array(
            'id'          => $product->getId(),
            'name'        => $product->getName(),
            'price'       => $product->getFinalPrice(),
            'tier_prices' => $product->getTierPrice(),
            'type_id'     => $product->getTypeId(),
            'stock'       => array(
                'manage' => in_array(
                    $product->getTypeId(),
                    array(
                        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
                        Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
                        Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE,
                    )
                ) ? $stockItem->getManageStock() : false,
                'status' => $stockItem->getIsInStock(),
                'qty'    => (float) $stockItem->getQty(),
            ),
        );
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $productData['associated_products'] = array_map(
                function ($associatedProduct) {
                    /** @var Mage_Catalog_Model_Product $associatedProduct */
                    return array(
                        'id'    => $associatedProduct->getId(),
                        'name'  => $associatedProduct->getName(),
                        'price' => $associatedProduct->getFinalPrice(),
                    );
                },
                $product->getTypeInstance(true)->getAssociatedProducts($product)
            );
        }
        return Mage::helper('core')->jsonEncode($productData);
    }

    /**
     * Returns customer data in JSON format
     *
     * @return string
     */
    public function getCustomerJSON()
    {
        return Mage::helper('core')->jsonEncode(
            array(
                'is_logged_in' => Mage::getSingleton('customer/session')->isLoggedIn()
            )
        );
    }

    /**
     * Returns url to login page with current as referrer
     *
     * @return string
     */
    public function getCustomerLoginUrlWithReferrer()
    {
        return $this->getUrl(
            'customer/account/login',
            array(
                Mage_Customer_Helper_Data::REFERER_QUERY_PARAM_NAME => Mage::helper('core')->urlEncode(
                    $this->getUrl('*/*/*', array('_current' => true))
                )
            )
        );
    }
}
