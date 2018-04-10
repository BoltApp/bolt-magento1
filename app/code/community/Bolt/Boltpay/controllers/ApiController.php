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

            Mage::helper('boltpay/api')->setResponseContextHeaders();

            if (!$boltHelper->verify_hook($request_json, $hmac_header)) {
                $exception = new Exception("Hook request failed validation.");
                $this->getResponse()->setHttpResponseCode(400);
                $this->getResponse()->setBody($exception->getMessage());
                $this->getResponse()->setException($exception);
                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            //Mage::log('Initiating webhook call', null, 'bolt.log');

            $bodyParams = json_decode(file_get_contents('php://input'), true);

            $reference = $bodyParams['reference'];
            $transactionId = $bodyParams['transaction_id'];
            $hookType = $bodyParams['notification_type'];

            $boltHelper = Mage::helper('boltpay/api');

            $boltHelperBase = Mage::helper('boltpay');

            /* Allows this method to be used even if the Bolt plugin is disabled.  This accounts for orders that have already been processed by Bolt */
            $boltHelperBase::$from_hooks = true;

            if ($hookType == 'credit') {
                //Mage::log('notification_type is credit. Ignoring it');
            }

            $transaction = $boltHelper->fetchTransaction($reference);
            $display_id = $transaction->order->cart->display_id;

            $order = Mage::getModel('sales/order')->loadByIncrementId($display_id);

            if (sizeof($order->getData()) > 0) {
                //Mage::log('Order Found. Updating it', null, 'bolt.log');
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

                $this->getResponse()->setBody(json_encode(array(
                    'status' => 'success',
                    'message' => "Updated existing order $display_id."
                )));
                $this->getResponse()->setHttpResponseCode(200);
                return;
            }

            //Mage::log('Order not found. Creating one', null, 'bolt.log');

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $display_id)
                ->getFirstItem();

            $quoteId = $bodyParams['quote_id'] ?: $quote->getId();

            if (sizeof($quote->getData()) == 0) {
                //Mage::log("Quote not found: $quoteId. Quote must have been already processed.", null, 'bolt.log');
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

        } catch (Bolt_Boltpay_InvalidTransitionException $boltPayInvalidTransitionException) {

            // An invalid transition is treated as a late queue event and hence will be ignored
            $error_message = $boltPayInvalidTransitionException->getMessage();
            //Mage::log($error_message, null, 'bolt.log');
            //Mage::log("Late queue event. Returning as OK", null, 'bolt.log');
            $this->getResponse()->setHttpResponseCode(200);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

}