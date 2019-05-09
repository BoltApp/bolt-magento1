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
 * Class Bolt_Boltpay_ApiController
 *
 * Webhook endpoint.
 */
class Bolt_Boltpay_ApiController extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * The starting point for all Api hook request
     */
    public function hookAction()
    {

        try {
            $bodyParams = json_decode($this->payload, true);

            if (isset($bodyParams['type']) && $bodyParams['type'] == "discounts.code.apply") {
                /** @var Bolt_Boltpay_Model_Coupon $couponModel */
                $couponModel = Mage::getModel('boltpay/coupon');
                $couponModel->setupVariables(json_decode(file_get_contents('php://input')));
                $couponModel->applyCoupon();

                return $this->sendResponse($couponModel->getHttpCode(), $couponModel->getResponseData());
            }

            $reference = $bodyParams['reference'];
            $transactionId = @$bodyParams['transaction_id'] ?: $bodyParams['id'];
            $hookType = @$bodyParams['notification_type'] ?: $bodyParams['type'];

            /* Allows this method to be used even if the Bolt plugin is disabled.  This accounts for orders that have already been processed by Bolt */
            Bolt_Boltpay_Helper_Data::$fromHooks = true;

            $transaction = $this->boltHelper()->fetchTransaction($reference);

            $quoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);

            /* If display_id has been confirmed and updated on Bolt, then we should look up the order by display_id */
            $order = Mage::getModel('sales/order')->loadByIncrementId($transaction->order->cart->display_id);

            /* If it hasn't been confirmed, or could not be found, we use the quoteId as fallback */
            if ($order->isObjectNew()) {
                $order =  Mage::getModel('boltpay/order')->getOrderByQuoteId($quoteId);
            }

            if (!$order->isObjectNew()) {
                //Mage::log('Order Found. Updating it', null, 'bolt.log');
                $orderPayment = $order->getPayment();

                $newTransactionStatus = Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus($hookType, $transaction);
                $prevTransactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');

                // Update the transaction id as it may change, ignore the credit hook type,
                // cause the partial refund need original transaction id to process.
                if($hookType !== 'credit'){
                    $orderPayment
                        ->setAdditionalInformation('bolt_merchant_transaction_id', $transaction->id)
                        ->setTransactionId($transaction->id);
                }

                /******************************************************************************************************
                 * TODO: Check the validity of this code.  It has been known to get out of sync and
                 * is not strictly necessary.  In fact, it is redundant with one-to-one quote to bolt order mapping
                 * Therefore, throwing errors will be disabled until fully reviewed.
                 ********************************************************************************************************/
                $merchantTransactionId = $orderPayment->getAdditionalInformation('bolt_merchant_transaction_id');
                if ($merchantTransactionId == null || $merchantTransactionId == '') {
                    $orderPayment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
                    $orderPayment->save();
                } elseif ($merchantTransactionId != $transactionId && $hookType != 'credit') {
                    $this->boltHelper()->notifyException(
                        new Exception(
                            $this->boltHelper()->__("Transaction id mismatch. Expected: %s got: %s", $merchantTransactionId, $transactionId)
                        )
                    );
                }

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->save();
                
                if($hookType == 'credit'){
                    $transactionAmount = $bodyParams['amount']/100;                    
                }
                else{
                    $transactionAmount = $this->getCaptureAmount($transaction);
                }
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus, $transactionAmount, $transaction);

                $this->sendResponse(
                    200,
                    array(
                        'status' => 'success',
                        'display_id' => $order->getIncrementId(),
                        'message' => $this->boltHelper()->__( 'Updated existing order %d', $order->getIncrementId() )
                    )
                );

                return;
            }

            /////////////////////////////////////////////////////
            /// Order was not found.  We will create it.
            /////////////////////////////////////////////////////

            $this->boltHelper()->addBreadcrumb(
                array(
                    'reference'  => $reference,
                    'quote_id'   => $quoteId,
                )
            );

            if (empty($reference) || empty($transactionId)) {
                $exception = new Exception($this->boltHelper()->__('Reference and/or transaction_id is missing'));

                $this->getResponse()->setHttpResponseCode(400)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => 6011, 'message' => $exception->getMessage()))));

                $this->boltHelper()->notifyException($exception);
                return;
            }

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('boltpay/order')->createOrder($reference, $sessionQuoteId = null, false, $transaction);

            $this->getResponse()->setBody(
                json_encode(
                    array(
                        'status' => 'success',
                        'display_id' => $order->getIncrementId(),
                        'message' => $this->boltHelper()->__('Order creation was successful')
                    )
                )
            );
            $this->getResponse()
                ->setHttpResponseCode(201)
                ->sendResponse();

            //////////////////////////////////////////////
            //  Clear parent quote to empty the cart
            //////////////////////////////////////////////
            /** @var Mage_Sales_Model_Quote $parentQuote */
            $parentQuote = Mage::getModel('boltpay/order')->getParentQuoteFromOrder($order);
            $parentQuote->removeAllItems()->save();
            //////////////////////////////////////////////

        } catch (Bolt_Boltpay_InvalidTransitionException $boltPayInvalidTransitionException) {

            if ($boltPayInvalidTransitionException->getOldStatus() == Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD) {
                $this->getResponse() ->setHeader("Retry-After", "86400");
                $this->sendResponse(
                    503,
                    array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $this->boltHelper()->__('The order is on-hold and requires manual merchant update before this hook can be processed') ))
                );
            } else {
                $isNotRefundOrCaptureHook = !in_array($hookType, array(Bolt_Boltpay_Model_Payment::HOOK_TYPE_REFUND, Bolt_Boltpay_Model_Payment::HOOK_TYPE_CAPTURE));
                $isRepeatHook = $newTransactionStatus === $prevTransactionStatus;
                $isRejectionHookForCancelledOrder =
                    ($prevTransactionStatus === Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED)
                    && in_array($hookType, array(Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_REVERSIBLE, Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_IRREVERSIBLE));
                $isAuthHookForCompletedOrder =
                    ($prevTransactionStatus === Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED)
                    && ($hookType === Bolt_Boltpay_Model_Payment::HOOK_TYPE_AUTH);

                $canAssumeHookedIsHandled = $isNotRefundOrCaptureHook && ($isRepeatHook || $isRejectionHookForCancelledOrder || $isAuthHookForCompletedOrder);

                if ( $canAssumeHookedIsHandled )
                {
                    $this->sendResponse(
                        200,
                        array(
                            'status' => 'success',
                            'display_id' => $order->getIncrementId(),
                            'message' => $this->boltHelper()->__('Order already handled, so hook was ignored')
                        )
                    );
                } else {
                    $this->sendResponse(
                        422,
                        array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $this->boltHelper()->__('Invalid webhook transition from %s to %s', $prevTransactionStatus, $newTransactionStatus) ))
                    );
                }
            }
        } catch (Exception $e) {
            if(stripos($e->getMessage(), 'Not all products are available in the requested quantity') !== false) {
                $this->getResponse()->setHttpResponseCode(409)
                    ->setBody(json_encode(array('status' => 'failure', 'error' => array('code' => 6003, 'message' => $e->getMessage()))));
            }else{
                $this->sendResponse(
                    422,
                    array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $e->getMessage()))
                );

                $metaData = array();
                if (isset($quote)){
                    $metaData['quote'] = var_export($quote->debug(), true);
                }

                $this->boltHelper()->notifyException($e, $metaData);
            }
        }
    }

    protected function getCaptureAmount($transaction) {
        if(isset($transaction->capture->amount->amount) && is_numeric($transaction->capture->amount->amount)) {
            return $transaction->capture->amount->amount/100;
        }

        return null;
    }

    /**
     * @param int $httpCode
     * @param array $data
     */
    protected function sendResponse($httpCode, $data = array())
    {
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode($httpCode);
        $this->getResponse()->setBody(json_encode($data));
    }

}
