<?php

class BoltPayInvalidTransitionException extends Exception {}

class Bolt_Boltpay_Model_Payment extends Mage_Payment_Model_Method_Abstract {
    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY    = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
    const METHOD_CODE               = 'boltpay';
    const TITLE                     = "Credit & Debit Card";


    // Order States
    const ORDER_DEFERRED = 'deferred';

    // Transaction States
    const TRANSACTION_AUTHORIZED = 'authorized';
    const TRANSACTION_CANCELLED = 'cancelled';
    const TRANSACTION_COMPLETED = 'completed';
    const TRANSACTION_PENDING = 'pending';
    const TRANSACTION_REJECTED_REVERSIBLE = 'rejected_reversible';
    const TRANSACTION_REJECTED_IRREVERSIBLE = 'rejected_irreversible';
    const TRANSACTION_NO_NEW_STATE = 'no_new_state';

    const HOOK_TYPE_AUTH = 'auth';
    const HOOK_TYPE_CAPTURE = 'capture';
    const HOOK_TYPE_REJECTED_REVERSIBLE = 'rejected_reversible';
    const HOOK_TYPE_REJECTED_IRREVERSIBLE = 'rejected_irreversible';
    const HOOK_TYPE_PAYMENT = 'payment';
    const HOOK_TYPE_PENDING = 'pending';
    const HOOK_TYPE_VOID = 'void';

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
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    // TODO: This can be set to true and we could move the handleOrderUpdate method
    protected $_canOrder                    = false;
    protected $_canUseInternal              = false;
    protected $_canUseForMultishipping      = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_isGateway                   = false;
    protected $_isInitializeNeeded          = false;

    protected $_validStateTransitions = array(
        self::TRANSACTION_AUTHORIZED => array(self::TRANSACTION_COMPLETED, self::TRANSACTION_CANCELLED),
        self::TRANSACTION_COMPLETED => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_PENDING => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_REJECTED_IRREVERSIBLE => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_REJECTED_REVERSIBLE => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_CANCELLED => array(self::TRANSACTION_NO_NEW_STATE)
    );

    // We will ignore "credit" type for now
    // There is no hook when the transaction Authorize fails.
    public static $_hookTypeToStatusTranslator = array(
        self::HOOK_TYPE_AUTH => self::TRANSACTION_AUTHORIZED,
        self::HOOK_TYPE_CAPTURE => self::TRANSACTION_COMPLETED,
        self::HOOK_TYPE_PAYMENT => self::TRANSACTION_COMPLETED,
        self::HOOK_TYPE_PENDING => self::TRANSACTION_PENDING,
        self::HOOK_TYPE_REJECTED_REVERSIBLE => self::TRANSACTION_REJECTED_REVERSIBLE,
        self::HOOK_TYPE_REJECTED_IRREVERSIBLE => self::TRANSACTION_REJECTED_IRREVERSIBLE,
        self::HOOK_TYPE_VOID => self::TRANSACTION_CANCELLED
    );

    public function getConfigData($field, $storeId = null) {
        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            if ($field == 'max_order_total' || $field == 'min_order_total' || $field == 'allowspecific' ||
                $field == 'specificcountry') {
                return null;
            }
        }

        if ($field == 'title') {
            return self::TITLE;
        }

