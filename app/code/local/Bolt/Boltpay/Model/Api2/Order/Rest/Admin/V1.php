<?php

/**
 * Class Bolt_Boltpay_Model_Api2_Order_Rest_Admin_V1
 *
 * This class implements this magento store's webhooks
 */
class Bolt_Boltpay_Model_Api2_Order_Rest_Admin_V1 extends Bolt_Boltpay_Model_Api2_Order
{
    /**
     * @var array  The response payload for successful order creation
     */
    public static $SUCCESS_ORDER_CREATED = array(
        'message' => 'New Order created',
        'status' => 'success',
        'http_response_code' => 201
    );

    /**
     * @var array  The response payload for successful order updates
     */
    public static $SUCCESS_ORDER_UPDATED = array(
        'message' => 'Updated existing order',
        'status' => 'success',
        'http_response_code' => 200
    );

    /**
     * @inheritdoc
     */
    public function dispatch() {
        try {
            parent::dispatch();
            $this->getResponse()->clearHeader("Location");
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }

    }

    /**
     * API Hook endpoint that processes all web hook requests
     *
     * @param array $couponData    Currently this array is not being used but it called at some points with "filteredData"
     * @return null
     */
    function _create($couponData)
    {
        try {

            Mage::log('Initiating webhook call', null, 'bolt.log');

            $bodyParams = $this->getRequest()->getBodyParams();
            Mage::log(trim(json_encode($bodyParams)), null, 'bolt.log');

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

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $display_id)
                ->getFirstItem();

            $quoteId = $bodyParams['quote_id'] ?: $quote->getId();

            if (sizeof($quote->getData()) == 0) {
                Mage::log("Quote not found: $quoteId. Quote must have been already processed.", null, 'bolt.log');
                $this->_critical(Mage::helper('boltpay')
                    ->__("Quote not found: $quoteId.  Quote must have been already processed."), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }

            if (empty($reference) || empty($transactionId)) {
                $this->_critical(Mage::helper('boltpay')
                    ->__('Reference and/or transaction_id is missing'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }

            /********************************************************************
             * Order creation is moved to helper API
             ********************************************************************/

            $boltHelper->createOrder($reference, $session_quote_id = null);

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


            Mage::helper('boltpay/bugsnag')->addMetaData(
                array(
                    "API HOOKS late queue event" => array (
                        "message" => $error,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            $this->_critical($error, Mage_Api2_Model_Server::HTTP_OK);
        } catch (Exception $e) {
            $error = $e->getMessage();
            Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addMetaData(
                array(
                    "API HOOKS Exception" => array (
                        "message" => $error,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            $this->_critical($error, Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
    }
}