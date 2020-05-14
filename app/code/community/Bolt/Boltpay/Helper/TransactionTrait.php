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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trait Bolt_Boltpay_Helper_TransactionTrait
 *
 * Defines functions used by Bolt
 */
trait Bolt_Boltpay_Helper_TransactionTrait {

    /**
     * Gets the immutable quote id stored in the Bolt transaction.  This is backwards
     * compatible with older versions of the plugin and is suitable for transition
     * installations.
     *
     * @param object $transaction  The Bolt transaction as a php object
     *
     * @return string  The immutable quote id
     */
    public function getImmutableQuoteIdFromTransaction( $transaction ) {
        if (strpos($transaction->order->cart->display_id, '|') !== false) {
            return explode("|", $transaction->order->cart->display_id)[1];
        } else {
            /////////////////////////////////////////////////////////////////
            // Here we address legacy hook format for backward compatibility
            // When placed into production in a merchant that previously used the old format,
            // all their prior orders will have to be accounted for as there are potential
            // hooks like refund, cancel, or order approval that will still be presented in
            // the old format.
            //
            // For $transaction->order->cart->order_reference
            //  - older version stores the immutable quote ID here, and parent ID in getParentQuoteId()
            //  - newer version stores the parent ID here, and immutable quote ID in getParentQuoteId()
            // So, we take the max of getParentQuoteId() and $transaction->order->cart->order_reference
            // which will be the immutable quote ID
            /////////////////////////////////////////////////////////////////
            $potentialQuoteId = (int) $transaction->order->cart->order_reference;
            /** @var Mage_Sales_Model_Quote $potentialQuote */
            $potentialQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($potentialQuoteId);

            $associatedQuoteId = (int) $potentialQuote->getParentQuoteId();

            return max($potentialQuoteId, $associatedQuoteId);
        }

    }

    /**
     * Gets the increment id stored in the Bolt transaction.  This is backwards
     * compatible with older versions of the plugin and is suitable for transition
     * installations.
     *
     * @param object $transaction  The Bolt transaction as a php object
     *
     * @return string  The order increment id
     */
    public function getIncrementIdFromTransaction( $transaction ) {
        return (strpos($transaction->order->cart->display_id, '|') !== false)
            ? explode("|", $transaction->order->cart->display_id)[0]
            : $transaction->order->cart->display_id;
    }

}