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
 * Class Bolt_Boltpay_Model_Order
 *
 * The Magento Model class that provides order related utility methods
 *
 */
class Bolt_Boltpay_Model_Order extends Mage_Core_Model_Abstract
{
    const MERCHANT_BACK_OFFICE = 'merchant_back_office';

    /**
     * Processes Magento order creation. Called from both frontend and API.
     *
     * @param string        $reference           Bolt transaction reference
     * @param int           $sessionQuoteId      Quote id, used if triggered from shopping session context,
     *                                           This will be null if called from within an API call context
     * @param boolean       $isAjaxRequest       If called by ajax request. default to false.
     * @param object        $transaction         pre-loaded Bolt Transaction object
     *
     * @return Mage_Sales_Model_Order   The order saved to Magento
     *
     * @throws Exception    thrown on order creation failure
     */
    public function createOrder($reference, $sessionQuoteId = null, $isAjaxRequest = false, $transaction = null)
    {
        try {
            if (empty($reference)) {
                throw new Exception(Mage::helper('boltpay')->__("Bolt transaction reference is missing in the Magento order creation process."));
            }

            $transaction = $transaction ?: Mage::helper('boltpay/api')->fetchTransaction($reference);

            /** @var Bolt_Boltpay_Helper_Transaction $transactionHelper */
            $transactionHelper = Mage::helper('boltpay/transaction');
            $immutableQuoteId = $transactionHelper->getImmutableQuoteIdFromTransaction($transaction);

            /* @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuoteId);

            // check that the order is in the system.  If not, we have an unexpected problem
            if ($immutableQuote->isEmpty()) {
                throw new Exception(Mage::helper('boltpay')->__("The expected immutable quote [$immutableQuoteId] is missing from the Magento system.  Were old quotes recently removed from the database?"));
            }

            if(!$this->storeHasAllCartItems($immutableQuote)){
                throw new Exception(Mage::helper('boltpay')->__("Not all items are available in the requested quantities."));
            }

            // check if this order is currently being processed.
            /* @var Mage_Sales_Model_Quote $parentQuote */
            $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuote->getParentQuoteId());

            /*
             * left it here temporarily because we may have some merchants that
             * should have it or have some problems without it.
             **/
            /*if ($parentQuote->isEmpty() ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The parent quote %s is unexpectedly missing.",
                        $immutableQuote->getParentQuoteId() )
                );
            } else if (!$parentQuote->getIsActive() && $transaction->indemnification_reason !== self::MERCHANT_BACK_OFFICE) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The parent quote %s for immutable quote %s is currently being processed or has been processed for order #%s. Check quote %s for details.",
                        $parentQuote->getId(), $immutableQuote->getId(), $parentQuote->getReservedOrderId(), $parentQuote->getParentQuoteId() )
                );
            } else {
                $parentQuote->setIsActive(false)->save();
            }*/

            // adding guest user email to order
            if (!$immutableQuote->getCustomerEmail()) {
                $email = $transaction->from_credit_card->billing_address->email_address;
                $immutableQuote->setCustomerEmail($email);
            }

                // explicitly set quote belong to guest if customer id does not exist
            $immutableQuote
                ->setCustomerIsGuest((($parentQuote && $parentQuote->getCustomerId()) ? false : true));

            // Set the firstname and lastname if guest customer.
            if ($immutableQuote->getCustomerIsGuest()) {
                $consumerData = $transaction->from_consumer;
                $immutableQuote
                    ->setCustomerFirstname($consumerData->first_name)
                    ->setCustomerLastname($consumerData->last_name);
            }
            $immutableQuote->save();

            //////////////////////////////////////////////////////////////////////////////////
            ///  Apply shipping address and shipping method data to quote directly from
            ///  the Bolt transaction.
            //////////////////////////////////////////////////////////////////////////////////
            $shippingMethodCode = null;
            if ($transaction->order->cart->shipments) {
                /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
                $shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
                $shippingAndTaxModel->applyShippingAddressToQuote($immutableQuote, $transaction->order->cart->shipments[0]->shipping_address);
                $shippingMethodCode = $transaction->order->cart->shipments[0]->reference;
            }

            $immutableQuote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
            $immutableQuote->getBillingAddress()->setShouldIgnoreValidation(true)->save();

            $shippingAddress = $immutableQuote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

            if ($shippingMethodCode) {
                $shippingAddress->setShippingMethod($shippingMethodCode)->save();
            } else {
                // Legacy transaction does not have shipments reference - fallback to $service field
                $service = $transaction->order->cart->shipments[0]->service;

                Mage::helper('boltpay')->collectTotals($immutableQuote);

                $rates = $shippingAddress->getAllShippingRates();

                $isShippingSet = false;
                foreach ($rates as $rate) {
                    if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() == $service
                        || (!$rate->getMethodTitle() && $rate->getCarrierTitle() == $service)) {
                        $shippingMethod = $rate->getCarrier() . '_' . $rate->getMethod();
                        $immutableQuote->getShippingAddress()->setShippingMethod($shippingMethod)->save();
                        $isShippingSet = true;
                        break;
                    }
                }

                if (!$isShippingSet) {
                    $errorMessage = Mage::helper('boltpay')->__('Shipping method not found');
                    $metaData = array(
                        'transaction'   => $transaction,
                        'rates' => $this->getRatesDebuggingData($rates),
                        'service' => $service,
                        'shipping_address' => var_export($shippingAddress->debug(), true),
                        'quote' => var_export($immutableQuote->debug(), true)
                    );
                    Mage::helper('boltpay/bugsnag')->notifyException(new Exception($errorMessage), $metaData);
                }
            }
            //////////////////////////////////////////////////////////////////////////////////

