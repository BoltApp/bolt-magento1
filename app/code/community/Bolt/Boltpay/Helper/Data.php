<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Helper_Data
 *
 * Base Magento Bolt Helper class
 *
 */
class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    static $fromHooks = false;

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
     * @return bool
     */
    public function isBoltPayActive()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/active');
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country)
    {

        if(!$this->isBoltPayActive()) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        if (Mage::getStoreConfig('payment/boltpay/allowspecific') == 1) {
            $availableCountries =
                explode(',', Mage::getStoreConfig('payment/boltpay/specificcountry'));
            if (!in_array($country, $availableCountries)){
                return false;
            }
        }

        return true;
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
     * Get config value
     *
     * @return bool
     */
    public function shouldAddButtonEverywhere()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/add_button_everywhere');
    }

    /**
     * Decrypt key
     *
     * @param $key
     * @return string
     */
    private function decryptKey($key)
    {
        return Mage::helper('core')->decrypt($key);
    }

    /**
     * Get MultiPage key
     *
     * @param bool $decrypt
     * @return string
     */
    public function getPublishableKeyMultiPageKey($decrypt = false)
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        if ($decrypt) {
            return $this->decryptKey($key);
        }

        return $key;
    }

    /**
     * Get OnePage Key
     *
     * @param bool $decrypt
     * @return string
     */
    public function getPublishableKeyOnePageKey($decrypt = false)
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_onepage');
        if ($decrypt) {
            return $this->decryptKey($key);
        }

        return $key;
    }

    /**
     * @return string
     */
    public function getConnectJsUrl()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/test') ?
            Bolt_Boltpay_Block_Checkout_Boltpay::JS_URL_TEST . "/connect.js":
            Bolt_Boltpay_Block_Checkout_Boltpay::JS_URL_PROD . "/connect.js";
    }

    /**
     * Creates a clone of a quote including items, addresses, customer details,
     * and shipping and tax options when
     *
     * @param Mage_Sales_Model_Quote $sourceQuote   The quote to be cloned
     * @param bool $isForMultipage    Determines if the quote is for payment only, (i.e. with address and shipping data), or multi-page, (i.e. without address and shipping data)
     *
     * @return Mage_Sales_Model_Quote  The cloned copy of the source quote
     */
    public function cloneQuote(Mage_Sales_Model_Quote $sourceQuote, $isForMultipage = false ) {

        /* @var Mage_Sales_Model_Quote $clonedQuote */
        $clonedQuote = Mage::getSingleton('sales/quote');

        try {
            // overridden quote classes may throw exceptions in post merge events.  We report
            // these in bugsnag, but these are non-fatal exceptions, so, we continue processing
            $clonedQuote->merge($sourceQuote);
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }

        if (!$isForMultipage) {
            // For the checkout page we want to set the
            // billing and shipping, and shipping method at this time.
            // For multi-page, we add the addresses during the shipping and tax hook
            // and the chosen shipping method at order save time.
            $clonedQuote
                ->setBillingAddress($sourceQuote->getBillingAddress())
                ->setShippingAddress($sourceQuote->getShippingAddress())
                ->getShippingAddress()
                ->setShippingMethod($sourceQuote->getShippingAddress()->getShippingMethod())
                ->save();
        }

        //////////////////////////////////////////////////////////////////////////////////////////////////
        // Attempting to reset some of the values already set by merge affects the totals passed to
        // Bolt in such a way that the grand total becomes 0.  Since we do not need to reset these values
        // we ignore them all.
        //////////////////////////////////////////////////////////////////////////////////////////////////
        $fieldsSetByMerge = array(
            'coupon_code',
            'subtotal',
            'base_subtotal',
            'subtotal_with_discount',
            'base_subtotal_with_discount',
            'grand_total',
            'base_grand_total',
            'auctaneapi_discounts',
            'applied_rule_ids',
            'items_count',
            'items_qty',
            'virtual_items_qty',
            'trigger_recollect',
            'can_apply_msrp',
            'totals_collected_flag',
            'global_currency_code',
            'base_currency_code',
            'store_currency_code',
            'quote_currency_code',
            'store_to_base_rate',
            'store_to_quote_rate',
            'base_to_global_rate',
            'base_to_quote_rate',
            'is_changed',
            'created_at',
            'updated_at',
            'entity_id'
        );

        // Add all previously saved data that may have been added by other plugins
        foreach ($sourceQuote->getData() as $key => $value) {
            if (!in_array($key, $fieldsSetByMerge)) {
                $clonedQuote->setData($key, $value);
            }
        }

        /////////////////////////////////////////////////////////////////
        // Generate new increment order id and associate it with current quote, if not already assigned
        // Save the reserved order ID to the session to check order existence at frontend order save time
        /////////////////////////////////////////////////////////////////
        $reservedOrderId = $sourceQuote->reserveOrderId()->save()->getReservedOrderId();
        Mage::getSingleton('core/session')->setReservedOrderId($reservedOrderId);

        $clonedQuote
            ->setCustomer($sourceQuote->getCustomer())
            ->setCustomerGroupId($sourceQuote->getCustomerGroupId())
            ->setCustomerIsGuest((($sourceQuote->getCustomerId()) ? false : true))
            ->setReservedOrderId($reservedOrderId)
            ->setStoreId($sourceQuote->getStoreId())
            ->setParentQuoteId($sourceQuote->getId())
            ->save();

        return $clonedQuote;
    }

    /**
     * Checking the config
     *
     * @return bool
     */
    public function canUseEverywhere()
    {
        $active = $this->isBoltPayActive();
        $isEverywhere = $this->shouldAddButtonEverywhere();

        return ($active && $isEverywhere);
    }
}
