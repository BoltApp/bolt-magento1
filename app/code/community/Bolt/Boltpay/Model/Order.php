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

use Bolt_Boltpay_OrderCreationException as OCE;

/**
 * Class Bolt_Boltpay_Model_Order
 *
 * The Magento Model class that provides order related utility methods
 *
 */
class Bolt_Boltpay_Model_Order extends Bolt_Boltpay_Model_Abstract
{
    const MERCHANT_BACK_OFFICE = 'merchant_back_office';

    protected $outOfStockSkus = null;
    protected $orderWasFlaggedForHold = false;
    protected $orderOnHoldMessage = '';
    protected $cartProducts = null;

    /**
     * Processes Magento order creation. Called from both frontend and API.
     *
     * @param string        $reference           Bolt transaction reference
     * @param int           $sessionQuoteId      Quote id, used if triggered from shopping session context,
     *                                           This will be null if called from within an API call context
     * @param boolean       $isPreAuthCreation   If called via pre-auth creation. default to false.
     * @param object        $transaction         pre-loaded Bolt Transaction object
     *
     * @todo   Remove orphaned transaction logic
     *
     * @return Mage_Sales_Model_Order   The order saved to Magento
     *
     * @throws Bolt_Boltpay_OrderCreationException    thrown on order creation failure
     */
    public function createOrder($reference, $sessionQuoteId = null, $isPreAuthCreation = false, $transaction = null)
    {
        try {
            if (empty($reference) && !$isPreAuthCreation) {
                throw new Exception($this->boltHelper()->__("Bolt transaction reference is missing in the Magento order creation process."));
            }

            $transaction = $transaction ?: $this->boltHelper()->fetchTransaction($reference);

            $immutableQuoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);
            $immutableQuote = $this->getQuoteById($immutableQuoteId);
            $parentQuote = $this->getQuoteById($immutableQuote->getParentQuoteId());

            if (!$sessionQuoteId){
                $sessionQuoteId = $immutableQuote->getParentQuoteId();
                $this->boltHelper()->setCustomerSessionByQuoteId($sessionQuoteId);
            }

            $this->validateCartSessionData($immutableQuote, $parentQuote, $sessionQuoteId, $transaction);

            // adding guest user email to order
            if (!$immutableQuote->getCustomerEmail()) {
                $email = $transaction->from_credit_card->billing_address->email_address;
                $immutableQuote->setCustomerEmail($email);
                $immutableQuote->save();
            }

            // explicitly set quote belong to guest if customer id does not exist
            $immutableQuote
                ->setCustomerIsGuest( (($parentQuote->getCustomerId()) ? false : true) );

            // Set the firstname and lastname if guest customer.
            if ($immutableQuote->getCustomerIsGuest()) {
                $consumerData = $transaction->from_consumer;
                $immutableQuote
                    ->setCustomerFirstname($consumerData->first_name)
                    ->setCustomerLastname($consumerData->last_name);
            }
            $immutableQuote->save();

            $immutableQuote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
            $immutableQuote->getBillingAddress()
                ->setFirstname($transaction->from_credit_card->billing_address->first_name)
                ->setLastname($transaction->from_credit_card->billing_address->last_name)
                ->setShouldIgnoreValidation(true)
                ->save();

            //////////////////////////////////////////////////////////////////////////////////
            ///  Apply shipping address and shipping method data to quote directly from
            ///  the Bolt transaction.
            //////////////////////////////////////////////////////////////////////////////////
            $packagesToShip = $transaction->order->cart->shipments;

            if ($packagesToShip) {

                $shippingAddress = $immutableQuote->getShippingAddress();
                $shippingMethodCode = null;

                /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
                $shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
                $shippingAndTaxModel->applyShippingAddressToQuote($immutableQuote, $packagesToShip[0]->shipping_address);
                $shippingMethodCode = $packagesToShip[0]->reference;

                if (!$shippingMethodCode) {
                    // Legacy transaction does not have shipments reference - fallback to $service field
                    $shippingMethod = $packagesToShip[0]->service;

                    $this->boltHelper()->collectTotals($immutableQuote);

                    $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
                    $rates = $shippingAddress->getAllShippingRates();

                    foreach ($rates as $rate) {
                        if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() === $shippingMethod
                            || (!$rate->getMethodTitle() && $rate->getCarrierTitle() === $shippingMethod)) {
                            $shippingMethodCode = $rate->getCarrier() . '_' . $rate->getMethod();
                            break;
                        }
                    }
                }

                if ($shippingMethodCode) {
                    $shippingAndTaxModel->applyShippingRate($immutableQuote, $shippingMethodCode, false);
                    $shippingAddress->save();
                    Mage::dispatchEvent(
                        'bolt_boltpay_order_creation_shipping_method_applied',
                        array(
                            'quote'=> $immutableQuote,
                            'shippingMethodCode' => $shippingMethodCode
                        )
                    );
                } else {
                    $errorMessage = $this->boltHelper()->__('Shipping method not found');
                    $metaData = array(
                        'transaction'   => $transaction,
                        'rates' => $this->getRatesDebuggingData($rates),
                        'service' => $shippingMethod,
                        'shipping_address' => var_export($shippingAddress->debug(), true),
                        'quote' => var_export($immutableQuote->debug(), true)
                    );
                    $this->boltHelper()->notifyException(new Exception($errorMessage), $metaData);
                }
            }
            //////////////////////////////////////////////////////////////////////////////////

