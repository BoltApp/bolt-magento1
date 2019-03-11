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
 * Trait Bolt_Boltpay_Helper_GeneralTrait
 *
 * Defines generalized functions used by Bolt
 *
 */
trait Bolt_Boltpay_Helper_GeneralTrait {

    use Bolt_Boltpay_Helper_ConfigTrait;

    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    public static $fromHooks = false;

    /**
     * Determines if the Bolt payment method can be used in the system
     *
     * @param Mage_Sales_Model_Quote $quote         Magento quote object
     * @param bool                   $checkCountry Set to true if the billing country should be checked, otherwise false
     *
     * @return bool     true if Bolt can be used, false otherwise
     *
     * TODO: consider store base currency and possibly add conversion logic
     * @throws Mage_Core_Model_Store_Exception
     */
    public function canUseBolt($quote, $checkCountry = true)
    {
        /**
         * If called from hooks always return true
         */
        if (self::$fromHooks) return true;

        return $this->isBoltPayActive()
            && (!$checkCountry || ($checkCountry && $this->canUseForCountry($quote->getBillingAddress()->getCountry())))
            && (Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD')
            && (Mage::app()->getStore()->getBaseCurrencyCode() == 'USD');
    }



    /**
     * Resets rounding deltas before calling collect totals which fixes bug in collectTotals that causes rounding errors
     * when a percentage discount is applied to a quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param bool                   $clearTotalsCollectedFlag
     * @return Mage_Sales_Model_Quote
     */
    public function collectTotals($quote, $clearTotalsCollectedFlag = false)
    {
        Mage::getSingleton('salesrule/validator')->resetRoundingDeltas();

        if($clearTotalsCollectedFlag) {
            $quote->setTotalsCollectedFlag(false);
        }

        $quote->collectTotals();

        return $quote;
    }

    /**
     * @param $item
     *
     * @return string
     */
    public function getItemImageUrl($item)
    {
        /** @var Mage_Catalog_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('catalog/image');

        /** @var Mage_Catalog_Model_Product $_product */
        $_product = $item->getProduct();

        $image = '';
        try {
            if ($_product->getThumbnail()) {
                /** @var Mage_Catalog_Helper_Image $image */
                $image = $imageHelper->init($_product, 'thumbnail', $_product->getThumbnail());
            }
        } catch (Exception $e) {  }

        return (string) $image;
    }

    /**
     * Set customer session based on the quote id passed in
     *
     * @param $quoteId
     */
    public function setCustomerSessionByQuoteId($quoteId)
    {
        $customerId = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId)->getCustomerId();
        $this->setCustomerSessionById($customerId);
    }

    /**
     * Set customer session based on the customer id passed in
     *
     * @param $customerId
     */
    public function setCustomerSessionById($customerId)
    {
        if ($customerId) {
            Mage::getSingleton('customer/session')->loginById($customerId);
        }
    }


    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($checkoutType = 'multi-page')
    {
        return Mage::app()->getLayout()->createBlock('boltpay/checkout_boltpay')->getCartDataJs($checkoutType);
    }

    /**
     * Determines if the current page being displayed is the shopping cart
     *
     * @return bool true if the current page is the shopping cart, otherwise false
     */
    public function isShoppingCartPage()
    {
        return
            (Mage::app()->getRequest()->getRouteName() === 'checkout')
            && (Mage::app()->getRequest()->getControllerName() === 'cart');
    }

    /**
     * Dispatches event to filter a value
     *
     * @param string                    $eventName              The name of the event to be dispatched
     * @param mixed                     $valueToFilter          The value to filter
     * @param array                     $additionalParameters   any extra parameters used in filtering
     *
     * @return mixed   the value after it has been filtered
     */
    public function doFilterEvent($eventName, $valueToFilter, $additionalParameters = array()) {
        $valueWrapper = new Varien_Object();
        $valueWrapper->setValue($valueToFilter);
        Mage::dispatchEvent(
            $eventName,
            array(
                'valueWrapper' => $valueWrapper,
                'parameters' => $additionalParameters
            )
        );

        return $valueWrapper->getValue();
    }

}