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

class Bolt_Boltpay_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    use Bolt_Boltpay_BoltGlobalTrait;

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
    // TODO: This can be set to true and we could move the handleOrderUpdate method
    protected $_canOrder                    = false;
    protected $_canUseInternal              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_isGateway                   = false;
    protected $_isInitializeNeeded          = true;

    protected $_validStateTransitions = array(
        self::TRANSACTION_AUTHORIZED => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_COMPLETED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_PENDING),
        self::TRANSACTION_COMPLETED => array(self::TRANSACTION_REFUND, self::TRANSACTION_NO_NEW_STATE, self::TRANSACTION_COMPLETED),
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
        if (!Bolt_Boltpay_Helper_Data::$fromHooks) {
            $this->_validStateTransitions[self::TRANSACTION_ON_HOLD] = array(self::TRANSACTION_ALL_STATES);
        }
    }

    /**
     * We set the initial state to Bolt
     * @param string $paymentAction
     * @param object $stateObject
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setStatus('pending_bolt')
            ->setIsNotified(false);

        return parent::initialize($paymentAction, $stateObject);
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

    public static function translateHookTypeToTransactionStatus($hookType, $transaction = null)
    {
        $hookType = strtolower($hookType);
        if (array_key_exists($hookType, static::$_hookTypeToStatusTranslator)) {

            if (
                $hookType == self::HOOK_TYPE_CAPTURE &&
                $transaction &&
                $transaction->status == self::TRANSACTION_AUTHORIZED
            ){
                return self::TRANSACTION_AUTHORIZED;
            }

            return static::$_hookTypeToStatusTranslator[$hookType];
        } else {
            $payment = new Bolt_Boltpay_Model_Payment();
            $message = $payment->boltHelper()->__('Invalid hook type %s', $hookType);
            Mage::throwException($message);
        }
    }

    /**
     * Method called upon pressing the "Get Payment Update" button from the Admin
     *
     * @param Mage_Payment_Model_Info $payment  Holds meta data about this payment
     * @param string $transactionId  The transaction ID of the payment
     * @throws Exception    thrown upon error in updating the status of an order
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        try {

            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($merchantTransId == null) {
                $message = $this->boltHelper()->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $reference = $payment->getAdditionalInformation('bolt_reference');
            $response = $this->boltHelper()->transmit($reference, null);
            if (strlen($response->status) == 0) {
                $message = $this->boltHelper()->__('Bad fetch transaction response. Empty transaction status');
                Mage::throwException($message);
            }

            $transactionStatus = strtolower($response->status);
            $prevTransactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');

            if ($transactionStatus === self::TRANSACTION_PENDING) {
                $message = $this->boltHelper()->__('Bolt is still reviewing this transaction.  The order status will be updated automatically after review.');
                Mage::getSingleton('adminhtml/session')->addNotice($message);
            }

            $this->handleTransactionUpdate($payment, $transactionStatus, $prevTransactionStatus);
            //Mage::log(sprintf('Fetch transaction info completed for payment id: %d', $payment->getId()), null, 'bolt.log');
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            throw $e;
        }
    }

    /**
     * Check whether BoltPay is available
     */
    public function isAvailable($quote = null)
    {
        if(!empty($quote)) {
            return $this->boltHelper()->canUseBolt($quote);
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
            $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_NEW, true, "I am your gingerbread boy");
            return $this;
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            throw $e;
        }
    }

    public function capture(Varien_Object $payment, $amount)
    {
        try {
            // Get the merchant transaction id
            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');

            $reference = $payment->getAdditionalInformation('bolt_reference');

            $transactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');

            if ($merchantTransId == null && !$payment->getData('auto_capture')) {
                // If a capture is called with transaction status == Completed, it implies its
                // auto capture that is calling this function and hence there is no need
                // to call transaction update.
                $message = $this->boltHelper()->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            if ($transactionStatus == self::TRANSACTION_AUTHORIZED) {
                $captureRequest = array(
                    'transaction_id' => $merchantTransId,
                    'amount'         => (int)round($amount * 100),
                    'currency'       => $payment->getOrder()->getOrderCurrencyCode(),
                    'skip_hook_notification' => true
                );
                $response = $this->boltHelper()->transmit('capture', $captureRequest);
                if (strlen($response->status) == 0) {
                    $message = $this->boltHelper()->__('Bad capture response. Empty transaction status');
                    Mage::throwException($message);
                }

                $responseStatus = $response->status;

                $this->_handleBoltTransactionStatus($payment, $responseStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);

                $payment->save();
            } else {
                $message = $this->boltHelper()->__('Capture attempted denied. Transaction status: %s', $transactionStatus);
                Mage::throwException($message);
            }

            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-capture-%s", $reference, time()));
            $payment->setIsTransactionClosed(0);
            return $this;
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
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

            $paymentInfo = $this->getInfoInstance();
            $order = $paymentInfo->getOrder();

            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($transId == null) {
                $message = $this->boltHelper()->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $data = array(
                'transaction_id' => $transId,
                'amount' => (int)round($amount * 100),
                'currency' => $order->getOrderCurrencyCode(),
                'skip_hook_notification' => true,
            );
            $response = $this->boltHelper()->transmit('credit', $data);

            if (strlen($response->reference) == 0) {
                $message = $this->boltHelper()->__('Bad refund response. Empty transaction reference');
                Mage::throwException($message);
            }

            if (strlen($response->id) == 0) {
                $message = $this->boltHelper()->__('Bad refund response. Empty transaction id');
                Mage::throwException($message);
            }
            
            $this->setRefundPaymentInfo($payment,$response);

            //Mage::log(sprintf('Refund completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            throw $e;
        }
    }

    public function void(Varien_Object $payment)
    {
        try {
            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            $reference = $payment->getAdditionalInformation('bolt_reference');
            if ($transId == null) {
                $message = $this->boltHelper()->__('Waiting for a transaction update from Bolt. Please retry after 60 seconds.');
                Mage::throwException($message);
            }

            $data = array(
                'transaction_id' => $transId,
                'skip_hook_notification' => true
            );
            $response = $this->boltHelper()->transmit('void', $data);
            if (strlen($response->status) == 0) {
                $message = $this->boltHelper()->__('Bad void response. Empty transaction status');
                Mage::throwException($message);
            }

            $responseStatus = $response->status;
            $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);
            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-void", $reference));

            //Mage::log(sprintf('Void completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
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
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, $this->boltHelper()->__("BOLT notification: Order ")
            );
            $order->save();

            $orderPayment->setData('auto_capture', $transactionStatus == self::TRANSACTION_COMPLETED);
            if($order->getState() !== Mage_Sales_Model_Order::STATE_HOLDED){
                $this->handleTransactionUpdate($orderPayment, $transactionStatus, null);
            }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');

            $this->boltHelper()->addBreadcrumb(
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

    public function handleTransactionUpdate(
        Mage_Payment_Model_Info $payment,
        $newTransactionStatus,
        $prevTransactionStatus,
        $transactionAmount = null,
        $transaction = null
    ) {
        try {
            $newTransactionStatus = strtolower($newTransactionStatus);

            // null prevTransactionStatus indicates a new transaction
            if ($prevTransactionStatus != null) {
                $prevTransactionStatus = strtolower($prevTransactionStatus);

                if (!$this->isTransactionStatusChanged($newTransactionStatus, $prevTransactionStatus)) { return; }
                $this->validateWebHook($newTransactionStatus, $prevTransactionStatus);
            }

            if ($this->isTransactionStatusChanged($newTransactionStatus, $prevTransactionStatus)) {
                $reference = $payment->getAdditionalInformation('bolt_reference');

                if ($this->isCaptureRequest($newTransactionStatus, $prevTransactionStatus)) {
                    $this->createInvoiceForHookRequest($payment);
                }elseif ($newTransactionStatus == self::TRANSACTION_AUTHORIZED) {
                    if (empty($reference)) {
                        throw new Exception( $this->boltHelper()->__("Payment missing expected transaction ID.") );
                    }
                    $order = $payment->getOrder();
                    $payment->setTransactionId($reference);
                    $transaction = $payment->addTransaction( Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH );
                    $transaction->setIsClosed(false);
                    $payment->save();
                    
                    $message = $this->boltHelper()->__('BOLT notification: Payment transaction is authorized.');
                    $order->setState( Mage_Sales_Model_Order::STATE_PROCESSING, true, $message );
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_PENDING) {
                    $order = $payment->getOrder();
                    $message = $this->boltHelper()->__('BOLT notification: Payment is under review');
                    $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $message);
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_CANCELLED) {
                      $this->handleVoidTransactionUpdate($payment);
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_IRREVERSIBLE) {
                    $order = $payment->getOrder();
                    $payment->setParentTransactionId($reference);
                    $payment->setTransactionId(sprintf("%s-rejected", $reference));
                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
                    $message = $this->boltHelper()->__('BOLT notification: Transaction reference "%s" has been permanently rejected by Bolt', $reference);
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
                    $payment->save();
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_REVERSIBLE) {
                    $order = $payment->getOrder();
                    $message = $this->boltHelper()->__('BOLT notification: Transaction reference "%s" has been rejected by Bolt internal review but is eligible for force approval on Bolt\'s merchant dashboard', $reference);
                    $order->setState(self::ORDER_DEFERRED, true, $message);
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REFUND) {
                    $this->handleRefundTransactionUpdate($payment, $newTransactionStatus, $prevTransactionStatus, $transactionAmount, $transaction);
                }

                $this->_handleBoltTransactionStatus($payment, $newTransactionStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $newTransactionStatus);
                $payment->save();
            } else {
                $payment->setShouldCloseParentTransaction(true);
            }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');

            $this->boltHelper()->addBreadcrumb(
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
    
    public function handleRefundTransactionUpdate(
        Mage_Payment_Model_Info $payment,
        $newTransactionStatus,
        $prevTransactionStatus,
        $transactionAmount,
        $transaction
    ) {
        try {
            $order             = $payment->getOrder();
            $transactionAmount = Mage::app()->getStore()->roundPrice($transactionAmount);
            $totalRefunded     = $order->getTotalRefunded() ?: 0;
            $totalPaid         = $order->getTotalPaid();
            $availableRefund   = Mage::app()->getStore()->roundPrice(
                $totalPaid - $totalRefunded
            );
            if ($availableRefund < $transactionAmount) {
                $message = $this->boltHelper()->__('Maximum amount available %s is less than requested %s',
                    $availableRefund, $transactionAmount);
                Mage::throwException($message);
            }

            $service         = Mage::getModel('sales/service_order', $order);
            $invoiceIds      = $order->getInvoiceCollection()->getAllIds();
            $isPartialRefund = false;
            //actually for order with bolt payment, there is only one invoice can refund
            if ($invoiceIds && isset($invoiceIds[0])) {
                $this->setRefundPaymentInfo($payment,$transaction);             
                
                // flag refund as already being set on Bolt to prevent a duplicate call by Magento to Bolt
                $payment->setAdditionalInformation('bolt_transaction_was_refunded_by_webhook', '1');

                $invoiceId = $invoiceIds[0];
                // full refund
                if ($totalPaid == $availableRefund && $transactionAmount == $availableRefund) {
                    $invoice = Mage::getModel('sales/order_invoice')
                                   ->load($invoiceId)
                                   ->setOrder($order);
                    if ($order->canCreditmemo() && $invoice->canRefund()) {
                        $data       = array();
                        $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                        $creditmemo->setRefundRequested(true);
                        $creditmemo->setOfflineRequested(false);
                        $creditmemo->setPaymentRefundDisallowed(false);
                        $creditmemo->register()->save();
                    }
                } else { // partial refund
                    $isPartialRefund   = true;
                    //actually for order with bolt payment, there is only one invoice can refund
                    foreach ($invoiceIds as $k => $invoiceId) {
                        $invoice = Mage::getModel('sales/order_invoice')
                                       ->load($invoiceId)
                                       ->setOrder($order);
                        if ($order->canCreditmemo() && $invoice->canRefund()) {
                            $qtys = array();
                            foreach ($order->getAllItems() as $item) {
                                $qtys[$item->getId()] = 0;
                            }

                            $data = array(
                                'qtys' => $qtys
                            );
                            
                            // When doing a refund from Bolt dashboard, it is hard to detect what the refund is for, actually the refund could be shipping, tax, item fee or any part of them,
                            // therefore we have to reply on "Adjustment Refund", using this field the refund in magento can keep pace with exact amount from Bolt server, also avoid complicated calculation
                            // and conflicts with other plugins.
                            $data['adjustment_positive'] = $transactionAmount;
                            // By default magento would always send all the available shipping amount to refund,
                            // so we need to set the shipping amount to zero to avoid overcharging.
                            $data['shipping_amount'] = 0;

                            $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
                            $creditmemo->setRefundRequested(true);
                            $creditmemo->setOfflineRequested(false);
                            $creditmemo->setPaymentRefundDisallowed(false);
                            $creditmemo->register()->save();
                        }
                    }
                    // For partial refund, the next refund can be called by Magento or Bolt
                    $payment->setAdditionalInformation('bolt_transaction_was_refunded_by_webhook', '0');
                }

                $order->save();

                $totalRefunded   = $order->getTotalRefunded() ?: 0;
                $availableRefund = Mage::app()->getStore()->roundPrice(
                    $totalPaid - $totalRefunded
                );

                if ($availableRefund < 0.01) {
                    //for partial refund, after all the paid amount is refuned
                    //we need to restore the items in cart separately
                    if ($isPartialRefund) {
                        $invoice = Mage::getModel('sales/order_invoice')
                                       ->load($invoiceId)
                                       ->setOrder($order);
                        $qtys    = array();
                        foreach ($order->getAllItems() as $item) {
                            $qtys[$item->getId()] = $item->getData('qty_ordered');
                        }

                        $data       = array(
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

        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Set related info for refund payment.
     *
     * @param  $payment Mage_Payment_Model_Info
     * @param  $transaction   Object derived from the response of the Bolt API endpoint
     *
     */
    protected function setRefundPaymentInfo(Mage_Payment_Model_Info $payment,$transaction){
        $refundTransactionId       = $transaction->id;
        $refundTransactionStatus   = $transaction->status;
        $refundReference           = $transaction->reference;
        $refundTransactionStatuses = $payment->getAdditionalInformation('bolt_refund_transaction_statuses');
        $refundTransactionIds      = $payment->getAdditionalInformation('bolt_refund_merchant_transaction_ids');
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
        $msg = $this->boltHelper()->__(
            "Bolt Operation: \"Refund\". Bolt Reference: \"%s\".\nBolt Transaction: \"%s\"",
            $refundReference,
            $refundTransactionId
        );
        $payment->getOrder()->addStatusHistoryComment($msg);
        $payment->setAdditionalInformation('bolt_refund_transaction_statuses',
            serialize($refundTransactionStatuses));
        $payment->setAdditionalInformation('bolt_refund_merchant_transaction_ids',
            serialize($refundTransactionIds));
        $payment->setTransactionId(sprintf("%s-refund", $refundReference));
        $payment->setAdditionalInformation('bolt_transaction_status', $refundTransactionStatus);
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
            $boltMaxCaptureAmountAfterRefunds = $this->getBoltMaxCaptureAmountAfterRefunds($order);
            if($captureAmount > $boltMaxCaptureAmountAfterRefunds){
                $captureAmount = $boltMaxCaptureAmountAfterRefunds;
            }

            $this->validateCaptureAmount($order, $captureAmount);

            if($order->getGrandTotal() > $captureAmount) {
                return Mage::getModel('boltpay/service_order', $order)->prepareInvoiceWithoutItems($captureAmount);
            }
        }

        return $order->prepareInvoice();
    }

    /**
     * Handles case where Bolt didn't properly apply discount, and Magento grand total is now less than the Bolt capture amount.
     * Reduces the Bolt capture amount by the Bolt refunded amount.
     *
     * @param $order
     * @return float
     */
    protected function getBoltMaxCaptureAmountAfterRefunds($order){
        $reference = $order->getPayment()->getAdditionalInformation('bolt_reference');
        $transaction = $this->boltHelper()->fetchTransaction($reference);
        $refundedAmount = (!empty($transaction->refunded_amount->amount)) ? $transaction->refunded_amount->amount : 0;

        return ($transaction->amount->amount - $refundedAmount)/100;
    }

    /**
     * @param $captureAmount
     * @throws Exception
     */
    protected function validateCaptureAmount($order, $captureAmount) {
        $isInvalidAmount = !isset($captureAmount) || !is_numeric($captureAmount) || $captureAmount < 0;
        $isInvalidAmountRange = $order->getTotalInvoiced() + $captureAmount > $order->getGrandTotal();

        if($isInvalidAmount || $isInvalidAmountRange) {
            $this->boltHelper()->addBreadcrumb(
                array(
                    'capture_amount'  => $captureAmount,
                )
            );
            throw new Exception( $this->boltHelper()->__('Capture amount is invalid'));
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
            case Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED:
                $new_order_status = Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING:
                $new_order_status = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE:
                $new_order_status = Bolt_Boltpay_Model_Payment::ORDER_DEFERRED;
                break;
            case Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED:
            case Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE:
                $new_order_status = Mage_Sales_Model_Order::STATE_CANCELED;
                break;
            default:
                $payment = new Bolt_Boltpay_Model_Payment();
                $payment->boltHelper()->notifyException(new Exception( $payment->boltHelper()->__("'%s' is not a recognized order status.  '%s' is being set instead.", $transactionStatus, $new_order_status) ));
        }

        return $new_order_status;
    }

    /**
     * @param \Mage_Payment_Model_Info $payment
     *
     * @throws \Exception
     */
    protected function createInvoiceForHookRequest(Mage_Payment_Model_Info $payment)
    {
        $boltCaptures = $this->getNewBoltCaptures($payment);

        $order = $payment->getOrder();
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);

        if (count($boltCaptures) == 0 && $order->getGrandTotal() == 0) {
            $boltCaptures = $this->removeInvoicedCaptures($payment, array(0));
        }
        // Create invoices for items from $boltCaptures that are not exists on Magento
        $identifier = count($boltCaptures) > 1 ? 0 : null;
        foreach ($boltCaptures as $captureAmount) {
            $invoice = $this->createInvoice($order, $captureAmount / 100);
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $this->preparePaymentAndAddTransaction($payment, $invoice, $identifier);
            $order->addRelatedObject($invoice);
            $identifier++;
        }

        $order->save();
    }

    /**
     * @param \Mage_Payment_Model_Info $payment
     *
     * @return array
     * @throws \Exception
     */
    protected function getNewBoltCaptures(Mage_Payment_Model_Info $payment)
    {
        $reference = $payment->getAdditionalInformation('bolt_reference');
        $transaction = $this->boltHelper()->fetchTransaction($reference);
        $boltCaptures = $this->getBoltCaptures($transaction);

        return $this->removeInvoicedCaptures($payment, $boltCaptures);
    }

    /**
     * @param $transaction
     *
     * @return array
     */
    protected function getBoltCaptures($transaction)
    {
        $boltCaptures = array();
        foreach (@$transaction->captures as $capture) {
            if (@$capture->status == 'succeeded') {
                $boltCaptures[] = @$capture->amount->amount;
            }
        }

        return $boltCaptures;
    }

    /**
     * @param       $payment
     * @param array $boltCaptures
     *
     * @return array
     */
    protected function removeInvoicedCaptures(Mage_Payment_Model_Info $payment, $boltCaptures = array())
    {
        $order = $payment->getOrder();
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $amount = round($invoice->getGrandTotal() * 100);
            $index = array_search($amount, $boltCaptures);

            if ($index !== false) {
                unset($boltCaptures[$index]);
            }
        }

        return $boltCaptures;
    }

    /**
     * @param Mage_Payment_Model_Info         $payment
     * @param  Mage_Sales_Model_Order_Invoice $invoice
     * @param                                 $identifier
     *
     * @throws \Exception
     */
    protected function preparePaymentAndAddTransaction(Mage_Payment_Model_Info $payment, Mage_Sales_Model_Order_Invoice $invoice, $identifier = null)
    {
        $this->preparePaymentForTransaction($payment, $identifier);
        Mage::dispatchEvent('sales_order_payment_capture', array('payment' => $payment, 'invoice' => $invoice));
        $this->addPaymentTransaction($payment, $invoice);
    }

    /**
     * @param \Mage_Payment_Model_Info $payment
     * @param                          $identifier
     */
    protected function preparePaymentForTransaction(Mage_Payment_Model_Info $payment, $identifier)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        $reference = $payment->getAdditionalInformation('bolt_reference');
        $transactionId = sprintf("%s-capture-%s", $reference, time());
        $transactionId .= $identifier !== null ? "-$identifier" : '';

        $payment->setParentTransactionId($reference);
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(0);
        if (!$order->getTotalDue()) {
            $payment->setShouldCloseParentTransaction(true);
        }
    }

    /**
     * @param \Mage_Payment_Model_Info        $payment
     * @param \Mage_Sales_Model_Order_Invoice $invoice
     */
    protected function addPaymentTransaction(Mage_Payment_Model_Info $payment, Mage_Sales_Model_Order_Invoice $invoice)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $message = $payment->getPreparedMessage() . $this->boltHelper()->__(
                ' Captured amount of %s online.',
                $order->getBaseCurrency()->formatTxt($invoice->getGrandTotal(), array())
            );

        $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $invoice, true, $message);
    }

    /**
     * @param $newTransactionStatus
     * @param $prevTransactionStatus
     *
     * @return bool
     */
    protected function isTransactionStatusChanged($newTransactionStatus, $prevTransactionStatus)
    {
        return in_array($newTransactionStatus, array(self::TRANSACTION_REFUND, self::TRANSACTION_AUTHORIZED, self::TRANSACTION_COMPLETED)) ||
               $newTransactionStatus != $prevTransactionStatus;
    }

    /**
     * @param $newTransactionStatus
     * @param $prevTransactionStatus
     *
     * @return bool
     * @throws \Bolt_Boltpay_InvalidTransitionException
     * @throws \Mage_Core_Exception
     */
    protected function validateWebHook($newTransactionStatus, $prevTransactionStatus)
    {
        $validNextStatuses = null;
        if (array_key_exists($prevTransactionStatus, $this->_validStateTransitions)) {
            $validNextStatuses = $this->_validStateTransitions[$prevTransactionStatus];
        } else {
            $message = $this->boltHelper()->__("Invalid previous state: %s", $prevTransactionStatus);
            Mage::throwException($message);
        }

        if ($validNextStatuses == null) {
            $message = $this->boltHelper()->__("validNextStatuses is null");
            Mage::throwException($message);
        }

        $requestedStateOrAll = array($newTransactionStatus, self::TRANSACTION_ALL_STATES);

        if (!array_intersect($requestedStateOrAll, $validNextStatuses)) {
            throw new Bolt_Boltpay_InvalidTransitionException(
                $prevTransactionStatus, $newTransactionStatus, $this->boltHelper()->__("Cannot transition a transaction from %s to %s", $prevTransactionStatus, $newTransactionStatus)
            );
        }

        return true;
    }

    /**
     * @param $newTransactionStatus
     * @param $prevTransactionStatus
     *
     * @return bool
     */
    protected function isCaptureRequest($newTransactionStatus, $prevTransactionStatus)
    {
        return $newTransactionStatus == self::TRANSACTION_COMPLETED ||
              ($newTransactionStatus == self::TRANSACTION_AUTHORIZED && $prevTransactionStatus == self::TRANSACTION_AUTHORIZED);
    }

    /**
     *  Handles two void cases sent by Bolt.
     *  1.) Complete void an order cancelling all funds
     *  2.) Partial void where partial capture has occurred but authorization has expired
     *
     * @param Mage_Payment_Model_Info $payment
     */
    public function handleVoidTransactionUpdate(Mage_Payment_Model_Info $payment){

        $authTransaction = $payment->getAuthorizationTransaction();
        $order = $payment->getOrder();
        $amount =  $order->getBaseGrandTotal() - $order->getBaseTotalPaid() ;

        if ($authTransaction->canVoidAuthorizationCompletely()) {
            // True void
            $reference = $payment->getAdditionalInformation('bolt_reference');
            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-void", $reference));
            $payment->setShouldCloseParentTransaction(true);
            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
            $message = $this->getVoidMessage($payment, $transaction, $amount);
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
        } else if (!$authTransaction->getIsClosed()) {
            // Open authorization has expired and partial capture has taken place.
            // We do not change the order state.  We only need to close the authorization.
            $authTransaction->closeAuthorization();
            $message = $this->getVoidMessage($payment, $authTransaction, $amount);
            $order->addStatusHistoryComment($message);
        }

        $order->save();
    }

    /**
     * @param Mage_Payment_Model_Info $payment
     * @param $transaction
     * @param null $amount
     * @return string
     */
    protected function getVoidMessage(Mage_Payment_Model_Info $payment, $transaction, $amount = null)
    {
        $order = $payment->getOrder();
        $message = $this->boltHelper()->__('BOLT notification: Transaction authorization has been voided.');
        if (isset($amount)) {
            $message .= ' ' . $this->boltHelper()->__('Amount: %s.', $order->getBaseCurrency()->formatTxt($amount, array()));
        }
        $message .= ' ' . $this->boltHelper()->__('Transaction ID: "%s".', $transaction->getHtmlTxnId());

        return $message;
    }
}
