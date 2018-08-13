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

class Bolt_Boltpay_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
    const METHOD_CODE               = 'boltpay';
    const TITLE                     = "Credit & Debit Card";
    const TITLE_ADMIN               = "Credit and Debit Card (Powered by Bolt)";

    // Order States
    const ORDER_DEFERRED = 'deferred';

    // Transaction States
    const TRANSACTION_AUTHORIZED = 'authorized';
    const TRANSACTION_CANCELLED = 'cancelled';
    const TRANSACTION_COMPLETED = 'completed';
    const TRANSACTION_PENDING = 'pending';
    const TRANSACTION_ON_HOLD = 'on-hold';
    const TRANSACTION_REJECTED_REVERSIBLE = 'rejected_reversible';
    const TRANSACTION_REJECTED_IRREVERSIBLE = 'rejected_irreversible';
    const TRANSACTION_REFUND = 'credit';
    const TRANSACTION_NO_NEW_STATE = 'no_new_state';
    const TRANSACTION_ALL_STATES = 'all_states';

    const HOOK_TYPE_AUTH = 'auth';
    const HOOK_TYPE_CAPTURE = 'capture';
    const HOOK_TYPE_REJECTED_REVERSIBLE = 'rejected_reversible';
    const HOOK_TYPE_REJECTED_IRREVERSIBLE = 'rejected_irreversible';
    const HOOK_TYPE_PAYMENT = 'payment';
    const HOOK_TYPE_PENDING = 'pending';
    const HOOK_TYPE_VOID = 'void';
    const HOOK_TYPE_REFUND = 'credit';

    const CAPTURE_TYPE = 'online';

    protected $_code               = self::METHOD_CODE;
    protected $_formBlockType      = 'boltpay/form';
    protected $_infoBlockType      = 'boltpay/info';
    protected $_allowCurrencyCode  = array('USD');

    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseCheckout              = true;
    protected $_canFetchTransactionInfo     = true;

    protected $_canManageRecurringProfiles  = false;
    protected $_canCapturePartial           = true;
    protected $_canCaptureOnce              = true;
    // TODO: This can be set to true and we could move the handleOrderUpdate method
    protected $_canOrder                    = false;
    protected $_canUseInternal              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_isGateway                   = false;
    protected $_isInitializeNeeded          = false;

    protected $_validStateTransitions = array(
        self::TRANSACTION_AUTHORIZED => array(self::TRANSACTION_COMPLETED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_PENDING),
        self::TRANSACTION_COMPLETED => array(self::TRANSACTION_REFUND, self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_PENDING => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_ON_HOLD => array(self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE),
        self::TRANSACTION_REJECTED_IRREVERSIBLE => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_REJECTED_REVERSIBLE => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_CANCELLED => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_REFUND => array(self::TRANSACTION_REFUND,self::TRANSACTION_NO_NEW_STATE)
    );

    // There is no hook when the transaction Authorize fails.
    public static $_hookTypeToStatusTranslator = array(
        self::HOOK_TYPE_AUTH => self::TRANSACTION_AUTHORIZED,
        self::HOOK_TYPE_CAPTURE => self::TRANSACTION_COMPLETED,
        self::HOOK_TYPE_PAYMENT => self::TRANSACTION_COMPLETED,
        self::HOOK_TYPE_PENDING => self::TRANSACTION_PENDING,
        self::HOOK_TYPE_REJECTED_REVERSIBLE => self::TRANSACTION_REJECTED_REVERSIBLE,
        self::HOOK_TYPE_REJECTED_IRREVERSIBLE => self::TRANSACTION_REJECTED_IRREVERSIBLE,
        self::HOOK_TYPE_VOID => self::TRANSACTION_CANCELLED,        
        self::HOOK_TYPE_REFUND => self::TRANSACTION_REFUND
    );


    /**
     * Bolt_Boltpay_Model_Payment constructor.
     *
     * Allows transitions from on-hold from the non-webhook context
     */
    public function __construct()
    {
        if (!Bolt_Boltpay_Helper_Api::$fromHooks) {
            $this->_validStateTransitions[self::TRANSACTION_ON_HOLD] = array(self::TRANSACTION_ALL_STATES);
        }
    }

    /**
     * @return bool
     */
    public function isAdminArea()
    {
        return (Mage::app()->getStore()->isAdmin() && Mage::getDesign()->getArea() === 'adminhtml');
    }

    public function getConfigData($field, $storeId = null)
    {
        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            if ($field == 'allowspecific' || $field == 'specificcountry') {
                return null;
            }
        }

        if ($field == 'title') {
            if ($this->isAdminArea()) {
                return self::TITLE_ADMIN;
            } else {
                return self::TITLE;
            }
        }

        return parent::getConfigData($field, $storeId);
    }

    public static function translateHookTypeToTransactionStatus($hookType)
    {
        $hookType = strtolower($hookType);
        if (array_key_exists($hookType, static::$_hookTypeToStatusTranslator)) {
            return static::$_hookTypeToStatusTranslator[$hookType];
        } else {
            $message = Mage::helper('boltpay')->__('Invalid hook type %s', $hookType);
            Mage::throwException($message);
        }
    }

    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {

        try {
            //Mage::log(sprintf('Initiating fetch transaction info on payment id: %d', $payment->getId()), null, 'bolt.log');
            if ($payment->getData('auto_capture')) {
                //Mage::log('Skipping calls for fetch transaction in auto capture mode', null, 'bolt.log');
                return;
            }

            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($merchantTransId == null) {
                $message = Mage::helper('boltpay')->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $boltHelper = Mage::helper('boltpay/api');
            $reference = $payment->getAdditionalInformation('bolt_reference');
            $response = $boltHelper->transmit($reference, null);
            if (strlen($response->status) == 0) {
                $message = Mage::helper('boltpay')->__('Bad fetch transaction response. Empty transaction status');
                Mage::throwException($message);
            }

            $transactionStatus = strtolower($response->status);
            $prevTransactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');
            $this->handleTransactionUpdate($payment, $transactionStatus, $prevTransactionStatus);
            //Mage::log(sprintf('Fetch transaction info completed for payment id: %d', $payment->getId()), null, 'bolt.log');
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Check whether BoltPay is available
     */
    public function isAvailable($quote = null)
    {
        if(!empty($quote)) {
            return Mage::helper('boltpay')->canUseBolt($quote);
        }

        return false;
    }

    /**
     * Bolt Authorize is a dummy authorize method since authorization is done by Bolt's checkout iframe
     * This authorize method merely keeps the authorization transaction record open
     * @param Varien_Object $payment
     * @param $amount
     * @return Bolt_Boltpay_Model_Payment
     * @throws Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        try {
            // Auth transactions need to be kept open to support cancelling/voiding transaction
            $payment->setIsTransactionClosed(false);
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function capture(Varien_Object $payment, $amount)
    {
        try {
            //Mage::log(sprintf('Initiating capture on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            // Get the merchant transaction id
            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');

            $reference = $payment->getAdditionalInformation('bolt_reference');

            $transactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');

            if ($merchantTransId == null && !$payment->getData('auto_capture')) {
                // If a capture is called with transaction status == Completed, it implies its
                // auto capture that is calling this function and hence there is no need
                // to call transaction update.
                $message = Mage::helper('boltpay')->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            if ($transactionStatus == self::TRANSACTION_AUTHORIZED) {
                $captureRequest = array(
                    'transaction_id' => $merchantTransId,
                    'amount' => $amount * 100,
                    'currency' => $payment->getOrder()->getOrderCurrencyCode()
                );
                $response = $boltHelper->transmit('capture', $captureRequest);
                if (strlen($response->status) == 0) {
                    $message = Mage::helper('boltpay')->__('Bad capture response. Empty transaction status');
                    Mage::throwException($message);
                }

                $responseStatus = $response->status;

                $this->_handleBoltTransactionStatus($payment, $responseStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);

                $payment->save();
            } elseif ($transactionStatus == self::TRANSACTION_COMPLETED) {
                $order = $payment->getOrder();

                $invoices = $order->getInvoiceCollection()->getItems();

                if ($this->_canCaptureOnce && sizeof($invoices) > 1) {
                    Mage::throwException( Mage::helper('boltpay')->__('Invoice capture attempt denied for order %s. The Bolt payment method only allows a single capture for each order.', $order->getIncrementId()) );
                }
            } else {
                $message = Mage::helper('boltpay')->__('Capture attempted denied. Transaction status: %s', $transactionStatus);
                Mage::throwException($message);
            }

            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-capture", $reference));
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function refund(Varien_Object $payment, $amount)
    {
        try {
            $boltTransactionWasRefundedByWebhook = $payment->getAdditionalInformation('bolt_transaction_was_refunded_by_webhook');
            if(!empty($boltTransactionWasRefundedByWebhook)){
                return $this;
            }            
            //Mage::log(sprintf('Initiating refund on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            $paymentInfo = $this->getInfoInstance();
            $order = $paymentInfo->getOrder();

            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($transId == null) {
                $message = Mage::helper('boltpay')->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $data = array(
                'transaction_id' => $transId,
                'amount' => $amount * 100,
                'currency' => $order->getOrderCurrencyCode(),
                'skip_hook_notification' => true,
            );
            $response = $boltHelper->transmit('credit', $data);

            if (strlen($response->reference) == 0) {
                $message = Mage::helper('boltpay')->__('Bad refund response. Empty transaction reference');
                Mage::throwException($message);
            }

            if (strlen($response->id) == 0) {
                $message = Mage::helper('boltpay')->__('Bad refund response. Empty transaction id');
                Mage::throwException($message);
            }

            $refundTransactionId = $response->id;
            $refundTransactionStatus = $response->status;
            $refundReference = $response->reference;
            $refundTransactionStatuses = $payment->getAdditionalInformation('bolt_refund_transaction_statuses');
            $refundTransactionIds = $payment->getAdditionalInformation('bolt_refund_merchant_transaction_ids');
            if (is_null($refundTransactionStatuses)) {
                $refundTransactionStatuses = array();
            } else {
                $refundTransactionStatuses = unserialize($refundTransactionStatuses);
            }

            if (is_null($refundTransactionIds)) {
                $refundTransactionIds = array();
            } else {
                $refundTransactionIds = unserialize($refundTransactionIds);
            }

            array_push($refundTransactionStatuses, $refundTransactionStatus);
            array_push($refundTransactionIds, $refundTransactionId);
            $msg = Mage::helper('boltpay')->__(
                "Bolt Operation: \"Refund\". Bolt Reference: \"%s\".\nBolt Transaction: \"%s\"",
                $refundReference,
                $refundTransactionId
            );
            $payment->getOrder()->addStatusHistoryComment($msg);
            $payment->setAdditionalInformation('bolt_refund_transaction_statuses', serialize($refundTransactionStatuses));
            $payment->setAdditionalInformation('bolt_refund_merchant_transaction_ids', serialize($refundTransactionIds));
            $payment->setTransactionId(sprintf("%s-refund", $refundReference));

            //Mage::log(sprintf('Refund completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function void(Varien_Object $payment)
    {
        try {
            //Mage::log(sprintf('Initiating void on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            $reference = $payment->getAdditionalInformation('bolt_reference');
            if ($transId == null) {
                $message = Mage::helper('boltpay')->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $data = array(
                'transaction_id' => $transId,
            );
            $response = $boltHelper->transmit('void', $data);
            if (strlen($response->status) == 0) {
                $message = Mage::helper('boltpay')->__('Bad void response. Empty transaction status');
                Mage::throwException($message);
            }

            $responseStatus = $response->status;
            $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);
            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-void", $reference));

            //Mage::log(sprintf('Void completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Cancel is the same as void
     */
    public function cancel(Varien_Object $payment)
    {
        return $this->void($payment);
    }

    public function handleOrderUpdate(Varien_Object $order)
    {
        try {
            $orderPayment = $order->getPayment();
            $reference = $orderPayment->getAdditionalInformation('bolt_reference');
            $transactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
            $orderPayment->setTransactionId(sprintf("%s-%d-order", $reference, $order->getId()));
            $orderPayment->addTransaction(
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, Mage::helper('boltpay')->__("BOLT notification: Order ")
            );
            $order->save();

            $orderPayment->setData('auto_capture', $transactionStatus == self::TRANSACTION_COMPLETED);
            $this->handleTransactionUpdate($orderPayment, $transactionStatus, null);
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    "handle order update" => array (
                        "message" => $error['error'],
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            throw $e;
        }
    }

    public function handleTransactionUpdate(Mage_Payment_Model_Info $payment, $newTransactionStatus, $prevTransactionStatus, $transactionAmount = null)
    {
        try {
            $newTransactionStatus = strtolower($newTransactionStatus);

            // null prevTransactionStatus indicates a new transaction
            if ($prevTransactionStatus != null) {
                $prevTransactionStatus = strtolower($prevTransactionStatus);

                if ($newTransactionStatus != self::TRANSACTION_REFUND && $newTransactionStatus == $prevTransactionStatus) {
                    //Mage::log(sprintf('No new state change. Current transaction status: %s', $newTransactionStatus), null, 'bolt.log');
                    return;
                }

                $validNextStatuses = null;
                if (array_key_exists($prevTransactionStatus, $this->_validStateTransitions)) {
                    $validNextStatuses = $this->_validStateTransitions[$prevTransactionStatus];
                } else {
                    $message = Mage::helper('boltpay')->__("Invalid previous state: %s", $prevTransactionStatus);
                    Mage::throwException($message);
                }

                if ($validNextStatuses == null) {
                    $message = Mage::helper('boltpay')->__("validNextStatuses is null");
                    Mage::throwException($message);
                }

                //Mage::log(sprintf("Valid next states from %s: %s", $prevTransactionStatus, implode(",",$validNextStatuses)), null, 'bolt.log');
                $requestedStateOrAll = array($newTransactionStatus, self::TRANSACTION_ALL_STATES);

                if (!array_intersect($requestedStateOrAll, $validNextStatuses)) {
                    throw new Bolt_Boltpay_InvalidTransitionException(
                      $prevTransactionStatus, $newTransactionStatus, Mage::helper('boltpay')->__("Cannot transition a transaction from %s to %s", $prevTransactionStatus, $newTransactionStatus));
                }
            }

            if ($newTransactionStatus == self::TRANSACTION_REFUND || $newTransactionStatus != $prevTransactionStatus) {
                //Mage::log(sprintf("Transitioning from %s to %s", $prevTransactionStatus, $newTransactionStatus), null, 'bolt.log');
                $reference = $payment->getAdditionalInformation('bolt_reference');

                $this->_handleBoltTransactionStatus($payment, $newTransactionStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $newTransactionStatus);
                $payment->save();
                $payment->setShouldCloseParentTransaction(true);

                if ($newTransactionStatus == self::TRANSACTION_AUTHORIZED) {
                    $reference = $payment->getAdditionalInformation('bolt_reference');
                    if (empty($reference)) {
                        throw new Exception( Mage::helper('boltpay')->__("Payment missing expected transaction ID.") );
                    }
                    $order = $payment->getOrder();
                    $payment->setTransactionId($reference);
                    $transaction = $payment->addTransaction( Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH );
                    $transaction->setIsClosed(false);
                    $payment->save();
                    
                    $message = Mage::helper('boltpay')->__('BOLT notification: Payment transaction is authorized.');
                    $order->setState( Mage_Sales_Model_Order::STATE_PROCESSING, true, $message );
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_COMPLETED) {
                    $order = $payment->getOrder();
                    $invoices = $order->getInvoiceCollection()->getItems();
                    $invoice = null;
                    if (empty($invoices)) {
                        $invoice = $this->createInvoice($order, $transactionAmount);

                        $invoice->setTransactionId($reference);
                        $payment->setParentTransactionId($reference);
                        $invoice->setRequestedCaptureCase(self::CAPTURE_TYPE);
                        $invoice->register();
                        $payment->setCreatedInvoice($invoice);
                        $order->addRelatedObject($invoice);
                        $order->save();
                    } elseif (sizeof($invoices) == 1) {
                        $invoice = reset($invoices);
                        $invoice->capture();
                        $invoice->save();
                    } else {
                        $message = Mage::helper('boltpay')->__('Found multiple invoices');
                        Mage::throwException($message);
                    }
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_PENDING) {
                    $order = $payment->getOrder();
                    $message = Mage::helper('boltpay')->__('BOLT notification: Payment is under review');
                    $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $message);
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_CANCELLED) {
                    $order = $payment->getOrder();
                    $payment->setParentTransactionId($reference);
                    $payment->setTransactionId(sprintf("%s-void", $reference));
                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
                    $message = Mage::helper('boltpay')->__('BOLT notification: Transaction authorization has been voided');
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
                    $payment->save();
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_IRREVERSIBLE) {
                    $order = $payment->getOrder();
                    $payment->setParentTransactionId($reference);
                    $payment->setTransactionId(sprintf("%s-rejected", $reference));
                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
                    $message = Mage::helper('boltpay')->__('BOLT notification: Transaction reference "%s" has been permanently rejected by Bolt', $reference);
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
                    $payment->save();
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_REVERSIBLE) {
                    $order = $payment->getOrder();
                    $message = Mage::helper('boltpay')->__('BOLT notification: Transaction reference "%s" has been rejected by Bolt internal review but is eligible for force approval on Bolt\'s merchant dashboard', $reference);
                    $order->setState(self::ORDER_DEFERRED, true, $message);
                    $order->save();
                }
                elseif ($newTransactionStatus == self::TRANSACTION_REFUND) {
                    // flag refund as already being set on Bolt to prevent a duplicate call by Magento to Bolt
                    $payment->setAdditionalInformation('bolt_transaction_was_refunded_by_webhook', '1');
                    $order = $payment->getOrder();
                    $transactionAmount = Mage::app()->getStore()->roundPrice($transactionAmount);
                    $totalRefunded = $order->getTotalRefunded()?:0;
                    $totalPaid = $order->getTotalPaid();
                    $availableRefund = Mage::app()->getStore()->roundPrice(
                        $totalPaid - $totalRefunded
                    );
                    if($availableRefund < $transactionAmount){
                        $message = Mage::helper('boltpay')->__('Maximum amount available %s is less than requested %s', $availableRefund, $transactionAmount);
                        Mage::throwException($message);
                    }
                    
                    $service = Mage::getModel('sales/service_order', $order);
                    $invoiceIds = $order->getInvoiceCollection()->getAllIds();
                    $isPartialRefund = false;
                    //actually for order with bolt payment, there is only one invoice can refund
                    if($invoiceIds && isset($invoiceIds[0])){
                        $invoiceId = $invoiceIds[0];
                        // full refund
                        if($totalPaid == $availableRefund && $transactionAmount == $availableRefund){
                            $invoice = Mage::getModel('sales/order_invoice')
                                        ->load($invoiceId)
                                        ->setOrder($order);
                            if ($order->canCreditmemo() && $invoice->canRefund()) {                                
                                $data = array();
                                $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                                $creditmemo->setRefundRequested(true);
                                $creditmemo->setOfflineRequested(false);
                                $creditmemo->setPaymentRefundDisallowed(false);
                                $creditmemo->register()->save();
                            }
                        }
                        else{ // partial refund
                            $isPartialRefund = true;
                            $isShippingInclTax = Mage::getSingleton('tax/config')->displaySalesShippingInclTax($order->getStoreId());
                            //actually for order with bolt payment, there is only one invoice can refund
                            foreach($invoiceIds as $k=>$invoiceId){
                                $invoice = Mage::getModel('sales/order_invoice')
                                            ->load($invoiceId)
                                            ->setOrder($order);
                                if ($order->canCreditmemo() && $invoice->canRefund()) {                                
                                    $qtys = array();
                                    foreach($order->getAllItems() as $item) {
                                        $qtys[$item->getId()] = 0;
                                    }           
                            
                                    $data = array(
                                        'qtys' => $qtys
                                    );
                                    
                                    if ($isShippingInclTax) {
                                        $shipppingAllowedAmount = $order->getShippingInclTax()
                                                - $order->getShippingRefunded()
                                                - $order->getShippingTaxRefunded();
                                    } else {
                                        $shipppingAllowedAmount = $order->getShippingAmount() - $order->getShippingRefunded();
                                        $shipppingAllowedAmount = min($shipppingAllowedAmount, $invoice->getShippingAmount());
                                    }
                                    if($shipppingAllowedAmount > 0){
                                        if($transactionAmount >= $shipppingAllowedAmount){
                                            $data['shipping_amount'] = $shipppingAllowedAmount;
                                            $transactionAmount = $transactionAmount - $shipppingAllowedAmount;
                                        }
                                        else{
                                            $data['shipping_amount'] = $transactionAmount;
                                            $transactionAmount = 0;
                                        }
                                    }
                                    
                                    $data['adjustment_positive'] = $transactionAmount;
                                    
                                    $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                                    $creditmemo->setRefundRequested(true);
                                    $creditmemo->setOfflineRequested(false);
                                    $creditmemo->setPaymentRefundDisallowed(false);
                                    $creditmemo->register()->save();
                                }
                            }  
                        }
                        $order->save();
                    
                        $totalRefunded = $order->getTotalRefunded()?:0;
                        $availableRefund = Mage::app()->getStore()->roundPrice(
                            $totalPaid - $totalRefunded
                        );
    
                        if($availableRefund < 0.01){
                            //for partial refund, after all the paid amount is refuned
                            //we need to restore the items in cart separately
                            if($isPartialRefund){
                                $invoice = Mage::getModel('sales/order_invoice')
                                            ->load($invoiceId)
                                            ->setOrder($order);
                                $qtys = array();
                                foreach($order->getAllItems() as $item) {
                                    $qtys[$item->getId()] = $item->getData('qty_ordered');
                                }   
                        
                                $data = array(
                                    'qtys' => $qtys
                                );
                                $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                                $creditmemo->setSubtotal(0);
                                $creditmemo->setShippingAmount(0);
                                $creditmemo->setBaseGrandTotal(0);
                                $creditmemo->setGrandTotal(0);
                                $creditmemo->setRefundRequested(false);
                                $creditmemo->setOfflineRequested(false);
                                $creditmemo->setPaymentRefundDisallowed(true);
                                $creditmemo->register()->save();
                            }
                            $payment->setIsTransactionClosed(true);
                            $payment->setShouldCloseParentTransaction(true);          
                            $order->save();
                        }
                    }
                }
            } else {
                $payment->setShouldCloseParentTransaction(true);
            }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    "handle transaction update" => array (
                        "message" => $error['error'],
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            throw $e;
        }
    }

    /**
     * Generates either a partial or full invoice for the order.
     *
     * @param        $order Mage_Sales_Model_Order
     * @param        $captureAmount - The amount to invoice for
     *
     * @return Mage_Sales_Model_Order_Invoice   The order invoice
     * @throws Exception
     */
    protected function createInvoice($order, $captureAmount) {
        if (isset($captureAmount)) {
            $this->validateCaptureAmount($captureAmount);

            if($order->getGrandTotal() > $captureAmount) {
                return Mage::getModel('boltpay/service_order', $order)->prepareInvoiceWithoutItems($captureAmount);
            }
        }

        return $order->prepareInvoice();
    }

    /**
     * @param $captureAmount
     * @throws Exception
     */
    protected function validateCaptureAmount($captureAmount) {
        if(!isset($captureAmount) || !is_numeric($captureAmount) || $captureAmount < 0) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'capture_amount'  => $captureAmount,
                )
            );

            throw new Exception( Mage::helper('boltpay')->__('Capture amount is invalid') );
        }
    }

    /**
     * Handles transaction status for fetch transaction requests
     *
     * This is different from auth or capture transaction requests from Magento's perspective
     * for the following reasons
     *
     * Magento only checks if a transaction is pending or not in auth or capture process
     * but it checks for approval or denial (including pending or not) in fetch
     * transaction status request
     */
    function _handleBoltTransactionStatus(Mage_Payment_Model_Info $payment, $status)
    {
        switch(strtolower($status)) {
            case "completed":
            case "authorized":
                $payment->setIsTransactionApproved(true);
                break;

            case "failed":
                $payment->setIsTransactionDenied(true);
                break;

            case "pending":
                $payment->setIsTransactionPending(true);
                break;

            default:
                break;
        }
    }

    /**
     * @param mixed $data
     * @return Bolt_Boltpay_Model_Payment
     * @throws Mage_Core_Exception
     */
    public function assignData($data)
    {
        if (!$this->isAdminArea()) {
            return $this;
        }

        $info = $this->getInfoInstance();

        if ($reference = $data->getBoltReference()) {
            $info->setAdditionalInformation('bolt_reference', $reference);
        }

        return $this;
    }

    /**
     * Converts a Bolt Transaction Status to a Magento order status
     *
     * @param string $transactionStatus A Bolt transaction status
     * @return string The Magento order status mapped to the Bolt Status
     */
    public static function transactionStatusToOrderStatus( $transactionStatus ) {
        $new_order_status = Mage_Sales_Model_Order::STATE_NEW;
        switch ($transactionStatus) {
            case Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED:
                $new_order_status = Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING:
                $new_order_status = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED:
                $new_order_status = Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE:
                $new_order_status = Bolt_Boltpay_Model_Payment::ORDER_DEFERRED;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED:
            case Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE:
                $new_order_status = Mage_Sales_Model_Order::STATE_CANCELED;
                break;
            default:
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception( Mage::helper('boltpay')->__("'%s' is not a recognized order status.  '%s' is being set instead.", $transactionStatus, $transactionStatus) ));
        }

        return $new_order_status;
    }
}