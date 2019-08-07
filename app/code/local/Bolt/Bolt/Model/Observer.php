<?php

class Bolt_Bolt_Model_Observer extends Amasty_Rules_Model_Observer
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @param $observer
     * Process quote item validation and discount calculation
     * @return $this
     */
    public function handleValidation($observer)
    {
        $promotions =  Mage::getModel('amrules/promotions');
        $promotions->process($observer);
        return $this;
    }

    /**
     * Skips all In-Store pickup shipping options
     *
     * event: bolt_boltpay_shipping_method_applied_after
     *
     * @param $observer
     */
    function skipInStorePickup($observer) {
        $quote = $observer->getQuote();
        $shippingMethodCode = $observer->getShippingMethodCode();

        /** @var Mage_Sales_Model_Quote_Address_Rate $shippingRate */
        $shippingRate = Mage::getModel('sales/quote_address_rate')->load($shippingMethodCode, 'code');

        if (strpos($shippingRate->getMethodTitle(), 'In-Store Pick Up') !== false) {
            $quote->setShouldSkipThisShippingMethod(true);
        }
    }

    /**
     * Add time estimation to shipping labels
     *
     * event: bolt_boltpay_filter_shipping_label
     *
     * @param $observer
     */
    public function addShippingTimeToLabel($observer) {
        $valueWrapper = $observer->getValueWrapper();
        $shippingLabel = $valueWrapper->getValue();

        /** @var Mage_Sales_Model_Quote_Address_Rate $shippingRate */
        $shippingRate = $observer->getParameters();

        $carrier = $shippingRate->getCarrierTitle();
        $title = $shippingRate->getMethodTitle();

        // Apply adhoc rules to return concise string.
        if ($carrier === "Shipping Table Rates") {
            $methodId = explode('amtable_amtable', $shippingRate->getCode());
            $methodId = isset($methodId[1]) ? $methodId[1] : false;
            $estimateDelivery = $methodId ? Mage::helper('arrivaldates')->getEstimateHtml($methodId, false) : '';
            $shippingLabel =  $estimateDelivery ? $title . ' -- ' . $estimateDelivery : $title;
        }

        $valueWrapper->setValue($shippingLabel);
    }

    /**
     * Sets in-store pickup to the session at order creation time
     *
     * event: bolt_boltpay_order_creation_before
     * @param $observer
     */
    public function setInStorePickup($observer) {

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        /** @var Mage_Sales_Model_Quote $immutableQuote */
        $immutableQuote = $observer->getImmutableQuote();
        $transaction = $observer->getTransaction();

        $packagesToShip = @$transaction->order->cart->shipments;

        if ($immutableQuote->getStorePickupId()) {
            Mage::getSingleton('core/session')->setIsStorePickup(true);
            $immutableQuote->setIsVirtual(1)->save();
        }

    }

    /**
     * Sets in-store pickup to the Bolt order
     *
     * event: bolt_boltpay_filter_bolt_order
     * @param $observer
     */
    public function setInStorePickupToBoltOrder($observer) {

        $valueWrapper = $observer->getValueWrapper();
        $parameters = $observer->getParameters();

        $orderData = $valueWrapper->getValue();
        $quote = $parameters['quote'];

        if ($parameters['ísMultiPage']) {return;}

        if ($quote->getStorePickupId()) {
            Mage::getSingleton('core/session')->setIsStorePickup(true);
            $orderData['cart']['shipments'] = array(array(
                'shipping_address' => $orderData['cart']['billing_address'],
                'tax_amount'       => 0,
                'service'          => $this->boltHelper()->__('No Shipping Required'),
                'reference'        => "noshipping",
                'cost'             => 0
            ));
        }

        $valueWrapper->setValue($orderData);
    }

    /**
     * Adds the user note to the Magento created order
     *
     * event: bolt_boltpay_order_creation_after
     * @param $observer
     */
    public function addOrderNote($observer) {

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        $transaction = $observer->getTransaction();

        if (isset($transaction->order->user_note)) {
            Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                'customerordercomments', $transaction->order->user_note
            )->save();
            Mage::getSingleton('core/session')->unsBoltOnePageComments();
            return;
        }

        /** @var Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');
        $parentQuote = $orderModel->getParentQuoteFromOrder($order);

        if ($parentQuote->getCustomerNote()) {
            Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                'customerordercomments', $parentQuote->getCustomerNote()
            )->save();
        }

        Mage::getSingleton('core/session')->unsBoltOnePageComments();
    }

    /**
     * Adds the user note to the Bolt order data if it has already been set in the session
     *
     * event: bolt_boltpay_filter_bolt_order
     *
     * @param $observer
     */
    public function addNoteToBoltOrder($observer) {

        $valueWrapper = $observer->getValueWrapper();
        $orderData = $valueWrapper->getValue();
        $comments = Mage::getSingleton('core/session')->getBoltOnePageComments();

        if($comments) {
            $orderData['user_note'] = $comments;
        }

        $valueWrapper->setValue($orderData);
    }

    /**
     * Corrects discounts for proper validation
     *
     * event: bolt_boltpay_validate_totals_before
     * @param $observer
     */
    public function correctDiscountsForValidation($observer) {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();
        $transaction = $observer->getTransaction();

        if($quote->getAmGiftCardsAmount()){
            $transaction->order->cart->discount_amount->amount -=  ($quote->getAmGiftCardsAmountUsed() * 100);
        }

        if ($quote->getAmstcredUseCustomerBalance() && $quote->getAmstcredAmountUsed()) {
            $transaction->order->cart->discount_amount->amount -= ($quote->getAmstcredAmountUsed() * 100);
        }
    }

    /**
     * Overrides the adjusted shipping amount when Amstay discounts and gift cards are used
     *
     * event: bolt_boltpay_filter_adjusted_shipping_amount
     *
     * @param $observer
     */
    public function adjustAmastyDiscount($observer) {

        $valueWrapper = $observer->getValueWrapper();
        $parameters = $observer->getParameters();

        $adjustedShippingAmount = $valueWrapper->getValue();
        $quote = $parameters['quote'];
        $originalDiscountTotal = $parameters['originalDiscountTotal'];

        $newDiscountTotal = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
        $adjustedShippingAmount = $quote->getShippingAddress()->getShippingAmount() + $originalDiscountTotal - $newDiscountTotal;

        if($quote->getAmGiftCardsAmountUsed()){
            $adjustedShippingAmount -= $quote->getAmGiftCardsAmountUsed();
        }

        if ($quote->getAmstcredUseCustomerBalance() && $quote->getAmstcredAmountUsed()) {
            $adjustedShippingAmount -= $quote->getAmstcredAmountUsed();
        }

        $valueWrapper->setValue($adjustedShippingAmount);
    }

    /**
     * Function get store credit balance of customer
     *
     * @param $customerId
     *
     * @return mixed
     */
    private function getStoreCreditBalance($customerId)
    {
        return Mage::getModel('amstcred/balance')->getCollection()
            ->addFilter('customer_id', $customerId)->getFirstitem()->getAmount();
    }
}
