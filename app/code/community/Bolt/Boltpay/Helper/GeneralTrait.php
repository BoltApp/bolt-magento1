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
     * @var bool    a flag set to true if the an order was placed in this request, otherwise false
     */
    public static $boltOrderWasJustPlaced = false;

    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    public static $canChangePreAuthStatus = true;

    /**
     * Determines if the Bolt payment method can be used to pay for the given quote using the quote's context
     *
     * @param Mage_Sales_Model_Quote $quote        The cart to be inspected as viable for Bolt payment
     * @param bool                   $checkCountry Set to true if the billing country should be checked, otherwise false
     *
     * @return bool     true if Bolt can be used, false otherwise
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function canUseBolt($quote, $checkCountry = true)
    {
        $applicationContextStore = Mage::app()->getStore();
        $quoteContextStore = $quote->getStore();

        Mage::app()->setCurrentStore($quoteContextStore);
        /**
         * If called from hooks always return true
         */
        if (self::$fromHooks) return true;

        $canQuoteUseBolt = $this->isBoltPayActive()
            && (!$checkCountry || ($checkCountry && $this->canUseForCountry($quote->getBillingAddress()->getCountry())))
            && (Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD')
            && (Mage::app()->getStore()->getBaseCurrencyCode() == 'USD');

        Mage::app()->setCurrentStore($applicationContextStore);
        return $canQuoteUseBolt;
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
            $quote->getShippingAddress()->unsetData('cached_items_all');
            $quote->getShippingAddress()->unsetData('cached_items_nominal');
            $quote->getShippingAddress()->unsetData('cached_items_nonnominal');
        }

        $quote->collectTotals();

        return $quote;
    }

    /**
     * @param $item Mage_Sales_Model_Quote_Item
     *
     * @return string
     */
    public function getItemImageUrl($item)
    {
        /** @var Mage_Catalog_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('catalog/image');

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $product->load($product->getIdBySku($item->getSku()));

        $image = '';
        try {
            if ($product->getThumbnail()) {
                /** @var Mage_Catalog_Helper_Image $image */
                $image = $imageHelper->init($product, 'thumbnail', $product->getThumbnail());
            }
        } catch (Exception $e) {
        }

        return (string)$image;
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
     * @param string   $eventName              The name of the event to be dispatched
     * @param mixed    $valueToFilter          The value to filter
     * @param mixed    $additionalParameters   any extra parameter or array of parameters used in filtering
     *
     * @return mixed   the value after it has been filtered
     */
    public function doFilterEvent($eventName, $valueToFilter, $additionalParameters = array()) {
        return $this->dispatchFilterEvent($eventName, $valueToFilter, $additionalParameters);
    }

    /**
     * Memory conservative version of {@see Bolt_Boltpay_Helper_GeneralTrait::doFilterEvent()}
     * Use this instead if cases of passing large arrays or large string values
     *
     * @param string   $eventName              The name of the event to be dispatched
     * @param mixed    $valueToFilter          The value to filter
     * @param mixed    $additionalParameters   any extra parameter or array of parameters used in filtering
     *
     * @return mixed   the value after it has been filtered
     */
    public function dispatchFilterEvent($eventName, &$valueToFilter, $additionalParameters = array()) {
        $valueWrapper = new Varien_Object();
        $valueWrapper->setValue($valueToFilter);
        Mage::dispatchEvent(
            $eventName,
            array(
                'value_wrapper' => $valueWrapper,
                'parameters' => $additionalParameters
            )
        );

        return $valueWrapper->getValue();
    }

    /**
     * Unserializes a homogeneous non-associative array of integers from PHP serialized format to
     * to a PHP array without using the unserialize method
     *
     * @param string $serializedData    A non-associative array of integers in PHP serialized format
     *
     * @return  array the converted array of integers in PHP format
     */
    public function unserializeIntArray($serializedData) {
        preg_match_all('/i:\d+;i:(\d+);/', $serializedData, $convertedArray);
        return $convertedArray[1];
    }

    /**
     * Unserializes a homogeneous non-associative array of strings from PHP serialized format to
     * to a PHP array without using the unserialize method
     *
     * @param string $serializedData    A non-associative array of strings in PHP serialized format
     *
     * @return  array the converted array of strings in PHP format
     */
    public function unserializeStringArray($serializedData)
    {
        preg_match_all('/i:\d+;s:\d+:"([^"]*)";/', $serializedData, $convertedArray);
        return $convertedArray[1];
    }
}