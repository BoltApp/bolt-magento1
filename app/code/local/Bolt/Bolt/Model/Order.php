<?php

class Bolt_Bolt_Model_Order extends Bolt_Boltpay_Model_Order
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
            $immutableQuote = $this->getQuoteById($immutableQuoteId);

            // check that the order is in the system.  If not, we have an unexpected problem
            if ($immutableQuote->isEmpty()) {
                throw new Exception(Mage::helper('boltpay')->__("The expected immutable quote [$immutableQuoteId] is missing from the Magento system.  Were old quotes recently removed from the database?"));
            }

            if(!$this->allowOutOfStockOrders() && !empty($this->getOutOfStockSKUs($immutableQuote))){
                throw new Exception(Mage::helper('boltpay')->__("Not all items are available in the requested quantities. Out of stock SKUs: %s", join(', ', $this->getOutOfStockSKUs($immutableQuote))));
            }

            // check if the quotes matches, frontend only
            if ( $sessionQuoteId && ($sessionQuoteId != $immutableQuote->getParentQuoteId()) ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The Bolt order reference does not match the current cart ID. Cart ID: [%s]  Bolt Reference: [%s]",
                        $sessionQuoteId , $immutableQuote->getParentQuoteId())
                );
            }

            // check if this order is currently being proccessed.  If so, throw exception
            $parentQuote = $this->getQuoteById($immutableQuote->getParentQuoteId());
            if ($parentQuote->isEmpty()) {
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

            //////////////////////////////////////////////////////////////////////////////////
            ///  Apply shipping address and shipping method data to quote directly from
            ///  the Bolt transaction.
            //////////////////////////////////////////////////////////////////////////////////
            $shippingMethodCode = null;
            $shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
            if ($transaction->order->cart->shipments) {

                $shippingAndTaxModel->applyShippingAddressToQuote($immutableQuote, $transaction->order->cart->shipments[0]->shipping_address);
                $shippingMethodCode = $transaction->order->cart->shipments[0]->reference;
            }

            $immutableQuote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
            $immutableQuote->getBillingAddress()->setShouldIgnoreValidation(true)->save();

            if ($shippingMethodCode) {
                $immutableQuote->getShippingAddress()->setShippingMethod($shippingMethodCode)->save();
                Mage::dispatchEvent(
                    'bolt_boltpay_shipping_method_applied',
                    array(
                        'quote'=>$immutableQuote,
                        'shippingMethodCode' => $shippingMethodCode
                    )
                );
            } else {
                // Legacy transaction does not have shipments reference - fallback to $service field
                $service = $transaction->order->cart->shipments[0]->service;
                if ($service) {
                    Mage::helper('boltpay')->collectTotals($immutableQuote);

                    $shippingAddress = $immutableQuote->getShippingAddress();
                    $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
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

                    if (!$isShippingSet && !$immutableQuote->isVirtual()) {
                        Mage::dispatchEvent(
                            'bolt_boltpay_shipping_method_applied',
                            array(
                                'quote' => $immutableQuote,
                                'shippingMethodCode' => $shippingMethod
                            )
                        );
                    } else {
                        $errorMessage = Mage::helper('boltpay')->__('Shipping method not found');
                        $metaData = array(
                            'transaction' => $transaction,
                            'rates' => $this->getRatesDebuggingData($rates),
                            'service' => $service,
                            'shipping_address' => var_export($shippingAddress->debug(), true),
                            'quote' => var_export($immutableQuote->debug(), true)
                        );
                        Mage::helper('boltpay/bugsnag')->notifyException(new Exception($errorMessage), $metaData);
                    }
                }else{

                    if (!$immutableQuote->isVirtual()) {
                        if ($immutableQuote->getStorePickupId()) {
                            Mage::getSingleton('core/session')->setIsStorePickup(true);
                        }
                    }
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
                if ( $preExistingTransactionReference === $reference ) {
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

                $this->validateProducts($immutableQuote);
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

        if ($this->shouldPutOrderOnHold()) {
            $this->setOrderOnHold($order);
        }

        /** @var Bolt_Boltpay_Model_OrderFixer $orderFixer */
        $orderFixer = Mage::getModel('boltpay/orderFixer');
        $orderFixer->setupVariables($order, $transaction);
        if($orderFixer->requiresOrderUpdateToMatchBolt()) {
            $orderFixer->updateOrderToMatchBolt();
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
        try {

            if ($order->getId()) {
                if (isset($transaction->order->user_note)) {
                    Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                        'customerordercomments', $transaction->order->user_note
                    )->save();
                }

                if(Mage::getSingleton('core/session')->getBoltOnePageComments()) {
                    Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                        'customerordercomments', Mage::getSingleton('core/session')->getBoltOnePageComments()
                    )->save();
                    Mage::getSingleton('core/session')->unsBoltOnePageComments();
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $order;
    }

}
