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
     * Sets in-store pickup to the session
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

        if (!$packagesToShip && !$immutableQuote->isVirtual()) {
            if ($immutableQuote->getStorePickupId()) {
                Mage::getSingleton('core/session')->setIsStorePickup(true);
            }
        }
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
     * Adds the user note to the Bolt order data if it has already been set in the session
     *
     * event: bolt_boltpay_filter_discount_amount
     *
     * @param $observer
     */
    public function adjustAmastyDiscount($observer) {

        $valueWrapper = $observer->getValueWrapper();
        $parameters = $observer->getParameters();

        $old = $discountAmount = $valueWrapper->getValue();
        $quote = $parameters['quote'];
        $discountType = $parameters['discount'];

        if($discountType=='amgiftcard'){
            $discountAmount = $quote->getAmGiftCardsAmount();
$this->boltHelper()->notifyException(new Exception("Old Amstay Gift Card Discount: $old, New: $discountAmount"));
        }elseif ($discountType == 'amstcred') {
            $customerId = $quote->getCustomer()->getId();
            if ($customerId) $discountAmount = $this->getStoreCreditBalance($customerId);
$this->boltHelper()->notifyException(new Exception("Old Amstay Credit Discount: $old, New: $discountAmount"));
        }

        $valueWrapper->setValue($discountAmount);
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