            //////////////////////////////////////////////////////////////////////////////////
            // set Bolt as payment method
            //////////////////////////////////////////////////////////////////////////////////
            $immutableQuote->getShippingAddress()->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            $payment = $immutableQuote->getPayment();
            $payment->setMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            //////////////////////////////////////////////////////////////////////////////////

            $this->boltHelper()->collectTotals($immutableQuote, true)->save();
            $this->validateCoupons($immutableQuote, $transaction);
            $this->validateTotals($immutableQuote, $transaction);

            ////////////////////////////////////////////////////////////////////////////
            // reset increment id if needed
            ////////////////////////////////////////////////////////////////////////////
            /* @var Mage_Sales_Model_Order $preExistingOrder */
            $preExistingOrder = Mage::getModel('sales/order')->loadByIncrementId($parentQuote->getReservedOrderId());

            if (!$preExistingOrder->isObjectNew()) {
                ############################
                # First check if this order matches the immutable quote ID therefore already created
                # If so, we can return it as a the created order after notifying bugsnag
                ############################
                if ( $preExistingOrder->getQuoteId() === $immutableQuoteId ) {
                    Mage::helper('boltpay/bugsnag')->notifyException(
                        new Exception( Mage::helper('boltpay')->__("The order #%s has already been processed for this quote.", $preExistingOrder->getIncrementId() ) ),
                        array(),
                        'warning'
                    );
                    return $preExistingOrder;
                }
                ############################

                $parentQuote
                    ->setReservedOrderId(null)
                    ->reserveOrderId()
                    ->save();

                $immutableQuote->setReservedOrderId($parentQuote->getReservedOrderId());
            }
            ////////////////////////////////////////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////
            // call internal Magento service for order creation
            ////////////////////////////////////////////////////////////////////////////
            /** @var Mage_Sales_Model_Service_Quote $service */
            $service = Mage::getModel('sales/service_quote', $immutableQuote);

