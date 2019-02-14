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

    protected $outOfStockSkus = null;
    protected $shouldPutOrderOnHold = false;
    protected $orderOnHoldMessage = '';
    protected $cartProducts = null;

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

            if (!$sessionQuoteId){
                /** @var Bolt_Boltpay_Helper_Data $boltHelperBase */
                $boltHelperBase = Mage::helper('boltpay');
                $sessionQuoteId = $immutableQuote->getParentQuoteId();

                $boltHelperBase->setCustomerSessionByQuoteId($sessionQuoteId);
            }

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

            // Explicitly set the billing name to the correct billing information saved in $transaction
            $billingFirstName = $transaction->from_credit_card->billing_address->first_name;
            $billingLastName = $transaction->from_credit_card->billing_address->last_name;
            $immutableQuote->getBillingAddress()->setFirstname($billingFirstName)->save();
            $immutableQuote->getBillingAddress()->setLastname($billingLastName)->save();

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

                if ($isShippingSet) {
                    Mage::dispatchEvent(
                        'bolt_boltpay_shipping_method_applied',
                        array(
                            'quote'=> $immutableQuote,
                            'shippingMethodCode' => $shippingMethod
                        )
                    );
                } else {
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

        if($this->allowDisabledSKUOrders()) {
            $immutableQuote->setIsSuperMode(true); // Allow an order to be created even if it has disabled products
        }

        return $immutableQuote;
    }

    /**
     * @return boolean
     */
    protected function allowDisabledSKUOrders()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/allow_disabled_sku_orders');
    }

    /**
     * @return boolean
     */
    protected function allowOutOfStockOrders()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/allow_out_of_stock_orders');
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    protected function validateProducts(Mage_Sales_Model_Quote $quote)
    {
        $outOfStockSKUs = $this->getOutOfStockSKUs($quote);
        if ($outOfStockSKUs) {
            $this->enableOutOfStockOrderToBeCreated();

            $errorMessage = Mage::helper('boltpay')->__("Product " .
                join(", ", $outOfStockSKUs) .
                (count($outOfStockSKUs) == 1 ? " is" : " are") .
                " out of stock. ");

            $this->appendOrderOnHoldMessage($errorMessage);
        }

        $disabledSKUs = $this->getDisabledSKUs($quote);
        if ($disabledSKUs) {
            $errorMessage = Mage::helper('boltpay')->__("Product " .
                join(", ", $disabledSKUs) .
                (count($disabledSKUs) == 1 ? " is" : " are") .
                " disabled. ");

            $this->appendOrderOnHoldMessage($errorMessage);
        }

        if ($this->shouldPutOrderOnHold()) {
            $invalidSKUs = array_unique(array_merge($outOfStockSKUs, $outOfStockSKUs));
            $errorMessage = Mage::helper('boltpay')->__("Please review " .
                (count($invalidSKUs) > 1 ? "them" : "it") .
                " and un-hold the order. ");

            $this->appendOrderOnHoldMessage($errorMessage);
        }
    }

    /**
     * @return boolean
     */
    protected function shouldPutOrderOnHold()
    {
        return $this->shouldPutOrderOnHold;
    }

    /**
     * @param $message
     *
     * @return void
     */
    protected function appendOrderOnHoldMessage($message)
    {
        $this->shouldPutOrderOnHold = true;
        $this->orderOnHoldMessage .= $message;
    }

    /**
     * @var Mage_Sales_Model_Quote $quote The quote that defines the cart
     *
     * @return array
     */
    public function getOutOfStockSKUs(Mage_Sales_Model_Quote $quote)
    {
        if($this->outOfStockSkus == null) {
            $this->outOfStockSkus = array();

            foreach($this->getCartProducts($quote) as $product) {
                $stockInfo = $product->getStockItem();
                if ($stockInfo->getManageStock()) {
                    if (($stockInfo->getQty() < $product->getCartItemQty()) && !$stockInfo->getBackorders()) {
                        $this->outOfStockSkus[] = $product->getSku();
                    }
                }
            }
        }

        return $this->outOfStockSkus;
    }

    /**
     * @var Mage_Sales_Model_Quote $quote The quote that defines the cart
     *
     * @return array
     */
    protected function getCartProducts(Mage_Sales_Model_Quote $quote)
    {
        if($this->cartProducts == null) {
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
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function getDisabledSKUs(Mage_Sales_Model_Quote $quote)
    {
        $disabledSKUs = array();

        foreach($this->getCartProducts($quote) as $product) {
            if ($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
                $disabledSKUs[] = $product->getSku();
            }
        }

        return $disabledSKUs;
    }

    protected function enableOutOfStockOrderToBeCreated()
    {
        try{
            Mage::app()->getStore()->setId(Mage_Core_Model_App::ADMIN_STORE_ID);
        }catch (\Exception $e){
            Mage::helper('boltpay/bugsnag')->notifyException($e);
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

    /**
     * @param \Mage_Sales_Model_Order $order
     */
    protected function setOrderOnHold(Mage_Sales_Model_Order $order)
    {
        $order->setHoldBeforeState($order->getState());
        $order->setHoldBeforeStatus($order->getStatus());
        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $this->orderOnHoldMessage);
    }
}