            //////////////////////////////////////////////////////////////////////////////////
            // set Bolt as payment method
            //////////////////////////////////////////////////////////////////////////////////
            $immutableQuote->getShippingAddress()->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            $payment = $immutableQuote->getPayment();
            $payment->setMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE);
            //////////////////////////////////////////////////////////////////////////////////

            Mage::helper('boltpay')->collectTotals($immutableQuote, true)->save();

            ////////////////////////////////////////////////////////////////////////////
            // reset increment id if needed
            ////////////////////////////////////////////////////////////////////////////
            /* @var Mage_Sales_Model_Order $preExistingOrder */
            $preExistingOrder = Mage::getModel('sales/order')->loadByIncrementId($parentQuote->getReservedOrderId());

            if (!$preExistingOrder->isObjectNew()) {
                ############################
                # First check if this order matches the transaction and therefore already created
                # If so, we can return it after notifying Bugsnag
                ############################
                $preExistingTransactionReference = $preExistingOrder->getPayment()->getAdditionalInformation('bolt_reference');
                if ($preExistingTransactionReference === $reference) {
                    Mage::helper('boltpay/bugsnag')->notifyException(
                        new Exception(Mage::helper('boltpay')->__("The order #%s has already been processed for this quote.", $preExistingOrder->getIncrementId())),
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
            $service = Mage::getModel('sales/service_quote', $immutableQuote);

            try {
                ///////////////////////////////////////////////////////
                /// These values are used in the observer after successful
                /// order creation
                ///////////////////////////////////////////////////////
                Mage::getSingleton('core/session')->setBoltTransaction($transaction);
                Mage::getSingleton('core/session')->setBoltReference($reference);
                Mage::getSingleton('core/session')->setWasCreatedByHook(!$isAjaxRequest);
                ///////////////////////////////////////////////////////

                $service->submitAll();
            } catch (Exception $e) {

                ///////////////////////////////////////////////////////
                /// Unset session values set above
                ///////////////////////////////////////////////////////
                Mage::getSingleton('core/session')->unsBoltTransaction();
                Mage::getSingleton('core/session')->unsBoltReference();
                Mage::getSingleton('core/session')->unsWasCreatedByHook();
                ///////////////////////////////////////////////////////

                Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                    array(
                        'transaction'   => json_encode((array)$transaction),
                        'quote_address' => var_export($immutableQuote->getShippingAddress()->debug(), true)
                    )
                );
                throw $e;
            }
            ////////////////////////////////////////////////////////////////////////////

        } catch ( Exception $e ) {
            // Order creation failed, so mark the parent quote as active so webhooks can retry it
            if (@$parentQuote) {
                $parentQuote->setIsActive(true)->save();
            }

            throw $e;
        }

        $order = $service->getOrder();
        $this->validateSubmittedOrder($order, $immutableQuote);

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

        Mage::getModel('boltpay/payment')->handleOrderUpdate($order);

        ///////////////////////////////////////////////////////
        /// Dispatch order save events
        ///////////////////////////////////////////////////////
        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order'=>$order, 'quote'=>$immutableQuote, 'transaction' => $transaction));

        Mage::dispatchEvent(
            'checkout_submit_all_after',
            array('order' => $order, 'quote' => $immutableQuote, 'recurring_profiles' => $service->getRecurringPaymentProfiles())
        );
        ///////////////////////////////////////////////////////

        if ($sessionQuoteId) {
            /** @var Mage_Checkout_Model_Session $checkoutSession */
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession
                ->clearHelperData();
//            if ($parentQuote) {
//                $checkoutSession
//                    ->setLastQuoteId($parentQuote->getId())
//                    ->setLastSuccessQuoteId($parentQuote->getId());
//            } else {
//                $checkoutSession
//                    ->setLastQuoteId($immutableQuote->getId())
//                    ->setLastSuccessQuoteId($immutableQuote->getId());
//            }
            $checkoutSession
                ->setLastQuoteId($parentQuote->getId())
                ->setLastSuccessQuoteId($parentQuote->getId());

            // add order information to the session
            $checkoutSession->setLastOrderId($order->getId())
                ->setRedirectUrl('')
                ->setLastRealOrderId($order->getIncrementId());
        }

        return $order;
    }

    /**
     * Determines whether the cart has either all items available if Manage Stock is yes for requested quantities,
     * or, if not, those items are eligible for back order.
     *
     * @var Mage_Sales_Model_Quote $quote   The quote that defines the cart
     *
     * @return bool true if the store can accept an order for all items in the cart,
     *              otherwise, false
     */
    public function storeHasAllCartItems($quote)
    {
        foreach ($quote->getAllItems() as $cartItem) {
            if($cartItem->getHasChildren()) {
                continue;
            }

            $_product = Mage::getModel('catalog/product')->load($cartItem->getProductId());
            $stockInfo = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
            if($stockInfo->getManageStock()){
                if( ($stockInfo->getQty() < $cartItem->getQty()) && !$stockInfo->getBackorders() ){
                    return false;
                }
            }
        }

        return true;
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

    protected function validateSubmittedOrder($order, $quote) {
        if(empty($order)) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'quote'  => var_export($quote->debug(), true),
                    'quote_address'  => var_export($quote->getShippingAddress()->debug(), true),
                )
            );

            throw new Exception(Mage::helper('boltpay')->__('Order is empty after call to Sales_Model_Service_Quote->submitAll()'));
        }
    }
}
