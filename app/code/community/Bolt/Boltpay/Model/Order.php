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
    const MAX_ORDER_ID = 4294967295; # represents the max unsigned int by MySQL and MariaDB

    /**
     * Processes Magento order creation. Called from both frontend and API.
     *
     * @param string        $reference           Bolt transaction reference
     * @param int           $sessionQuoteId      Quote id, used if triggered from shopping session context,
     *                                           This will be null if called from within an API call context
     * @param boolean       $isPreAuthCreation   If called via pre-auth creation. default to false.
     * @param object        $transaction         pre-loaded Bolt Transaction object
     *
     *
     * @return Mage_Sales_Model_Order   The order saved to Magento
     *
     * @throws Bolt_Boltpay_OrderCreationException    thrown on order creation failure
     *
     * @todo Change this to accept a quote object to keep all setting current store context to the APIController
     * @todo Remove $reference, $sessionQuoteId, and $isPreAuth as this is only called from the preauth context
     */
    public function createOrder($reference, $sessionQuoteId = null, $isPreAuthCreation = false, $transaction = null)
    {
        try {
            if (empty($reference) && !$isPreAuthCreation) {
                $msg = $this->boltHelper()->__("Bolt transaction reference is missing in the Magento order creation process.");
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            }

            benchmark( "Potentially starting to fetch bolt transaction" );
            $transaction = $transaction ?: $this->boltHelper()->fetchTransaction($reference);
            benchmark( "Finished fetching bolt transaction. Looking up quotes." );

            $immutableQuoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);
            $immutableQuote = $this->getQuoteById($immutableQuoteId);
            $immutableQuote->setParentQuoteId($transaction->order->cart->order_reference);
            Mage::app()->setCurrentStore($immutableQuote->getStore());
            $parentQuote = $this->getQuoteById($transaction->order->cart->order_reference);
            benchmark( "Looked up quotes." );

            if (!$sessionQuoteId){
                $sessionQuoteId = $immutableQuote->getParentQuoteId();
                $this->boltHelper()->setCustomerSessionByQuoteId($sessionQuoteId);
            }

            benchmark( "Dispatching event bolt_boltpay_order_creation_before" );
            Mage::dispatchEvent(
                'bolt_boltpay_order_creation_before',
                array(
                    'immutable_quote'=> $immutableQuote,
                    'parent_quote' => $parentQuote,
                    'transaction' => $transaction
                )
            );
            benchmark( "Dispatched event bolt_boltpay_order_creation_before" );

            $this->validateCartSessionData($immutableQuote, $parentQuote, $transaction);
            benchmark( "Validated session data" );

            $parentQuote->setIsActive(false)->save();  # block synchronous processing of cart

            // adding guest user email to order
            if (!$immutableQuote->getCustomerEmail()) {
                $email = $transaction->order->cart->billing_address->email_address;
                $immutableQuote->setCustomerEmail($email);
            }

            // explicitly set quote belong to guest if customer id does not exist
            $immutableQuote
                ->setCustomerIsGuest( (($parentQuote->getCustomerId()) ? false : true) );

            // Set the firstname and lastname if guest customer.
            if ($immutableQuote->getCustomerIsGuest()) {
                $immutableQuote
                    ->setCustomerFirstname($transaction->order->cart->billing_address->first_name)
                    ->setCustomerLastname($transaction->order->cart->billing_address->last_name);
            }
            benchmark( "Saved customer information" );

            $immutableQuote->getShippingAddress()->setShouldIgnoreValidation(true);
            $immutableQuote->getBillingAddress()
                ->setFirstname($transaction->order->cart->billing_address->first_name)
                ->setLastname($transaction->order->cart->billing_address->last_name)
                ->setEmail($transaction->order->cart->billing_address->email_address)
                ->setShouldIgnoreValidation(true)
                ->save();
            benchmark( "Saved address info" );

            //////////////////////////////////////////////////////////////////////////////////
            ///  Apply shipping address and shipping method data to quote directly from
            ///  the Bolt transaction.
            //////////////////////////////////////////////////////////////////////////////////
            $packagesToShip = $transaction->order->cart->shipments;

            if ($packagesToShip) {
                benchmark( "Applying shipping" );
                $shippingAddress = $immutableQuote->getShippingAddress();
                $shippingMethodCode = null;

                /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
                $shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
                $shouldRecalculateShipping = (bool) $this->boltHelper()->getExtraConfig("recalculateShipping"); # false by default

                benchmark( "Applying shipping - Applying shipping address data" );
                $shippingAndTaxModel->applyBoltAddressData($immutableQuote, $packagesToShip[0]->shipping_address, $shouldRecalculateShipping);
                benchmark( "Finished applying shipping - Applying shipping address data" );

                $shippingMethodCode = $packagesToShip[0]->reference;

                if (!$shippingMethodCode) {
                    benchmark( "Applying shipping - Collecting shipping rates, legacy" );
                    // Legacy transaction does not have shipments reference - fallback to $service field
                    $shippingMethod = $packagesToShip[0]->service;

                    $this->boltHelper()->collectTotals($immutableQuote, $shouldRecalculateShipping);

                    $rates = $shippingAddress->getAllShippingRates();

                    foreach ($rates as $rate) {
                        if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() === $shippingMethod
                            || (!$rate->getMethodTitle() && $rate->getCarrierTitle() === $shippingMethod)) {
                            $shippingMethodCode = $rate->getCarrier() . '_' . $rate->getMethod();
                            break;
                        }
                    }
                    benchmark( "Finished applying shipping - Collecting shipping rates, legacy" );
                }

                if ($shippingMethodCode) {
                    $shippingAndTaxModel->applyShippingRate($immutableQuote, $shippingMethodCode, $shouldRecalculateShipping);
                } else {
                    $errorMessage = $this->boltHelper()->__('Shipping method not found');
                    $metaData = array(
                        'transaction'   => $transaction,
                        'rates' => $this->getRatesDebuggingData($rates),
                        'service' => $shippingMethod,
                        'shipping_address' => var_export($shippingAddress->debug(), true),
                        'quote' => var_export($immutableQuote->debug(), true)
                    );
                    $this->boltHelper()->logWarning($errorMessage);
                    $this->boltHelper()->notifyException(new Exception($errorMessage), $metaData);
                }
                benchmark( "Finished applying shipping" );
            }
            //////////////////////////////////////////////////////////////////////////////////

            //////////////////////////////////////////////////////////////////////////////////
            // set Bolt as payment method
            //////////////////////////////////////////////////////////////////////////////////
            $immutableQuote->getShippingAddress()->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            $payment = $immutableQuote->getPayment();
            $payment->setMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            //////////////////////////////////////////////////////////////////////////////////


            benchmark( "Collecting totals to validate" );
            $this->boltHelper()->collectTotals($immutableQuote, true)->save();
            benchmark( "Finished collecting totals to validate" );

            $this->validateCoupons($immutableQuote, $transaction);
            benchmark( "Validated coupons" );

            $this->validateTotals($immutableQuote, $transaction);
            benchmark( "Validated subTotals" );


            ////////////////////////////////////////////////////////////////////////////
            // reset increment id if needed
            ////////////////////////////////////////////////////////////////////////////
            /* @var Mage_Sales_Model_Order $preExistingOrder */
            $preExistingOrder = Mage::getModel('sales/order')->loadByIncrementId($parentQuote->getReservedOrderId());
            benchmark( "Searched for existing order" );

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

                $immutableQuote->setReservedOrderId($parentQuote->getReservedOrderId())->save();
            }
            ////////////////////////////////////////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////
            // call internal Magento service for order creation
            ////////////////////////////////////////////////////////////////////////////
            $immutableQuote->setTransaction($transaction)->setParent($parentQuote);

            /** @var Mage_Sales_Model_Service_Quote $service */
            $service = Mage::getModel('sales/service_quote', $immutableQuote);

            try {
                benchmark( "Submitting order" );
                $service->submitAll();
                $order = $service->getOrder();
                if (!$isPreAuthCreation) {
                    $order->addStatusHistoryComment($this->boltHelper()->__("BOLT notification: Order created via Bolt Webhook API for transaction $reference "));
                    $order->save();
                }
                benchmark( "Submitted order and validated it" );

                //////////////////////////////////////////////////
                // Add the user_note to the order comments
                // and make it visible for customer.
                //////////////////////////////////////////////////
                $userNote = isset($transaction->order->user_note)
                    ?
                        $this->boltHelper()->doFilterEvent(
                            'bolt_boltpay_filter_user_note',
                            '[CUSTOMER NOTE] ' . $transaction->order->user_note,
                            $order
                        )
                    :
                        ''
                ;
                if (!empty($userNote)) {
                    $this->setOrderUserNote($order, $userNote);
                }
                //////////////////////////////////////////////////

            } catch (Exception $e) {

                $this->boltHelper()->addBreadcrumb(
                    array(
                        'transaction'   => json_encode((array)$transaction),
                        'quote_address' => var_export($immutableQuote->getShippingAddress()->debug(), true)
                    )
                );
                $this->boltHelper()->logException($e);
                throw $e;
            }
            ////////////////////////////////////////////////////////////////////////////

        } catch ( Exception $oce ) {
            // Order creation exception, so mark the parent quote as active so webhooks can retry it
            if (@$parentQuote) {
                $parentQuote->setIsActive(true)->save();
            }

            $this->boltHelper()->logException($oce);
            $this->boltHelper()->notifyException($oce);

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

        // We set the created_at and updated_at date to null to hide the order from ERP until authorized
        if (!$this->boltHelper()->getExtraConfig('keepPreAuthOrderTimeStamps')) {
            $this->removeOrderTimeStamps($order);
        }

        if ($immutableQuote->getData('is_bolt_pdp') && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $this->associateOrderToCustomerWhenPlacingOnPDP($order->getData('increment_id'));
        }
        benchmark( "Finished post order processing" );

        ///////////////////////////////////////////////////////
        /// Dispatch order save events
        ///////////////////////////////////////////////////////
        Mage::dispatchEvent(
            'bolt_boltpay_order_creation_after',
            array('order'=>$order, 'quote'=>$immutableQuote, 'transaction' => $transaction)
        );
        benchmark( "Dispatched bolt_boltpay_order_creation_after" );
        ///////////////////////////////////////////////////////

        return $order;
    }


    /**
     * Checks several indicators to see if the Magento session or cart has expired
     *
     * @param Mage_Sales_Model_Quote $immutableQuote    Copy of the Magento session quote used by Bolt
     * @param Mage_Sales_Model_Quote $parentQuote       The Magento session quote holding cart data
     * @param object                 $transaction       The Bolt transaction object sent from the Bolt server
     *
     * @throws Bolt_Boltpay_OrderCreationException  on failure of session validation
     */
    protected function validateCartSessionData($immutableQuote, $parentQuote, $transaction) {

        if ($immutableQuote->isObjectNew()) {
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

        if ($parentQuote->isObjectNew() || !$parentQuote->getIsActive()) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED
            );
        }

        foreach ($immutableQuote->getAllItems() as $cartItem) {
            /** @var Mage_Sales_Model_Quote_Item $cartItem */
            $cartItem->shouldNotBeValidated = false;
            Mage::dispatchEvent(
                'bolt_boltpay_cart_item_inventory_validation_before',
                [
                    'cart_item' => $cartItem,
                    'quote' => $immutableQuote,
                    'transaction' => $transaction
                ]
            );

            if ( $cartItem->shouldNotBeValidated ){ continue; }

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

    }

    /**
     * This is the last chance, bottom line price check.  It is done after the submit service
     * has created the order, but before the order is committed to the database.  This allows
     * to get the actual totals that will be stored in the database and catch all unexpected
     * changes.  We have the option to attempt to correct any problems here.  If there remain
     * any unhandled problems, we can throw an exception and avoid complex order rollback.
     *
     * This is called from the observer context
     *
     * event: sales_model_service_quote_submit_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an order and (immutable) quote
     *                                        -  Mage_Sales_Model_Order order
     *                                        -  Mage_Sales_Model_Quote quote
     *
     *                                        The $quote, in turn holds
     *                                        -  Mage_Sales_Model_Quote parent (ONLY pre-auth; will be empty for admin)
     *                                        -  object (bolt) transaction (ONLY pre-auth; will be empty for admin)
     *
     *
     * @throws Exception    if an unknown error occurs
     * @throws Bolt_Boltpay_OrderCreationException if the bottom line price total differs by allowed tolerance
     *
     */
    public function validateBeforeOrderCommit($observer) {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        /** @var Mage_Sales_Model_Quote $quote */
        $immutableQuote = $order->getQuote();
        $boltTransaction = $immutableQuote->getTransaction();

        if ( (strtolower($payment->getMethod()) !== Bolt_Boltpay_Model_Payment::METHOD_CODE) || empty($boltTransaction) ) {
            return;
        }

        /////////////////////////////////////////////////////////////
        /// When the order is empty, it will not be able to save
        /// in Magento for an unknown reason.  Here we report the problem
        /////////////////////////////////////////////////////////////
        if(empty($order)) {
            throw new Exception("Order was not able to be saved");
        }
        /////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////
        /// Final sanity check on bottom line price on order.
        /// If we are failing here, then we've reach an unexpected
        /// snag that will we
        /////////////////////////////////////////////////////////////
        $priceFaultTolerance = $this->boltHelper()->getExtraConfig('priceFaultTolerance');

        $magentoGrandTotal = (int)(round($order->getGrandTotal() * 100));
        $boltGrandTotal = $boltTransaction->order->cart->total_amount->amount;
        $totalMismatch = $boltGrandTotal - $magentoGrandTotal;

        if (abs($totalMismatch) > $priceFaultTolerance) {
            throw new Bolt_Boltpay_OrderCreationException(
                OCE::E_BOLT_CART_HAS_EXPIRED,
                OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_GRAND_TOTAL,
                array($boltGrandTotal, $magentoGrandTotal)
            );
        } else if ($totalMismatch) {
            // Do order total correction if necessary so that the bottom line matches up
            $order->setTaxAmount($order->getTaxAmount() + ($totalMismatch/100))
                ->setBaseTaxAmount($order->getBaseTaxAmount() + ($totalMismatch/100))
                ->setGrandTotal($order->getGrandTotal() + ($totalMismatch/100))
                ->setBaseGrandTotal($order->getBaseGrandTotal() + ($totalMismatch/100));
        }
        /////////////////////////////////////////////////////////////////////////
    }

    /**
     * Associate order to customer when placing on product detail page
     * @param $orderIncrementId
     */
    protected function associateOrderToCustomerWhenPlacingOnPDP($orderIncrementId){
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $order->setCustomerId($customer->getId())
            ->setCustomerEmail($customer->getEmail())
            ->setCustomerFirstname($customer->getFirstname())
            ->setCustomerLastname($customer->getLastname())
            ->setCustomerIsGuest(0)
            ->setCustomerGroupId($customer->getGroupId());

        $order->save();
    }

    /**
     * Convenience method for getting a quote by ID
     *
     * @param int $quoteId  The ID of the quote to retrieve
     *
     * @return Mage_Sales_Model_Quote   the quote found by ID or an empty quote ob
     *                                  whose ID will be null
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

        /////////////////////////////////////////////////////////////////////////////
        /// The Bolt server design is limited in that it immutably sets the
        /// discount value prior to calculating shipping and tax.  We handle this
        /// problem by modifying shipping and tax totals in order to arrive at the
        /// correct bottom line grand total.  However, as a result, we can no longer
        /// validate at the subtotal level if discounts are supposed to be applied
        /// to the tax and are based on percentage.  This is a valid standard
        /// Magento configuration possibility.  If this possibility is detected,
        /// we simplify by flagging to skip subtotal validation and rely on
        /// bottom line grand total validation.
        /////////////////////////////////////////////////////////////////////////////
        $transaction->shouldSkipDiscountAndShippingTotalValidation = false;
        $cachedRules = [];


        foreach (explode(',', $immutableQuote->getAppliedRuleIds()) as $appliedRuleId ) {
                /** @var Mage_SalesRule_Model_Rule $rule */
            $rule = Mage::getModel('salesrule/rule')->load($appliedRuleId);
            $cachedRules[$appliedRuleId] = $rule;
            $percentDiscountWasCalculatedWithTax = $rule->getApplyToShipping() ||
                (
                    Mage::getStoreConfigFlag('tax/calculation/discount_tax') &&
                    in_array($rule->getSimpleAction(), [Mage_SalesRule_Model_Rule::TO_PERCENT_ACTION, Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION])
                );

                if ( $percentDiscountWasCalculatedWithTax ) {
                    $transaction->shouldSkipDiscountAndShippingTotalValidation = true;
                    break;
                }

        }

        /////////////////////////////////////////////////////////////////////////////


        /*
         * Validation of shopping cart rules and coupons, as opposed to catalog rules
         *
         * Natively, Magento only supports one coupon code per order, but we can build
         * basic support here for plugins like Amasty or custom solutions that implement
         * multiple coupons.
         *
         * Here, we use "," to delimit multiple coupon codes like Amasty and popular multi-coupon
         * custom code strategies.  This implementation also supports standard magento single coupon
         * format.
         */
        if (!@$transaction->order->cart->discounts) {
            return;
        }

        foreach($transaction->order->cart->discounts as $boltCoupon) {

            if (@$boltCoupon->reference) {
                $magentoCoupon = Mage::getModel('salesrule/coupon')->load($boltCoupon->reference, 'code');
                $couponExists = (bool) $magentoCoupon->getId();

                if ($couponExists) {
                    $magentoCouponCodes = $immutableQuote->getCouponCode()
                        ? explode(',', strtolower((string) $immutableQuote->getCouponCode()))
                        : array();
                    if (!in_array(strtolower($boltCoupon->reference), $magentoCouponCodes)) {
                        /** @var Mage_SalesRule_Model_Rule $rule */
                        $rule = (@$cachedRules[$magentoCoupon->getRuleId()]) ?: Mage::getModel('salesrule/rule')->load($magentoCoupon->getRuleId());
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

    /**
     * Verifies that the expected totals stored on Bolt have not changed in the Magento order prior to order creation
     *
     * @param Mage_Sales_Model_Quote $immutableQuote    Copy of the Magento session quote used by Bolt
     * @param object                 $transaction       The Bolt transaction object sent from the Bolt server
     *
     * @throws Bolt_Boltpay_OrderCreationException upon failure of price consistency validation
     */
    protected function validateTotals(Mage_Sales_Model_Quote $immutableQuote, $transaction)
    {
        $transaction->shouldDoTaxTotalValidation = true;
        $transaction->shouldDoDiscountTotalValidation = true;
        $transaction->shouldDoShippingTotalValidation = true;

        Mage::dispatchEvent(
            'bolt_boltpay_validate_totals_before',
            array(
                'quote'=> $immutableQuote,
                'transaction' => $transaction,
            )
        );

        $magentoTotals = $immutableQuote->getTotals();

        foreach ($transaction->order->cart->items as $boltCartItem) {

            $cartItem = $immutableQuote->getItemById($boltCartItem->reference);
            if ( !($cartItem instanceof Mage_Sales_Model_Quote_Item) ) { continue; }

            $cartItem->shouldNotBeValidated = false;
            Mage::dispatchEvent(
                'bolt_boltpay_cart_item_total_validation_before',
                [
                    'cart_item' => $cartItem,
                    'quote' => $immutableQuote,
                    'transaction' => $transaction
                ]
            );

            if ( $cartItem->shouldNotBeValidated ){ continue; }

            $boltPrice = (int)$boltCartItem->total_amount->amount;
            $magentoRowPrice = (int) ( $cartItem->getRowTotalWithDiscount() * 100 );
            $magentoCalculatedPrice = (int) round($cartItem->getCalculationPrice() * 100 * $cartItem->getQty());

            if ( !in_array($boltPrice, [$magentoRowPrice, $magentoCalculatedPrice]) ) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED,
                    OCE::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED_TMPL,
                    array($cartItem->getProductId(), $boltPrice, $magentoCalculatedPrice)
                );
            }
        }

        /////////////////////////////////////////////////////////////////////////
        /// Historically, we have honored a price tolerance of 1 cent on
        /// an order due calculations outside of the Magento framework context
        /// for discounts, shipping and tax.  We must still respect this feature
        /// and adjust the order final price according to fault tolerance which
        /// will now default to 0 cent unless a hidden option overrides this value
        /////////////////////////////////////////////////////////////////////////
        $priceFaultTolerance = $this->boltHelper()->getExtraConfig('priceFaultTolerance');

        if ($transaction->shouldDoTaxTotalValidation) {
            $magentoTaxTotal = (int)(( @$magentoTotals['tax']) ? round($magentoTotals['tax']->getValue() * 100) : 0 );
            $boltTaxTotal = (int)$transaction->order->cart->tax_amount->amount;
            $difference = abs($magentoTaxTotal - $boltTaxTotal);
            if ( $difference > $priceFaultTolerance ) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_CART_HAS_EXPIRED,
                    OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_TAX,
                    array($boltTaxTotal, $magentoTaxTotal)
                );
            } else if ($difference) {
                $message = "Tax differed by $difference cents.  Bolt: $boltTaxTotal | Magento: $magentoTaxTotal";
                $this->boltHelper()->logWarning($message);
                $this->boltHelper()->notifyException(new Exception($message), [], 'warning' );
            }
        }

        if ($transaction->shouldSkipDiscountAndShippingTotalValidation) return;

        if ($transaction->shouldDoDiscountTotalValidation) {
            $magentoDiscountTotal = (int)(($immutableQuote->getBaseSubtotal() - $immutableQuote->getBaseSubtotalWithDiscount()) * 100);
            $boltDiscountTotal = (int)$transaction->order->cart->discount_amount->amount;
            $difference = abs($magentoDiscountTotal - $boltDiscountTotal);
            if ( $difference > $priceFaultTolerance ) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_CART_HAS_EXPIRED,
                    OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_DISCOUNT,
                    array($boltDiscountTotal, $magentoDiscountTotal)
                );
            } else if ($difference) {
                $message = "Discount differed by $difference cents.  Bolt: $boltDiscountTotal | Magento: $magentoDiscountTotal";
                $this->boltHelper()->logWarning($message);
                $this->boltHelper()->notifyException(new Exception($message), [], 'warning' );
            }
        }

        if ( $transaction->shouldDoShippingTotalValidation && !$immutableQuote->isVirtual() ) {
            $shippingAddress = $immutableQuote->getShippingAddress();
            $magentoShippingTotal = (int) (($shippingAddress->getShippingAmount() - $shippingAddress->getBaseShippingDiscountAmount()) * 100);
            $boltShippingTotal = (int)$transaction->order->cart->shipping_amount->amount;
            $difference = abs($magentoShippingTotal - $boltShippingTotal);
            if ( $difference > $priceFaultTolerance ) {
                throw new Bolt_Boltpay_OrderCreationException(
                    OCE::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED,
                    OCE::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED_TMPL,
                    array($boltShippingTotal, $magentoShippingTotal)
                );
            } else if ($difference) {
                $message = "Shipping differed by $difference cents.  Bolt: $boltShippingTotal | Magento: $magentoShippingTotal";
                $this->boltHelper()->logWarning($message);
                $this->boltHelper()->notifyException(new Exception($message), [], 'warning' );
            }

            // Shipping Tax totals is used for supplying the total tax total for rounding error purposes.  Therefore,
            // we do not validate the shipping tax total. We only validate the full tax total
        }

        Mage::dispatchEvent(
            'bolt_boltpay_validate_totals_after',
            array(
                'quote' => $immutableQuote,
                'transaction' => $transaction,
            )
        );
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
     * Called after pre-auth order is confirmed as authorized on Bolt.
     *
     * @param Mage_Sales_Model_Order|string $order      the order or the customer facing order id
     * @param object|string                 $payload    payload sent from Bolt
     *
     * @throws Mage_Core_Exception if there is a problem retrieving the bolt transaction reference from the payload
     */
    public function receiveOrder( $order, $payload ) {
        /** @var Mage_Sales_Model_Order $order */
        $order = is_object($order) ? $order : Mage::getModel('sales/order')->loadByIncrementId($order);
        $payloadObject = is_object($payload) ? $payload : json_decode($payload);
        $immutableQuote = $this->getQuoteFromOrder($order);

        Mage::dispatchEvent('bolt_boltpay_order_received_before', array('order'=>$order, 'payload' => $payloadObject));

        $this->activateOrder($order, $payloadObject);
        $this->setBoltUserId($immutableQuote);

        Mage::dispatchEvent('bolt_boltpay_order_received_after', array('order'=>$order, 'payload' => $payloadObject));
    }

    /**
     * Performs the appropriate actions after a pre-auth order is confirmed to be transitioned to a
     * standard Magento order.
     *
     * @param Mage_Sales_Model_Order $order           The order than is has a confirmed authorization that is still at Bolt
     * @param object                 $payloadObject   The payload which contains the Bolt transaction reference
     *
     * @throws Mage_Core_Exception if the bolt transaction reference is an object instead of expected string
     */
    private function activateOrder(Mage_Sales_Model_Order $order, $payloadObject)
    {
        if (empty($order->getCreatedAt())) { $order->setCreatedAt(Mage::getModel('core/date')->gmtDate())->save(); }
        $this->getParentQuoteFromOrder($order)->setIsActive(false)->save();
        $reference = @$payloadObject->transaction_reference ?: $payloadObject->reference;
        $order->getPayment()->setAdditionalInformation('bolt_reference', $reference)->save();

        try {
            $immutableQuote = $this->getQuoteFromOrder($order);
            $recurringPaymentProfiles = $immutableQuote->setTotalsCollectedFlag(true)->prepareRecurringPaymentProfiles();

            benchmark( 'Running independent merchant third-party code via checkout_submit_all_after');
            Mage::dispatchEvent(
                'checkout_submit_all_after',
                array('order' => $order, 'quote' => $immutableQuote, 'recurring_profiles' => $recurringPaymentProfiles)
            );
            benchmark( "Finished running independent merchant third-party code via checkout_submit_all_after" );
        } catch ( Exception $e ) {
            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logException($e);
        }

        $this->sendOrderEmail($order);
    }

    /**
     *  Adds the Bolt User Id to a newly registered customer.
     *
     * @param $quote    The quote copy used to create the Bolt order
     */
    private function setBoltUserId($quote)
    {
        $session = Mage::getSingleton('customer/session');

        try {
            $customer = $quote->getCustomer();
            $boltUserId = $session->getBoltUserId();

            if ($customer != null && $boltUserId != null) {
                if ($customer->getBoltUserId() == null || $customer->getBoltUserId() == 0) {
                    //Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Adding bolt_user_id to the customer from the quote", null, 'bolt.log');
                    $customer->setBoltUserId($boltUserId);
                    $customer->save();
                }
            }
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logException($e);
        }

        $session->unsBoltUserId();
    }
    
    /**
     * Sends an email if an order email has not already been sent.
     *
     * @param $order Mage_Sales_Model_Order     The order which has just been authorized
     */
    public function sendOrderEmail($order)
    {
        try {
            if (!$order->getEmailSent()) {
                $order->queueNewOrderEmail();
                $history = $order->addStatusHistoryComment( $this->boltHelper()->__('Email sent for order %s', $order->getIncrementId()) );
                $history->setIsCustomerNotified(true);
            }
        } catch (Exception $e) {
            // Catches errors that occur when sending order email confirmation (e.g. external API is down)
            // and allows order creation to complete.
            $error = new Exception('Failed to send order email', 0, $e);
            $this->boltHelper()->notifyException($error);
            return;
        }
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

    /**
     * Removes a bolt order from the system.  The order is expected to be a Bolt order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @throws Mage_Core_Exception if the order cannot be canceled
     */
    public function removePreAuthOrder($order) {
        if ($this->isBoltOrder($order)) {

            $parentQuote = $this->getParentQuoteFromOrder($order);

            ##############################################################
            # Cancel order for restocking inventory and triggering events
            ##############################################################
            if ($order->getStatus() !== 'canceled_bolt') {
                $order->setQuoteId(null)->save();
                $order->cancel()->setStatus('canceled_bolt')->save();
            }
            ##############################################################

            ###########################################################
            # Permanently delete order
            ###########################################################
            if ($this->boltHelper()->getExtraConfig('keepPreAuthOrders')) {return;}
            $previousStoreId = Mage::app()->getStore()->getId();
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            $order->delete();
            Mage::app()->setCurrentStore($previousStoreId);
            ###########################################################

            ##########################################################
            # Unblock quote to allow order to be re-processed
            ##########################################################
            $parentQuote->setIsActive(true)->save();
            ###########################################################
        }
    }

    /**
     * Determines whether a given order is owned by Bolt
     *
     * @param Mage_Sales_Model_Order    $order  the magento order to be inspected
     *
     * @return bool true if the payment method for this order is currently set to Bolt, otherwise false
     */
    public function isBoltOrder($order) {
        return (strtolower($order->getPayment()->getMethod()) === Bolt_Boltpay_Model_Payment::METHOD_CODE);
    }

    /**
     * Add user note as a status history comment. It will be visible in admin and front
     *
     * @param Mage_Sales_Model_Order $order    Order in which the comment needs to be set
     * @param string                 $userNote The comment entered by the customer via the Bolt modal
     *
     * @return Mage_Sales_Model_Order Order object with comment set
     */
    public function setOrderUserNote($order, $userNote)
    {
        $order
            ->addStatusHistoryComment($userNote)
            ->setIsVisibleOnFront(true)
            ->setIsCustomerNotified(false);
        $order->save();

        return $order;
    }

    /**
     * To prevent common ERPs import of non-authorized orders, we remove timestamps until the order has been authorized
     *
     * @param Mage_Sales_Model_Order    $order  The order whose timestamps will be nullified
     */
    private function removeOrderTimeStamps($order) {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var Magento_Db_Adapter_Pdo_Mysql $writeConnection */
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('sales/order');

        $query = "UPDATE $table SET updated_at = NULL, created_at = NULL WHERE entity_id = :orderId";
        $bind = array(
            'orderId' => (int)$order->getId()
        );

        try {
            $writeConnection->query($query, $bind);
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->boltHelper()->notifyException($e, array(), 'warning');
            $this->boltHelper()->logWarning($e->getMessage());
        }
    }

}
