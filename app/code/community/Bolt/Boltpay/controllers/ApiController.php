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
class Bolt_Boltpay_ApiController extends Mage_Core_Controller_Front_Action
{

    /**
     * The starting point for all Api hook request
     */
    public function hookAction() 
    {

        try {
            $hmacHeader = @$_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $requestJson = file_get_contents('php://input');

            $boltHelper = Mage::helper('boltpay/api');

            Mage::helper('boltpay/api')->setResponseContextHeaders();

            if (!$boltHelper->verify_hook($requestJson, $hmacHeader)) {
                $exception = new Exception("Hook request failed validation.");
                $this->getResponse()->setHttpResponseCode(412);
                $this->getResponse()->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6001', 'message' => $exception->getMessage()))));

                $this->getResponse()->setException($exception);
                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            //Mage::log('Initiating webhook call', null, 'bolt.log');

            $bodyParams = json_decode(file_get_contents('php://input'), true);

            $reference = $bodyParams['reference'];
            $transactionId = @$bodyParams['transaction_id'] ?: $bodyParams['id'];
            $hookType = @$bodyParams['notification_type'] ?: $bodyParams['type'];  

            $boltHelper = Mage::helper('boltpay/api');

            $boltHelperBase = Mage::helper('boltpay');

            /* Allows this method to be used even if the Bolt plugin is disabled.  This accounts for orders that have already been processed by Bolt */
            $boltHelperBase::$fromHooks = true;

            if ($hookType == 'credit') {
                //Mage::log('notification_type is credit. Ignoring it');
            }

            $transaction = $boltHelper->fetchTransaction($reference);
            $orderId = $transaction->order->cart->display_id;
            $quoteId = $transaction->order->cart->order_reference;

            /* @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            /***************************************************************************/

            if (!$order->isEmpty()) {
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

                $captureAmount = $this->getCaptureAmount($transaction);

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus, $captureAmount);

                $this->getResponse()->setBody(
                    json_encode(
                        array(
                        'status' => 'success',
                        'message' => "Updated existing order $orderId."
                        )
                    )
                );
                $this->getResponse()->setHttpResponseCode(200);
                return;
            }

            //Mage::log('Order not found. Creating one', null, 'bolt.log');
            /* @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);

            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                'reference'  => $reference,
                'quote_id'   => $quoteId,
                )
            );

            if ($quote->isEmpty()) {
                //Mage::log("Quote not found: $quoteId. Quote must have been already processed.", null, 'bolt.log');
                $exception = new Exception("Quote not found: $quoteId.  Quote must have been already processed.");
                $this->getResponse()->setHttpResponseCode(404)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6012', 'message' => $exception->getMessage()))));

                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            if (empty($reference) || empty($transactionId)) {
                $exception = new Exception('Reference and/or transaction_id is missing');
                $this->getResponse()->setHttpResponseCode(404)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6011', 'message' => $exception->getMessage()))));

                Mage::helper('boltpay/bugsnag')->notifyException($exception);
                return;
            }

            $boltHelper->createOrder($reference, $sessionQuoteId = null);

            $this->getResponse()->setBody(
                json_encode(
                    array(
                    'status' => 'success',
                    'message' => "Order creation was successful"
                    )
                )
            );
            $this->getResponse()->setHttpResponseCode(200);

        } catch (Bolt_Boltpay_InvalidTransitionException $boltPayInvalidTransitionException) {

            if ($boltPayInvalidTransitionException->getOldStatus() == Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD) {
                $this->getResponse()->setHttpResponseCode(503)
                    ->setHeader("Retry-After", "86400")
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6009', 'message' => 'The order is on-hold and requires manual update before this hook is accepted' ))));
            } else {
                // An invalid transition is treated as a late queue event and hence will be ignored
                //Mage::log($errorMessage, null, 'bolt.log');
                //Mage::log("Late queue event. Returning as OK", null, 'bolt.log');
                $this->getResponse()->setHttpResponseCode(200);
            }

        } catch (Exception $e) {
            if(stripos($e->getMessage(), 'Not all products are available in the requested quantity') !== false) {
                $this->getResponse()->setHttpResponseCode(409)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6003', 'message' => $e->getMessage()))));
            }else{
                Mage::helper('boltpay/bugsnag')->notifyException($e);
                $this->getResponse()->setHttpResponseCode(422)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => '6009', 'message' => $e->getMessage()))));
            }
        }
    }

    protected function getCaptureAmount($transaction) {
        if(isset($transaction->capture->amount->amount) && is_numeric($transaction->capture->amount->amount)) {
            return $transaction->capture->amount->amount/100;
        }

        return null;
    }
}
