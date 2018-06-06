<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_Observer
 *
 * This class implements order event behavior
 */
class Bolt_Boltpay_Model_Observer
{
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
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }

        $session->unsBoltUserId();
    }

    /**
     * @return Bolt_Boltpay_Helper_Api
     */
    private function getBoltApiHelper()
    {
        return Mage::helper('boltpay/api');
    }

    /**
     * Event handler called after a save event.
     * Calls the complete authorize Bolt end point to confirm the order is valid.
     * If the order has been changed between the creation on Bolt end and the save in Magento
     * an order info message is recorded to inform the merchant and a bugsnag notification sent to Bolt.
     *
     * @param $observer
     * @throws Exception
     *
     */
    public function verifyOrderContents($observer) 
    {
        $boltHelper = $this->getBoltApiHelper();
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $transaction = $observer->getEvent()->getTransaction();

        $payment = $quote->getPayment();
        $method = $payment->getMethod();

        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {

            $reference = $payment->getAdditionalInformation('bolt_reference');
            $magentoTotal = Mage::getStoreConfig('tax/sales_display/grandtotal', $order->getStoreId()) ? (int)($order->getGrandTotal()*100) : (int)(($order->getGrandTotal()+$order->getTaxAmount())*100);

            if ( $magentoTotal !== $transaction->amount->amount)  {

                $message = "THERE IS A MISMATCH IN THE ORDER PAID AND ORDER RECORDED.<br>PLEASE COMPARE THE ORDER DETAILS WITH THAT RECORD IN YOUR BOLT MERCHANT ACCOUNT AT: ";
                $message .= Mage::getStoreConfig('payment/boltpay/test') ? "https://merchant-sandbox.bolt.com" : "https://merchant.bolt.com";
                $message .= "/transaction/$reference";
                $message .= "<br/>Bolt reports ".($transaction->amount->amount/100).'. Magento expects '.$magentoTotal/100;

                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $message)
                    ->save();

                $metaData = array(
                    'process'   => "order verification",
                    'reference'  => $reference,
                    'quote_id'   => $quote->getId(),
                    'display_id' => $order->getIncrementId(),
                );
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception($message), $metaData);
           }

            $this->sendOrderEmail($order);
            $order->save();
        }
    }

    public function sendCompleteAuthorizeRequest($request)
    {
        return $this->getBoltApiHelper()->transmit('complete_authorize', $request);
    }

    /**
     * @param $order Mage_Sales_Model_Order
     */
    public function sendOrderEmail($order)
    {
        $order->sendNewOrderEmail();
        $history = $order->addStatusHistoryComment('Email sent for order ' . $order->getIncrementId());
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
     * Updates the Bolt transaction status on order status change.
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
                    if ($order->getTotalPaid() >= .01) {
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
            return Mage::helper('sales')->__('Magento Order ID: "%s".', $incrementId);
        }

        return '';
    }
}