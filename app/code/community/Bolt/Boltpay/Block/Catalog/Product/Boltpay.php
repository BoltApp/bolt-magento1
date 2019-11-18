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
 *
 */
class Bolt_Boltpay_Block_Catalog_Product_Boltpay extends Mage_Core_Block_Template
{
    use Bolt_Boltpay_BoltGlobalTrait;

    public static $simpleProductTypes = array(
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL
    );

    /**
     * @return mixed|NULL
     */
    private function getCurrentProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return bool
     */
    private function isBoltActive()
    {
        return $this->boltHelper()->isBoltPayActive();
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout()
    {
        return ($this->isBoltActive() && $this->boltHelper()->isEnabledProductPageCheckout());
    }

    /**
     * @return bool
     */
    public function isSupportedProductType()
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $this->getCurrentProduct();

        return ($product && in_array($product->getTypeId(), $this->getProductSupportedTypes()));
    }

    /**
     * @return object|string
     */
    public function getBoltToken(Bolt_Boltpay_Model_BoltOrder $boltOrder)
    {
        if ($this->isSupportedProductType() && $this->isEnabledProductPageCheckout()) {
            $helper = new Bolt_Boltpay_Helper_CatalogHelper();
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = $helper->getQuoteWithCurrentProduct($this->getCurrentProduct());
            $response = $boltOrder->getBoltOrderToken($ppcQuote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);
            if ($response && $response->token) {
                return $response->token;
            }
            return '';
        }
        return '';
    }

    /**
     * @return array
     */
    private function getProductSupportedTypes()
    {
        return self::$simpleProductTypes;
    }
}
