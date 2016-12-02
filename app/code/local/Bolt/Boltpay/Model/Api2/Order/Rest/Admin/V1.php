<?php

class Bolt_Boltpay_Model_Api2_Order_Rest_Admin_V1 extends Bolt_Boltpay_Model_Api2_Order
{
    public static $SUCCESS_ORDER_CREATED = array(
        'message' => 'New Order created',
        'status' => 'success',
        'http_response_code' => 201
    );

    public static $SUCCESS_ORDER_UPDATED = array(
        'message' => 'Updated existing order',
        'status' => 'success',
        'http_response_code' => 200
    );

    public function dispatch() {
        parent::dispatch();
        $this->getResponse()->clearHeader("Location");
    }

//    function _retrieve() {
//        $activeQuotes = Mage::getModel('sales/quote')
//            ->getCollection()
//            ->addFieldToFilter('is_active', true)
//            ->getItems();
//
//        $allQuotes = Mage::getModel('sales/quote')
//            ->getCollection()
//            ->getItems();
//
//        $allQuotes = Mage::getModel('sales/quote')
//            ->getCollection()
//            ->getItems();
////            ->addFieldToFilter('entity_id', $quoteId)
////            ->getFirstItem();
//    }

    function _create($couponData)
    {
        try {
            Mage::log('Initiating webhook call', null, 'bolt.log');
            $bodyParams = $this->getRequest()->getBodyParams();
            $quoteId = $bodyParams['quote_id'];
            $reference = $bodyParams['reference'];
            $transactionId = $bodyParams['transaction_id'];
            $hookType = $bodyParams['notification_type'];

            if ($hookType == 'credit') {
                Mage::log('notification_type is credit. Ignoring it');
            }

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('entity_id', $quoteId)
                ->getFirstItem();

            if (sizeof($quote->getData()) == 0) {
                $this->_critical(Mage::helper('boltpay')
                    ->__('Quote not found'), Mage_Api2_Model_Server::HTTP_NOT_FOUND);
            }

            $order = Mage::getModel('sales/order')
                ->getCollection()
                ->addFieldToFilter('quote_id', $quoteId)
                ->getFirstItem();

            if (sizeof($order->getData()) > 0) {
                Mage::log('Order Found. Updating it', null, 'bolt.log');
                $orderPayment = $order->getPayment();

                $newTransactionStatus = $orderPayment
                    ->getMethodInstance()
                    ->translateHookTypeToTransactionStatus($hookType);

                $prevTransactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
                $merchantTransactionId = $orderPayment->getAdditionalInformation('bolt_merchant_transaction_id');
                if ($merchantTransactionId == null || $merchantTransactionId == '') {
                    $orderPayment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
                    $orderPayment->save();
                } elseif ($merchantTransactionId != $transactionId) {
                    $this->_critical(Mage::helper('boltpay')
                        ->__(sprintf(
                            'Transaction id mismatch. Expected: %s got: %s', $merchantTransactionId, $transactionId)),
                            Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
                }

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus);

                $this->getResponse()->addMessage(
                    self::$SUCCESS_ORDER_UPDATED['message'], self::$SUCCESS_ORDER_UPDATED['http_response_code'],
                    array(), 'success');
                $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_OK);
                $this->_render($this->getResponse()->getMessages());
                Mage::log('Order update was successful', null, 'bolt.log');
                return;
            }

            Mage::log('Order not found. Creating one', null, 'bolt.log');
            if (!$quote->getIsActive()) {
                $this->_critical(Mage::helper('boltpay')
                    ->__('Inactive cart/quote cannot be converted to an order'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }

            $payment = $quote->getPayment();

            if (empty($reference) || empty($transactionId)) {
                $this->_critical(Mage::helper('boltpay')
                    ->__('Reference and/or transaction_id is missing'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }

            $newTransactionStatus = $payment->getMethodInstance()->translateHookTypeToTransactionStatus($hookType);
            $payment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
            $payment->setAdditionalInformation('bolt_reference', $reference);
            $payment->setAdditionalInformation('bolt_transaction_status', $newTransactionStatus);
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(true);

            $service = Mage::getModel('sales/service_quote', $quote);
            $quote->collectTotals();
            $service->submitAll();
            $quote->setIsActive(false);
            $quote->save();
            $order = $service->getOrder();

            Mage::getModel('boltpay/payment')->handleOrderUpdate($order);

            $this->getResponse()->addMessage(
                self::$SUCCESS_ORDER_CREATED['message'], self::$SUCCESS_ORDER_CREATED['http_response_code'],
                array(), 'success');
            Mage::log('Order creation was successful', null, 'bolt.log');
            $this->_render($this->getResponse()->getMessages());

        } catch (BoltPayInvalidTransitionException $invalid) {
            // An invalid transition is treated as a late queue event and hence will be ignored
            $error = $invalid->getMessage();
            Mage::log($error, null, 'bolt.log');
            Mage::log("Late queue event. Returning as OK", null, 'bolt.log');
            $this->_critical($error, Mage_Api2_Model_Server::HTTP_OK);
        } catch (Exception $e) {
            $error = $e->getMessage();
            Mage::log($error, null, 'bolt.log');
            $this->_critical($error, Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
    }
}