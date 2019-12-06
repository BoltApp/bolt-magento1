<?php

class Bolt_Boltpay_Helper_CatalogHelper extends Mage_Core_Helper_Abstract
{
    /**
     * @return Mage_Core_Model_Abstract
     */
    public function getSession()
    {
        return Mage::getSingleton('catalog/session');
    }

    /**
     * Get Last real order ID from the catalog session
     * @return integer
     */
    public function getLastRealOrderId()
    {
        return Mage::getSingleton('catalog/session')->getLastRealOrderId();
    }

    /**
     * @param string $scope
     * @return string
     */
    private function getQuoteIdKey($scope = 'ppc')
    {
        return $scope.'_quote_id_' . Mage::app()->getStore()->getId();
    }

    /**
     * Get Quote for Product page
     * @return Mage_Sales_Model_Quote
     */
    public  function getQuote()
    {
        $hasOrder = false;
        $orderQuoteId = false;
        $ppcQuote = null;
        $ppcQuoteId = $this->getSession()->getData($this->getQuoteIdKey());
        if ($ppcQuoteId) {
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($ppcQuoteId);
            $order = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('increment_id', $ppcQuote->getReservedOrderId())->setPageSize(1)->getFirstItem();
            if ($order instanceof Mage_Sales_Model_Order) {
                $orderQuoteId = $order->getQuoteId();
            }
            if ($orderQuoteId && $ppcQuoteId == $orderQuoteId) {
                $ppcQuote->setIsActive(false);
                $ppcQuote->delete();
                $hasOrder = true;
            }
        }
        if ($hasOrder || empty($ppcQuote)) {
            /** @var Mage_Sales_Model_Quote $ppcQuote */
            $ppcQuote = Mage::getModel('sales/quote');
            $ppcQuote->setStore(Mage::app()->getStore());
            $ppcQuote->reserveOrderId();
            $ppcQuote->getShippingAddress()->setCollectShippingRates(true);
            $ppcQuote->collectTotals()->save();
        }
        return $ppcQuote;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuoteWithCurrentProduct(Mage_Catalog_Model_Product $product, $request = null)
    {
        /** @var Mage_Sales_Model_Quote $ppcQuote */
        $ppcQuote = $this->getQuote();
        $ppcQuote->removeAllItems();
        $ppcQuote->addProduct($product, $request);
        //$ppcQuote->setParentQuoteId($ppcQuote->getId());
        //$ppcQuote->getBillingAddress();
        //$ppcQuote->getShippingAddress()->setCollectShippingRates(true);
        $ppcQuote->setParentQuoteId($ppcQuote->getId());
        $ppcQuote->collectTotals()->save();
        $this->getSession()->setData($this->getQuoteIdKey(), $ppcQuote->getId());
        
        return $ppcQuote;
    }

    /**
     * Get request for product add to cart procedure
     *
     * @param   mixed $requestInfo
     * @return  Varien_Object
     */
    public function getProductRequest($requestInfo)
    {
        if ($requestInfo instanceof Varien_Object) {
            $request = $requestInfo;
        } elseif (is_numeric($requestInfo)) {
            $request = new Varien_Object(array('qty' => $requestInfo));
        } else {
            $request = new Varien_Object($requestInfo);
        }
        return $request;
    }
}