            try {

                //$this->validateProducts($immutableQuote);

                $service->submitAll();

                $order = $service->getOrder();
                $this->doAfterCreationValidation($order, $immutableQuote, $parentQuote, $sessionQuoteId, $transaction);

            } catch (Exception $e) {

                $this->boltHelper()->addBreadcrumb(
                    array(
                        'transaction'   => json_encode((array)$transaction),
                        'quote_address' => var_export($immutableQuote->getShippingAddress()->debug(), true)
                    )
                );
                throw $e;
            }
            ////////////////////////////////////////////////////////////////////////////

        } catch ( Exception $oce ) {
            // Order creation exception, so mark the parent quote as active so webhooks can retry it
            if (@$parentQuote) {
                $parentQuote->setIsActive(true)->save();
            }

            if ( $oce instanceof Bolt_Boltpay_OrderCreationException ) {
                throw $oce;
            } else {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_GENERAL_ERROR,
                    OCE::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
                    array( addcslashes($oce->getMessage(), '"\\') ),
                    $oce->getMessage(),
                    $oce
                );
            }
        }

        ///////////////////////////////////////////////////////
        // Close out session by assigning the immutable quote
        // as the parent of its parent quote
        //
        // This creates a circular reference so that we can use the parent quote
        // to look up the used immutable quote
        ///////////////////////////////////////////////////////
        $parentQuote->setParentQuoteId($immutableQuote->getId())
            ->save();
        ///////////////////////////////////////////////////////

        $recurringPaymentProfiles = $service->getRecurringPaymentProfiles();

        Mage::dispatchEvent(
            'checkout_submit_all_after',
            array('order' => $order, 'quote' => $immutableQuote, 'recurring_profiles' => $recurringPaymentProfiles)
        );
        ///////////////////////////////////////////////////////

        return $order;
    }


    /**
     * @param Mage_Sales_Model_Quote $immutableQuote
     * @param Mage_Sales_Model_Quote $parentQuote
     * @param $sessionQuoteId
     * @param $transaction
     * @throws Bolt_Boltpay_OrderCreationException
     */
    protected function validateCartSessionData($immutableQuote, $parentQuote, $sessionQuoteId, $transaction) {

        if ($immutableQuote->isEmpty()) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_FOUND,
                array( $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction) )
            );
        }

        if (!$parentQuote->getItemsCount()) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_EMPTY
            );
        }

        if ($parentQuote->isEmpty() || !$parentQuote->getIsActive()) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED
            );
        }

        foreach ($immutableQuote->getAllItems() as $cartItem) {
            /** @var Mage_Sales_Model_Quote_Item $cartItem */

            $product = $cartItem->getProduct();
            if (!$product->isSaleable()) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_CART_HAS_EXPIRED,
                    OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_PURCHASABLE,
                    array($product->getId())
                );
            }

            /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
            $quantityNeeded = $cartItem->getTotalQty();
            $quantityAvailable = $stockItem->getQty()-$stockItem->getMinQty();
            if (!$cartItem->getHasChildren() && !$stockItem->checkQty($quantityNeeded)) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_OUT_OF_INVENTORY,
                    OCE::E_BOLT_OUT_OF_INVENTORY_TMPL,
                    array($product->getId(), $quantityAvailable , $quantityNeeded)
                );
            }
        }

        /*
        // check that the order is in the system.  If not, we have an unexpected problem
        if ($immutableQuote->isEmpty()) {
            throw new Exception($this->boltHelper()->__("The expected immutable quote [{$immutableQuote->getId()}] is missing from the Magento system.  Were old quotes recently removed from the database?"));
        }

        if(!$this->allowOutOfStockOrders() && !empty($this->getOutOfStockSKUs($immutableQuote))){
            throw new Exception($this->boltHelper()->__("Not all items are available in the requested quantities. Out of stock SKUs: %s", join(', ', $this->getOutOfStockSKUs($immutableQuote))));
        }

        // check if the quotes matches, frontend only
        if ( $sessionQuoteId && ($sessionQuoteId != $immutableQuote->getParentQuoteId()) ) {
            throw new Exception(
                $this->boltHelper()->__("The Bolt order reference does not match the current cart ID. Cart ID: [%s]  Bolt Reference: [%s]",
                    $sessionQuoteId , $immutableQuote->getParentQuoteId())
            );
        }

        if ($parentQuote->isEmpty()) {
            throw new Exception(
                $this->boltHelper()->__("The parent quote %s is unexpectedly missing.",
                    $immutableQuote->getParentQuoteId() )
            );
        } else if (!$parentQuote->getIsActive() && $transaction->indemnification_reason !== self::MERCHANT_BACK_OFFICE) {
            throw new Exception(
                $this->boltHelper()->__("The parent quote %s for immutable quote %s is currently being processed or has been processed for order #%s. Check quote %s for details.",
                    $parentQuote->getId(), $immutableQuote->getId(), $parentQuote->getReservedOrderId(), $parentQuote->getParentQuoteId() )
            );
        }
        */

    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Quote $immutableQuote
     * @param Mage_Sales_Model_Quote $parentQuote
     * @param $sessionQuoteId
     * @param $transaction
     */
    protected function doAfterCreationValidation($order, $immutableQuote, $parentQuote, $sessionQuoteId, $transaction) {

        /////////////////////////////////////////////////////////////
        /// When the order is empty, it did not save in Magento
        /// Here we attempt to discover the cause of the problem
        /////////////////////////////////////////////////////////////
        if(empty($order)) {
            throw new Exception("Order was not able to be saved");
        }
        /////////////////////////////////////////////////////////////
    }

    /**
     * Called after order is authorized on Bolt.
     *
     * @param string $orderIncrementId  customer facing order id
     * @param object $payload           payload sent from Bolt
     */
    public function receiveOrder( $orderIncrementId, $payload ) {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $payloadObject = json_decode($payload);
        $immutableQuote = $this->getQuoteFromOrder($order);

        ///////////////////////////////////////////////////////
        /// Dispatch order save events
        ///////////////////////////////////////////////////////
        Mage::dispatchEvent('bolt_boltpay_authorization_after', array('order'=>$order, 'quote'=>$immutableQuote, 'reference' => $payloadObject->transaction_reference));
    }

    /**
     * Sends an email if an order email has not already been sent.
     *
     * @param $order Mage_Sales_Model_Order     The order which has just been authorized
     */
    public function sendOrderEmail($order)
    {
        try {
            $order->queueNewOrderEmail();
        } catch (Exception $e) {
            // Catches errors that occur when sending order email confirmation (e.g. external API is down)
            // and allows order creation to complete.

            $error = new Exception('Failed to send order email', 0, $e);
            $this->boltHelper()->notifyException($error);
            return;
        }

        $history = $order->addStatusHistoryComment( $this->boltHelper()->__('Email sent for order %s', $order->getIncrementId()) );
        $history->setIsCustomerNotified(true);
    }

    /**
     * @param $quoteId
     *
     * @return \Mage_Sales_Model_Quote
     */
    public function getQuoteById($quoteId)
    {
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')
            ->getCollection()
            ->addFieldToFilter('entity_id', $quoteId)
            ->getFirstItem();

        return $quote;
    }

    /**
     * Validates coupon codes
     *
     * @param Mage_Sales_Model_Quote $immutableQuote    Magento copy of Bolt order data
     * @param object                 $transaction       Bolt copy of order data
     *
     * @throws Bolt_Boltpay_OrderCreationException  when coupon fails validation
     */
    protected function validateCoupons(Mage_Sales_Model_Quote $immutableQuote, $transaction) {

        if (@$transaction->order->cart->discounts) {
            /*
             * Natively, Magento only supports one coupon code per order, but we can build
             * basic support here for plugins like Amasty or custom solutions that implement
             * multiple coupons.
             *
             * Here, we use "," to delimit multiple coupon codes like Amasty and popular multi-coupon
             * custom code strategies.  This implementation also supports standard magento single coupon
             * format.
             */
            foreach($transaction->order->cart->discounts as $boltCoupon) {

                if (@$boltCoupon->reference) {
                    $magentoCoupon = Mage::getModel('salesrule/coupon')->load($boltCoupon->reference, 'code');
                    $couponExists = (bool) $magentoCoupon->getId();

                    if ($couponExists) {

                        $magentoCouponCodes = $immutableQuote->getCouponCode() ? explode(',', (string) $immutableQuote->getCouponCode()) : array();

                        if (!in_array($boltCoupon->reference, $magentoCouponCodes)) {
                            /** @var Mage_SalesRule_Model_Rule $rule */
                            $rule = Mage::getModel('salesrule/rule')->load($magentoCoupon->getRuleId());
                            $toTime = $rule->getToDate() ? ((int) strtotime($rule->getToDate()) + Mage_CatalogRule_Model_Resource_Rule::SECONDS_IN_DAY - 1) : 0;
                            $now = Mage::getModel('core/date')->gmtTimestamp('Today');

                            if ( $toTime && $toTime < $now ) {
                                throw new Bolt_Boltpay_OrderCreationException(
                                    OCE::E_BOLT_DISCOUNT_CANNOT_APPLY,
                                    OCE::E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_EXPIRED,
                                    array($boltCoupon->reference)
                                );
                            }

                            throw new Bolt_Boltpay_OrderCreationException(
                                OCE::E_BOLT_DISCOUNT_CANNOT_APPLY,
                                OCE::E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_GENERIC,
                                array("Coupon criteria was not met.", $boltCoupon->reference)
                            );
                        }

                    } else {
                        throw new Bolt_Boltpay_OrderCreationException(
                            OCE::E_BOLT_DISCOUNT_DOES_NOT_EXIST,
                            OCE::E_BOLT_DISCOUNT_DOES_NOT_EXIST_TMPL,
                            array($boltCoupon->reference)
                        );
                    }
                }

            }
        }
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    protected function validateTotals(Mage_Sales_Model_Quote $immutableQuote, $transaction)
    {

        foreach ($transaction->order->cart->items as $boltCartItem) {

            $cartItem = $immutableQuote->getItemById($boltCartItem->reference);
            $boltPrice = (int)$boltCartItem->total_amount->amount;
            $magentoPrice = (int) round($cartItem->getCalculationPrice() * 100 * $cartItem->getQty());

            if ( $boltPrice !== $magentoPrice ) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED,
                    OCE::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED_TMPL,
                    array($cartItem->getProductId(), $boltPrice, $magentoPrice)
                );
            }
        }

        $magentoDiscountTotal = (int)(($immutableQuote->getBaseSubtotal() - $immutableQuote->getBaseSubtotalWithDiscount()) * 100);
        $boltDiscountTotal = (int)$transaction->order->cart->discount_amount->amount;
        if ($magentoDiscountTotal !== $boltDiscountTotal) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_DISCOUNT,
                array($boltDiscountTotal, $magentoDiscountTotal)
            );
        }

        if ( !$immutableQuote->isVirtual() ) {
            $shippingAddress = $immutableQuote->getShippingAddress();
            $magentoShippingTotal = (int) (($shippingAddress->getShippingAmount() - $shippingAddress->getBaseShippingDiscountAmount()) * 100);
            $boltShippingTotal = (int)$transaction->order->cart->shipping_amount->amount;
            if ($magentoShippingTotal !== $boltShippingTotal) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_CART_HAS_EXPIRED,
                    OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_SHIPPING,
                    array($boltShippingTotal, $magentoShippingTotal)
                );
            }

            // Shipping Tax totals is used for to supply the total tax total for round error purposes.  Therefore,
            // we do not validate that total, but only the full tax total
        }

        $magentoTaxTotal = (int)(( $tax = @$immutableQuote->getTotals()['tax']) ? round($tax->getValue() * 100) : 0 );
        $boltTaxTotal = (int)$transaction->order->cart->tax_amount->amount;
        if ($magentoTaxTotal !== $boltTaxTotal) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_TAX,
                array($boltTaxTotal, $magentoTaxTotal)
            );
        }

    }

    /**
     * @var Mage_Sales_Model_Quote $quote The quote that defines the cart
     *
     * @return array
     */
    protected function getCartProducts(Mage_Sales_Model_Quote $quote)
    {
        if($this->cartProducts == null) {
            /** @var Mage_Sales_Model_Quote_Item $cartItem */
            foreach ($quote->getAllItems() as $cartItem) {
                if ($cartItem->getHasChildren()) {
                    continue;
                }

                $product = $cartItem->getProduct();
                $product->setCartItemQty($cartItem->getQty());

                $this->cartProducts[] = $product;
            }
        }

        return $this->cartProducts;
    }

    /**
     * Gets a an order by parent quote id/Bolt order reference
     *
     * @param int|string $quoteId  The quote id which this order was created from
     *
     * @return Mage_Sales_Model_Order   If found, and order with all the details, otherwise a new object order
     */
    public function getOrderByParentQuoteId($quoteId) {
        $parentQuote = $this->getQuoteById($quoteId);
        return $this->getOrderByQuoteId($parentQuote->getParentQuoteId());
    }

    /**
     * Gets a an order by quote id/order reference
     *
     * @param int|string $quoteId  The quote id which this order was created from
     *
     * @return Mage_Sales_Model_Order   If found, and order with all the details, otherwise a new object order
     */
    public function getOrderByQuoteId($quoteId) {
        /* @var Mage_Sales_Model_Resource_Order_Collection $orderCollection */
        $orderCollection = Mage::getResourceModel('sales/order_collection');

        return $orderCollection
            ->addFieldToFilter('quote_id', $quoteId)
            ->getFirstItem();
    }

    /**
     * Retrieve the Quote object of an order
     *
     * @param Mage_Sales_Model_Order $order  The order from which to retrieve its quote
     *
     * @return Mage_Sales_Model_Quote   The quote which created the order
     */
    public function getQuoteFromOrder($order) {
        return Mage::getModel('sales/quote')->loadByIdWithoutStore($order->getQuoteId());
    }


    /**
     * Retrieve the parent Quote object of an order
     *
     * @param Mage_Sales_Model_Order $order     The order from which to retrieve its parent quote
     *
     * @return Mage_Sales_Model_Quote   The parent quote of the order that is tied to the Magento session
     */
    public function getParentQuoteFromOrder($order) {
        $quote = $this->getQuoteFromOrder($order);
        return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId());
    }


    protected function getRatesDebuggingData($rates) {
        $rateDebuggingData = '';

        if(isset($rates)) {
            foreach($rates as $rate) {
                $rateDebuggingData .= var_export($rate->debug(), true);
            }
        }

        return $rateDebuggingData;
    }

}