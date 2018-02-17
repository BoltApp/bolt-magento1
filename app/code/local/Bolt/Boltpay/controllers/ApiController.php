<?php

/**
 * Class Bolt_Boltpay_ApiController
 *
 * Webhook endpoint.
 */
class Bolt_Boltpay_ApiController extends Mage_Core_Controller_Front_Action {

    /**
     * The starting point for all Api hook request
     */
    public function hookAction() {

        try {
            $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json);

            $boltHelper = Mage::helper('boltpay/api');

            if (!$boltHelper->verify_hook($request_json, $hmac_header)) {
                $exception = new Exception("Hook request failed validation.");
                $this->getResponse()->setHttpResponseCode(400);
                $this->getResponse()->setBody($exception->getMessage());
                $this->getResponse()->setException($exception);
                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            Mage::log('Initiating webhook call', null, 'bolt.log');

            $bodyParams = json_decode(file_get_contents('php://input'), true);

            $reference = $bodyParams['reference'];
            $transactionId = $bodyParams['transaction_id'];
            $hookType = $bodyParams['notification_type'];

            $boltHelper = Mage::helper('boltpay/api');

            $boltHelperBase = Mage::helper('boltpay');
            $boltHelperBase::$from_hooks = true;

            if ($hookType == 'credit') {
                Mage::log('notification_type is credit. Ignoring it');
            }

            $transaction = $boltHelper->fetchTransaction($reference);
            $display_id = $transaction->order->cart->display_id;

            $order = Mage::getModel('sales/order')->loadByIncrementId($display_id);

            if (sizeof($order->getData()) > 0) {
                Mage::log('Order Found. Updating it', null, 'bolt.log');
                $orderPayment = $order->getPayment();

                $newTransactionStatus = Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus($hookType);

                $prevTransactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
                $merchantTransactionId = $orderPayment->getAdditionalInformation('bolt_merchant_transaction_id');
                if ($merchantTransactionId == null || $merchantTransactionId == '') {
                    $orderPayment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
                    $orderPayment->save();
                } elseif ($merchantTransactionId != $transactionId) {
                    throw new Exception(
                        sprintf(
                        'Transaction id mismatch. Expected: %s got: %s', $merchantTransactionId, $transactionId
                        )
                    );
                }

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus);

                $this->getResponse()->setBody('Updated existing order');
                $this->getResponse()->setHttpResponseCode(200);

                return;
            }

            Mage::log('Order not found. Creating one', null, 'bolt.log');

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $display_id)
                ->getFirstItem();

            $quoteId = $bodyParams['quote_id'] ?: $quote->getId();

            if (sizeof($quote->getData()) == 0) {
                Mage::log("Quote not found: $quoteId. Quote must have been already processed.", null, 'bolt.log');
                throw new Exception("Quote not found: $quoteId.  Quote must have been already processed.");
            }

            if (empty($reference) || empty($transactionId)) {
                $exception = new Exception('Reference and/or transaction_id is missing');
                $this->getResponse()->setHttpResponseCode(400);
                $this->getResponse()->setException($exception);
                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            /********************************************************************
             * Order creation is moved to helper API
             ********************************************************************/
            $boltHelper->createOrder($reference, $session_quote_id = null);

            $this->getResponse()->setBody('Order creation was successful');
            $this->getResponse()->setHttpResponseCode(200);

        } catch (BoltPayInvalidTransitionException $boltPayInvalidTransitionException) {

            // An invalid transition is treated as a late queue event and hence will be ignored
            $error_message = $boltPayInvalidTransitionException->getMessage();
            Mage::log($error_message, null, 'bolt.log');
            Mage::log("Late queue event. Returning as OK", null, 'bolt.log');
            $this->getResponse()->setHttpResponseCode(200);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

}