        return parent::getConfigData($field, $storeId);
    }

    public static function translateHookTypeToTransactionStatus($hookType) {
        $hookType = strtolower($hookType);
        if (array_key_exists($hookType, static::$_hookTypeToStatusTranslator)) {
            return static::$_hookTypeToStatusTranslator[$hookType];
        } else {
            $message = sprintf('Invalid hook type %s', $hookType);
            Mage::throwException($message);
        }
    }

    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId) {

        try {
            Mage::log(sprintf('Initiating fetch transaction info on payment id: %d', $payment->getId()), null, 'bolt.log');
            if ($payment->getData('auto_capture')) {
                Mage::log('Skipping calls for fetch transaction in auto capture mode', null, 'bolt.log');
                return;
            }

            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($merchantTransId == null) {
                $message ='Waiting for a transaction update from Bolt. Please retry after 60 seconds.';
                Mage::throwException($message);
            }
            $boltHelper = Mage::helper('boltpay/api');
            $reference = $payment->getAdditionalInformation('bolt_reference');
            $response = $boltHelper->handleErrorResponse($boltHelper->transmit($reference, null));
            if (strlen($response->status) == 0) {
                $message ='Bad fetch transaction response. Empty transaction status';
                Mage::throwException($message);
            }
            $transactionStatus = strtolower($response->status);
            $prevTransactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');
            $this->handleTransactionUpdate($payment, $transactionStatus, $prevTransactionStatus);
            Mage::log(sprintf('Fetch transaction info completed for payment id: %d', $payment->getId()), null, 'bolt.log');
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Check whether BoltPay is available
     */
    public function isAvailable($quote = null) {
        if(!empty($quote)) {
            $quote->collectTotals();

            return Mage::helper('boltpay')->canUseBolt($quote);
        }

        return false;
    }

    /**
     * Bolt Authorize is a dummy authorize method since authorization is done by Bolt's checkout iframe
     * This authorize method does the following
     * 1. Logs the reference id and reference to the comments
     * 2. Keeps the authorization transaction record open
     * 3. Moves the transaction to either pending or non pending state based on the response
     */
    public function authorize(Varien_Object $payment, $amount) {

        try {
            Mage::log(sprintf('Initiating authorize on payment id: %d', $payment->getId()), null, 'bolt.log');
            // Get the merchant transaction id
            $reference = $payment->getAdditionalInformation('bolt_reference');
            if (empty($reference)) {
                throw new Exception("Payment missing expected transaction ID.");
            }

            // Set the transaction id
            $payment->setTransactionId($reference);

            // Log the payment info
            $msg = sprintf(
                "Bolt Operation: \"Authorization\". Bolt Reference: \"%s\".", $reference);
            $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $msg);


            // Auth transactions need to be kept open to support cancelling/voiding transaction
            $payment->setIsTransactionClosed(false);
            Mage::log(sprintf('Authorization completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function capture(Varien_Object $payment, $amount) {
        try {
            Mage::log(sprintf('Initiating capture on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            // Get the merchant transaction id
            $merchantTransId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');

            $reference = $payment->getAdditionalInformation('bolt_reference');

            $transactionStatus = $payment->getAdditionalInformation('bolt_transaction_status');

            if ($merchantTransId == null && !$payment->getData('auto_capture')) {
                // If a capture is called with transaction status == Completed, it implies its
                // auto capture that is calling this function and hence there is no need
                // to call transaction update.
                $message ='Waiting for a transaction update from Bolt. Please retry after 60 seconds.';
                Mage::throwException($message);
            }

            if ($transactionStatus == self::TRANSACTION_AUTHORIZED) {
                $captureRequest = array(
                    'transaction_id' => $merchantTransId,
                );
                $response = $boltHelper->handleErrorResponse($boltHelper->transmit('capture', $captureRequest));
                if (strlen($response->status) == 0) {
                    $message = 'Bad capture response. Empty transaction status';
                    Mage::throwException($message);
                }
                $responseStatus = $response->status;

                $this->_handleBoltTransactionStatus($payment, $responseStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);

                $payment->save();

            } elseif ($transactionStatus != self::TRANSACTION_COMPLETED) {
                $message = sprintf('Capture attempted denied. Transaction status: %s', $transactionStatus);
                Mage::throwException($message);
            }

            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-capture", $reference));
            Mage::log(sprintf('Capture completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function refund(Varien_Object $payment, $amount) {
        try {
            Mage::log(sprintf('Initiating refund on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            $paymentInfo = $this->getInfoInstance();
            $order = $paymentInfo->getOrder();

            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            if ($transId == null) {
                $message = 'Waiting for a transaction update from Bolt. Please retry after 60 seconds.';
                Mage::throwException($message);
            }
            $data = array(
                'transaction_id' => $transId,
                'Amount' => $amount * 100,
                'Currency' => $order->getOrderCurrencyCode(),
            );
            $response = $boltHelper->handleErrorResponse($boltHelper->transmit('credit', $data));
            
            if (strlen($response->reference) == 0) {
                $message = 'Bad refund response. Empty transaction reference';
                Mage::throwException($message);
            }

            if (strlen($response->id) == 0) {
                $message = 'Bad refund response. Empty transaction id';
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
            $msg = sprintf(
                "Bolt Operation: \"Refund\". Bolt Reference: \"%s\".\nBolt Transaction: \"%s\"",
                $refundReference,
                $refundTransactionId);
            $payment->getOrder()->addStatusHistoryComment($msg);
            $payment->setAdditionalInformation('bolt_refund_transaction_statuses', serialize($refundTransactionStatuses));
            $payment->setAdditionalInformation('bolt_refund_merchant_transaction_ids', serialize($refundTransactionIds));
            $payment->setTransactionId(sprintf("%s-refund", $refundReference));

            Mage::log(sprintf('Refund completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    public function void(Varien_Object $payment) {
        try {
            Mage::log(sprintf('Initiating void on payment id: %d', $payment->getId()), null, 'bolt.log');
            $boltHelper = Mage::helper('boltpay/api');
            $transId = $payment->getAdditionalInformation('bolt_merchant_transaction_id');
            $reference = $payment->getAdditionalInformation('bolt_reference');
            if ($transId == null) {
                $message = 'Waiting for a transaction update from Bolt. Please retry after 60 seconds.';
                Mage::throwException($message);
            }
            $data = array(
                'transaction_id' => $transId,
            );
            $response = $boltHelper->handleErrorResponse($boltHelper->transmit('void', $data));
            if (strlen($response->status) == 0) {
                $message = 'Bad void response. Empty transaction status';
                Mage::throwException($message);
            }
            $responseStatus = $response->status;
            $payment->setAdditionalInformation('bolt_transaction_status', $responseStatus);
            $payment->setParentTransactionId($reference);
            $payment->setTransactionId(sprintf("%s-void", $reference));

            Mage::log(sprintf('Void completed for payment id: %d', $payment->getId()), null, 'bolt.log');
            return $this;
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Cancel is the same as void
     */
    public function cancel(Varien_Object $payment) {
        return $this->void($payment);
    }

    public function handleOrderUpdate(Varien_Object $order) {
        try {
            $orderPayment = $order->getPayment();
            $reference = $orderPayment->getAdditionalInformation('bolt_reference');
            $transactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
            $orderPayment->setTransactionId(sprintf("%s-%d-order", $reference, $order->getId()));
            $orderPayment->addTransaction(
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER, null, false, "Order ");
            $order->save();

            $orderPayment->setData('auto_capture', $transactionStatus == self::TRANSACTION_COMPLETED);
            $this->handleTransactionUpdate($orderPayment, $transactionStatus, null);
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addMetaData(
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

    public function handleTransactionUpdate(Mage_Payment_Model_Info $payment, $newTransactionStatus, $prevTransactionStatus) {
        try {

            $newTransactionStatus = strtolower($newTransactionStatus);

            // null prevTransactionStatus indicates a new transaction
            if ($prevTransactionStatus != null) {
                $prevTransactionStatus = strtolower($prevTransactionStatus);

                if ($newTransactionStatus == $prevTransactionStatus) {
                    Mage::log(sprintf('No new state change. Current transaction status: %s', $newTransactionStatus), null, 'bolt.log');
                    return;
                }

                $validNextStatuses = null;
                if (array_key_exists($prevTransactionStatus,  $this->_validStateTransitions)) {
                    $validNextStatuses = $this->_validStateTransitions[$prevTransactionStatus];
                } else {
                    $message = sprintf("Invalid previous state: %s", $prevTransactionStatus);
                    Mage::throwException($message);
                }

                if ($validNextStatuses == null) {
                    $message = "validNextStatuses is null";
                    Mage::throwException($message);
                }

                Mage::log(
                    sprintf("Valid next states from %s: %s", $prevTransactionStatus, implode(",",$validNextStatuses)), null, 'bolt.log');

                if (!in_array($newTransactionStatus, $validNextStatuses)) {
                    throw new BoltPayInvalidTransitionException(sprintf("Cannot transition a transaction from %s to %s", $prevTransactionStatus, $newTransactionStatus));
                }
            }

            if ($newTransactionStatus != $prevTransactionStatus) {
                Mage::log(sprintf("Transitioning from %s to %s", $prevTransactionStatus, $newTransactionStatus), null, 'bolt.log');
                $reference = $payment->getAdditionalInformation('bolt_reference');

                $this->_handleBoltTransactionStatus($payment, $newTransactionStatus);
                $payment->setAdditionalInformation('bolt_transaction_status', $newTransactionStatus);
                $payment->save();
                $payment->setShouldCloseParentTransaction(true);

                if ($newTransactionStatus == self::TRANSACTION_AUTHORIZED) {
                    if ($prevTransactionStatus ==  self::TRANSACTION_PENDING) {
                        $message = Mage::helper('boltpay')->__('Payment transaction is approved.');
                    } else {
                        $message = '';
                    }
                    $order = $payment->getOrder();
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $message);
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_COMPLETED) {
                    $order = $payment->getOrder();
                    $invoices = $order->getInvoiceCollection()->getItems();
                    $invoice = null;
                    if (empty($invoices)) {
                        $invoice = $order->prepareInvoice();
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
                        $message = 'Found multiple invoices';
                        Mage::throwException($message);
                    }
                } elseif ($newTransactionStatus == self::TRANSACTION_PENDING) {
                    $order = $payment->getOrder();
                    $message = Mage::helper('boltpay')->__('Payment is under review');
                    $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, $message);
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_CANCELLED) {
                    $order = $payment->getOrder();
                    $payment->setParentTransactionId($reference);
                    $payment->setTransactionId(sprintf("%s-void", $reference));
                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
                    $message = Mage::helper('boltpay')->__('Transaction authorization has been voided');
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
                    $payment->save();
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_IRREVERSIBLE) {
                    $order = $payment->getOrder();
                    $payment->setParentTransactionId($reference);
                    $payment->setTransactionId(sprintf("%s-rejected", $reference));
                    $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, null, true);
                    $message = Mage::helper('boltpay')->__(sprintf('Transaction reference "%s" has been permanently rejected by Bolt', $reference));
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message);
                    $payment->save();
                    $order->save();
                } elseif ($newTransactionStatus == self::TRANSACTION_REJECTED_REVERSIBLE) {
                    $order = $payment->getOrder();
                    $message = Mage::helper('boltpay')->__(sprintf('Transaction reference "%s" has been rejected by Bolt internal review but is eligible for force approval on Bolt\'s merchant dashboard', $reference));
                    $order->setState(self::ORDER_DEFERRED, true, $message);
                    $order->save();
                }
            } else {
                $payment->setShouldCloseParentTransaction(true);
            }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addMetaData(
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
     * Handles transaction status for fetch transaction requests
     *
     * This is different from auth or capture transaction requests from Magento's perspective
     * for the following reasons
     *
     * Magento only checks if a transaction is pending or not in auth or capture process
     * but it checks for approval or denial (including pending or not) in fetch
     * transaction status request
     */
    function _handleBoltTransactionStatus(Mage_Payment_Model_Info $payment, $status) {
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
}
