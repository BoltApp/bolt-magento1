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

    /**
     * @return mixed|NULL
     */
    public function getCurrentProduct()
    {
        return Mage::registry('current_product');
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    public function getSession()
    {
        return Mage::getSingleton('catalog/session');
    }

    /**
     * @return Mage_Core_Model_Store
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getStore()
    {
        return Mage::app()->getStore();
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        $ppcQuoteId = $this->getSession()->getData('ppcQuote');
        if ($ppcQuoteId) {
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($ppcQuoteId);
            $ppcQuote->removeAllItems();
        } else {
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = Mage::getModel('sales/quote');
            $ppcQuote->setStore($this->getStore());
            $ppcQuote->reserveOrderId();
            $ppcQuote->collectTotals()->save();
            $this->getSession()->setData('ppcQuote', $ppcQuote->getId());
        }

        return $ppcQuote;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuoteWithCurrentProduct()
    {
        /** @var Mage_Sales_Model_Quote $ppcQuote */
        $ppcQuote = $this->getQuote();
        $ppcQuote->addProduct($this->getCurrentProduct());
        $ppcQuote->getShippingAddress()->setCollectShippingRates(true);
        $ppcQuote->collectTotals()->save();

        return $ppcQuote;
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return bool
     */
    public function isBoltActive()
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
        $product =$this->getCurrentProduct();

        return ($product && in_array($product->getTypeId(), $this->getProductSupportedTypes()));
    }

    /**
     * @return object|string
     */
    public function getBoltToken()
    {
        if ($this->isSupportedProductType()) {
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = $this->getQuoteWithCurrentProduct();

            $boltOrder = new Bolt_Boltpay_Model_BoltOrder();
            $token = $boltOrder->getBoltOrderToken($ppcQuote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);
            return $token;
        }
    }
    ///// create order from quote
//    $service = Mage::getModel('sales/service_quote', $quote);
//    $service->submitAll();
//    $increment_id = $service->getOrder()->getRealOrderId();

    /**
     * @return array
     */
    protected function getProductSupportedTypes()
    {
        return [
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL
        ];
    }
}
