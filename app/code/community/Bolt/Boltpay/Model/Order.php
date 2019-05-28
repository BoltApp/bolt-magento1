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
 * Class Bolt_Boltpay_Model_Order
 *
 * The Magento Model class that provides order related utility methods
 *
 */
class Bolt_Boltpay_Model_Order extends Bolt_Boltpay_Model_Abstract
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
                $msg = $this->boltHelper()->__("Bolt transaction reference is missing in the Magento order creation process.");
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            }

            $transaction = $transaction ?: $this->boltHelper()->fetchTransaction($reference);

            $immutableQuoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);
            $immutableQuote = $this->getQuoteById($immutableQuoteId);

            if (!$sessionQuoteId){
                /** @var Bolt_Boltpay_Helper_Data $boltHelperBase */
                $boltHelperBase = $this->boltHelper();
                $sessionQuoteId = $immutableQuote->getParentQuoteId();

                $boltHelperBase->setCustomerSessionByQuoteId($sessionQuoteId);
            }

            // check that the order is in the system.  If not, we have an unexpected problem
            if ($immutableQuote->isEmpty()) {
                $msg = $this->boltHelper()->__("The expected immutable quote [$immutableQuoteId] is missing from the Magento system.  Were old quotes recently removed from the database?");
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            }

            // check if the quotes matches, frontend only
            if ( $sessionQuoteId && ($sessionQuoteId != $immutableQuote->getParentQuoteId()) ) {
                $msg = $this->boltHelper()->__("The Bolt order reference does not match the current cart ID. Cart ID: [%s]  Bolt Reference: [%s]", $sessionQuoteId , $immutableQuote->getParentQuoteId());
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            }

            // check if this order is currently being proccessed.  If so, throw exception
            $parentQuote = $this->getQuoteById($immutableQuote->getParentQuoteId());
            if ($parentQuote->isEmpty()) {
                $msg = $this->boltHelper()->__("The parent quote %s is unexpectedly missing.", $immutableQuote->getParentQuoteId());
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            } else if (!$parentQuote->getIsActive() && $transaction->indemnification_reason !== self::MERCHANT_BACK_OFFICE) {
                $msg = $this->boltHelper()->__("The parent quote %s for immutable quote %s is currently being processed or has been processed for order #%s. Check quote %s for details.",
                    $parentQuote->getId(), $immutableQuote->getId(), $parentQuote->getReservedOrderId(), $parentQuote->getParentQuoteId());
                $this->boltHelper()->logWarning($msg);
                throw new Exception($msg);
            } else {
                $parentQuote->setIsActive(false)->save();
            }

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
                    $service = $packagesToShip[0]->service;

                    $this->boltHelper()->collectTotals($immutableQuote);

                    $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
                    $rates = $shippingAddress->getAllShippingRates();

                    foreach ($rates as $rate) {
                        if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() == $service
                            || (!$rate->getMethodTitle() && $rate->getCarrierTitle() == $service)) {
                            $shippingMethodCode = $rate->getCarrier() . '_' . $rate->getMethod();
                            break;
                        }
                    }
                }

                if ($shippingMethodCode) {
                    $shippingAndTaxModel->applyShippingRate($immutableQuote, $shippingMethodCode);
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
                        'service' => $service,
                        'shipping_address' => var_export($shippingAddress->debug(), true),
                        'quote' => var_export($immutableQuote->debug(), true)
                    );
                    $this->boltHelper()->logWarning($errorMessage);
                    $this->boltHelper()->notifyException(new Exception($errorMessage), $metaData);
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

            $this->boltHelper()->collectTotals($immutableQuote, true)->save();

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
                if ( $preExistingTransactionReference === $reference ) {
                    $this->boltHelper()->notifyException(
                        new Exception( $this->boltHelper()->__("The order #%s has already been processed for this quote.", $preExistingOrder->getIncrementId() ) ),
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
                $order = $service->getOrder();

                // Add the user_note to the order comments and make it visible for customer.
                if (isset($transaction->order->user_note)) {
                    $this->setOrderUserNote($order, '[CUSTOMER NOTE] ' . $transaction->order->user_note);
                }
            } catch (Exception $e) {

                ///////////////////////////////////////////////////////
                /// Unset session values set above
                ///////////////////////////////////////////////////////
                Mage::getSingleton('core/session')->unsBoltTransaction();
                Mage::getSingleton('core/session')->unsBoltReference();
                Mage::getSingleton('core/session')->unsWasCreatedByHook();
                ///////////////////////////////////////////////////////

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

        } catch ( Exception $e ) {
            // Order creation failed, so mark the parent quote as active so webhooks can retry it
            if (@$parentQuote) {
                $parentQuote->setIsActive(true)->save();
            }
            $this->boltHelper()->logException($e);
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
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession
                ->clearHelperData();
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
     * @param $quoteId
     *
     * @return \Mage_Sales_Model_Quote
     */
    protected function getQuoteById($quoteId)
    {
        /* @var Mage_Sales_Model_Quote $immutableQuote */
        $immutableQuote = Mage::getModel('sales/quote')
            ->getCollection()
            ->addFieldToFilter('entity_id', $quoteId)
            ->getFirstItem();

        return $immutableQuote;
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
            if ($order->getStatus() !== 'canceled_bolt') {
                $order->cancel()->setQuoteId(null)->setStatus('canceled_bolt')->save();
            }
            $previousStoreId = Mage::app()->getStore()->getId();
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            $order->delete();
            Mage::app()->setCurrentStore($previousStoreId);
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

    protected function validateSubmittedOrder($order, $quote) {
        if(empty($order)) {
            $this->boltHelper()->addBreadcrumb(
                array(
                    'quote'  => var_export($quote->debug(), true),
                    'quote_address'  => var_export($quote->getShippingAddress()->debug(), true),
                )
            );

            throw new Exception($this->boltHelper()->__('Order is empty after call to Sales_Model_Service_Quote->submitAll()'));
        }
    }

    /**
     * @param \Mage_Sales_Model_Order $order
     */
    protected function setOrderOnHold(Mage_Sales_Model_Order $order)
    {
        $order->setHoldBeforeState($order->getState());
        $order->setHoldBeforeStatus($order->getStatus());
        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $this->orderOnHoldMessage);
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
        return $order;
    }
}