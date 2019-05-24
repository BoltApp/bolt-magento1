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
 * Class Bolt_Boltpay_Model_Observer
 *
 * This class implements order event behavior
 */
class Bolt_Boltpay_Model_Observer
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Adds the Bolt User Id to the newly registered customer.
     *
     * event: bolt_boltpay_authorization_after
     *
     * @param Varien_Event_Observer $observer  Observer event contains `quote`
     */
    public function setBoltUserId($observer)
    {
        $quote = $observer->getEvent()->getQuote();
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
     * Event handler called after Bolt confirms order authorization
     *
     * event: bolt_boltpay_authorization_after
     *
     * @param Varien_Event_Observer $observer Observer event contains `quote`, `order`, and the bolt transaction `reference`
     *
     * @throws Mage_Core_Exception if the bolt transaction reference is an object instead of expected string
     */
    public function completeAuthorize($observer)
    {
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $reference = $observer->getEvent()->getReference();

        if (empty($order->getCreatedAt())) { $order->setCreatedAt(Mage::getModel('core/date')->gmtDate())->save(); }
        Mage::getModel('boltpay/order')->getParentQuoteFromOrder($order)->setIsActive(false)->save();
        $order->getPayment()->setAdditionalInformation('bolt_reference', $reference)->save();
        Mage::getModel('boltpay/order')->sendOrderEmail($order);
    }

    /**
     * Clears the Shopping Cart after the success page
     *
     * @param Varien_Event_Observer $observer   An Observer object with an empty event object*
     * Event: checkout_onepage_controller_success_action
     */
    public function clearShoppingCart($observer) {
        $cartHelper = Mage::helper('checkout/cart');
        $cartHelper->getCart()->truncate()->save();
    }

    /**
     * If the session quote has been flagged by having a parent quote Id equal to its own
     * id, this will clear the cart cache, which, in turn, forces the creation of a new Bolt order
     *
     * event: controller_front_init_before
     *
     * @param Varien_Event_Observer $observer event contains front (Mage_Core_Controller_Varien_Front)
     */
    public function clearCartCacheOnOrderCanceled($observer) {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($quote && is_int($quote->getId()) && $quote->getId() === $quote->getParentQuoteId()) {
            Mage::getSingleton('core/session')->unsCachedCartData();
            // clear the parent quote ID to re-enable cart cache
            $quote->setParentQuoteId(null);
        }
    }

    public function sendCompleteAuthorizeRequest($request)
    {
        return $this->boltHelper()->transmit('complete_authorize', $request);
    }

    /**
     * @param $order Mage_Sales_Model_Order
     */
    public function sendOrderEmail($order)
    {
        try {
            $order->sendNewOrderEmail();
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
     * Event handler called when bolt payment capture.
     * Add the message Magento Order Id: "xxxxxxxxx" to the standard payment capture message.
     *
     * @param $observer
     */
    public function addMessageWhenCapture($observer)
    {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $method = $payment->getMethod();
        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            $message = $this->_addMagentoOrderIdToMessage($order->getIncrementId());
            if (!empty($message)) {
                $observer->getEvent()->getPayment()->setPreparedMessage($message);
            }
        }
    }

    /**
     * Updates the Magento's interpretation of the Bolt transaction status on order status change.
     * Note: this transaction status is not necessarily same as order status on the Bolt server.
     * bolt_transaction_status field keeps track of payment status only from the magento plugin's perspective.
     *
     * @param $observer
     */
    public function updateBoltTransactionStatus($observer)
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();

        $method = $payment->getMethod();
        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {

            switch ($order->getState()) {
                case Mage_Sales_Model_Order::STATE_COMPLETE:
                case Mage_Sales_Model_Order::STATE_CLOSED:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED);
                    break;
                case Mage_Sales_Model_Order::STATE_PROCESSING:
                    if (!$order->getTotalDue()) {
                        $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED);
                    } else {
                        $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED);
                    }
                    break;
                case Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING);
                    break;
                case Mage_Sales_Model_Order::STATE_HOLDED:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD);
                    break;
                case Mage_Sales_Model_Order::STATE_CANCELED:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED);
                    break;
                case Mage_Sales_Model_Order::STATE_PENDING_PAYMENT:
                case Mage_Sales_Model_Order::STATE_NEW:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING);
                    break;
            }
            $payment->save();
        }
    }

    /**
     * Add Magento Order ID to the prepared message.
     *
     * @param number|string $incrementId
     * @return string
     */
    protected function _addMagentoOrderIdToMessage($incrementId)
    {
        if ($incrementId) {
            return $this->boltHelper()->__('Magento Order ID: "%s".', $incrementId);
        }

        return '';
    }

    /**
     * Sets the order's initial status according to Bolt and annotates it with creation and payment meta data
     *
     * @param Varien_Event_Observer $observer   the observer object which contains the order
     */
    public function setInitialOrderStatusAndDetails(Varien_Event_Observer $observer) {

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();

        $paymentMethod = $payment->getMethod();
        if (strtolower($paymentMethod) !== Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            return;
        }

        $reference = Mage::getSingleton('core/session')->getBoltReference();
        $transaction = Mage::getSingleton('core/session')->getBoltTransaction() ?: $this->boltHelper()->fetchTransaction($reference);

        $boltCartTotal = $transaction->amount->currency_symbol. ($transaction->amount->amount/100);
        $orderTotal = $order->getGrandTotal();

        $msg = $this->boltHelper()->__(
            "BOLT notification: Authorization requested for %s.  Order total is %s. Bolt transaction: %s/transaction/%s.", 
            $boltCartTotal, $transaction->amount->currency_symbol.$orderTotal, $this->boltHelper()->getBoltMerchantUrl(), $transaction->reference
        );

        if(Mage::getSingleton('core/session')->getWasCreatedByHook()){ // order is create via AJAX call
            $msg .= $this->boltHelper()->__("  This order was created via webhook (Bolt traceId: <%s>)", $this->boltHelper()->getBoltTraceId());
        }

        $order->setState(Bolt_Boltpay_Model_Payment::transactionStatusToOrderStatus($transaction->status), true, $msg)
            ->save();

        $order->getPayment()
            ->setAdditionalInformation('bolt_transaction_status', $transaction->status)
            ->setAdditionalInformation('bolt_reference', $transaction->reference)
            ->setAdditionalInformation('bolt_merchant_transaction_id', $transaction->id)
            ->setTransactionId($transaction->id)
            ->save();
    }

    /**
     * Hides the Bolt Pre-auth order states from the admin->Sales->Order list
     *
     * event: sales_order_grid_collection_load_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an orderGridCollection object
     */
    public function filterPreAuthOrders($observer) {
        if ($this->boltHelper()->getExtraConfig('displayPreAuthOrders')) { return; }

        /** @var Mage_Sales_Model_Resource_Order_Grid_Collection $orderGridCollection */
        $orderGridCollection = $observer->getEvent()->getOrderGridCollection();
        $orderGridCollection->addFieldToFilter('main_table.status',
            array(
                'nin'=>array(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
                )
            )
        );
    }

    /**
     * Prevents Magento from changing the Bolt preauth statuses
     *
     * event: sales_order_save_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an order object
     */
    public function safeguardPreAuthStatus($observer) {
        $order = $observer->getEvent()->getOrder();
        if (!Bolt_Boltpay_Helper_Data::$fromHooks && in_array($order->getOrigData('status'), array('pending_bolt','canceled_bolt')) ) {
            $order->setStatus($order->getOrigData('status'));
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
        /** @var  Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');
        $orderModel->validateBeforeOrderCommit($observer);
    }
}
