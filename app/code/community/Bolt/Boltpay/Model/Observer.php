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
     * Event handler called after a save event.
     * Adds the Bolt User Id to the newly registered customer.
     *
     * @param $observer
     * @throws Exception
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
        }

        $session->unsBoltUserId();
    }

    /**
     * Event handler called after a save event.
     * Calls the complete authorize Bolt end point to confirm the order is valid.
     * If the order has been changed between the creation on Bolt end and the save in Magento
     * an order info message is recorded to inform the merchant and a bugsnag notification sent to Bolt.
     *
     * @param $observer
     * @throws Exception
     */
    public function verifyOrderContents($observer)
    {
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $transaction = $observer->getEvent()->getTransaction();
        $payment = $quote->getPayment();
        $method = $payment->getMethod();
        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            $reference = $payment->getAdditionalInformation('bolt_reference');
            $magentoTotal = (int)(round($order->getGrandTotal() * 100));
            if ($magentoTotal !== $transaction->amount->amount)  {
                $message = $this->boltHelper()->__("THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>
                           PLEASE COMPARE THE ORDER DETAILS WITH THAT RECORD IN YOUR BOLT MERCHANT ACCOUNT AT: %s/transaction/%s<br/>
                           Bolt reports %s. Magento expects %s", $this->boltHelper()->getBoltMerchantUrl(),
                    $reference, ($transaction->amount->amount/100), ($magentoTotal/100) );

                # Adjust amount if it is off by only one cent, likely due to rounding
                $difference = $transaction->amount->amount - $magentoTotal;
                if ( abs($difference) == 1) {
                    $order->setTaxAmount($order->getTaxAmount() + ($difference/100))
                        ->setBaseTaxAmount($order->getBaseTaxAmount() + ($difference/100))
                        ->setGrandTotal($order->getGrandTotal() + ($difference/100))
                        ->setBaseGrandTotal($order->getBaseGrandTotal() + ($difference/100))
                        ->save();
                } else {
                    # Total differs by more than one cent, so we put the order on hold.
                    $order->setHoldBeforeState($order->getState());
                    $order->setHoldBeforeStatus($order->getStatus());
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $message)
                        ->save();
                }
                $metaData = array(
                    'process'   => "order verification",
                    'reference'  => $reference,
                    'quote_id'   => $quote->getId(),
                    'display_id' => $order->getIncrementId(),
                );
                $this->boltHelper()->notifyException(new Exception($message), $metaData);
            }
            $this->sendOrderEmail($order);
            $order->save();
        }
    }

    /**
     * Clears the Shopping Cart after the success page
     *
     * Event: checkout_onepage_controller_success_action
     */
    public function clearShoppingCart() {
        $cartHelper = Mage::helper('checkout/cart');
        $cartHelper->getCart()->truncate()->save();
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
                case Bolt_Boltpay_Model_Payment::ORDER_DEFERRED:
                    $payment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE);
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
}
