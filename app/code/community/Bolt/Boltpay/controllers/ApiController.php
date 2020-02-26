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

use Bolt_Boltpay_OrderCreationException as OCE;

/**
 * Class Bolt_Boltpay_ApiController
 *
 * Webhook endpoint.
 */
class Bolt_Boltpay_ApiController extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_Controller_Traits_WebHookTrait;

    /**
     * The starting point for all Api hook request
     */
    public function hookAction()
    {
        try {

            $requestData = $this->getRequestData();

            $reference = $requestData->reference;
            $transactionId = @$requestData->transaction_id ?: $requestData->id;
            $hookType = @$requestData->notification_type ?: $requestData->type;
            $incrementId = (strpos(@$requestData->display_id, '|') !== false)
                ? explode("|", $requestData->display_id)[0]
                : @$requestData->display_id;

            /** @var Bolt_Boltpay_Model_Order $orderModel */
            $orderModel = Mage::getModel('boltpay/order');

            if ($hookType === 'failed_payment') {
                $this->handleFailedPaymentHook($incrementId);
                return;
            } else if ($hookType === 'discounts.code.apply') {
                $this->handleDiscountHook();
                return;
            }

            /* If display_id has been confirmed and updated on Bolt, then we should look up the order by display_id */
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

            $transaction = null; # Required initialization, but we only do Bolt API transaction lookups when required

            /* If it hasn't been confirmed, or could not be found, we use the quoteId as fallback */
            if ($order->isObjectNew()) {
                $transaction = $this->boltHelper()->fetchTransaction($reference);
                $quoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);
                $order =  $orderModel->getOrderByQuoteId($quoteId);
            }

            if (!$order->isObjectNew()) {
                ///////////////////////////////////////
                // Order was found.  We will update it
                ///////////////////////////////////////
                Mage::app()->setCurrentStore($order->getStore());

                if (empty($transaction) && $hookType !== 'pending') {
                    // further transaction details are required from the Bolt API to process hook
                    $transaction = $this->boltHelper()->fetchTransaction($reference);
                }

                $orderPayment = $order->getPayment();
                $paymentMethod = strtolower($orderPayment->getMethod());
                if ($paymentMethod !== Bolt_Boltpay_Model_Payment::METHOD_CODE) {
                    // immediately end processing if a non-Bolt order is detected
                    throw new Exception(
                        "Order #{$order->getIncrementId()} is not a Bolt order.  Order type: $paymentMethod"
                    );
                }
                if (!$orderPayment->getAdditionalInformation('bolt_reference')) {
                    if ($hookType === Bolt_Boltpay_Model_Payment::HOOK_TYPE_REJECTED_IRREVERSIBLE) {
                        // This is a special case of failed payment hook where Bolt immediately
                        // rejects the payment irreversibly prior to creating a transaction
                        $this->handleFailedPaymentHook($incrementId);
                        return;
                    }
                    /////////////////////////////////////////////////////////////////////////////
                    /// We've reached a case where authorization was not finalized via the browser
                    /// session.  We'll complete the post authorization steps prior to processing
                    /// the webhook.
                    /////////////////////////////////////////////////////////////////////////////
                    $orderModel->receiveOrder($order, $this->payload);
                    /////////////////////////////////////////////////////////////////////////////
                }

                $newTransactionStatus = Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus($hookType, $transaction);
                $prevTransactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');

                // Update the transaction id as it may change, ignore the credit hook type,
                // cause the partial refund need original transaction id to process.
                if($hookType !== 'credit' && !empty($transaction)){
                    $orderPayment
                        ->setAdditionalInformation('bolt_merchant_transaction_id', $transaction->id)
                        ->setTransactionId($transaction->id);
                }

                $merchantTransactionId = $orderPayment->getAdditionalInformation('bolt_merchant_transaction_id');
                if ($merchantTransactionId == null || $merchantTransactionId == '') {
                    $orderPayment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
                    $orderPayment->save();
                }

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->save();

                if($hookType == 'credit'){
                    $transactionAmount = $requestData->amount/100;
                }
                else{
                    $transactionAmount = $this->getCaptureAmount($transaction);
                }
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus, $transactionAmount, $transaction);

                $this->boltHelper()->logInfo($this->boltHelper()->__( 'Updated existing order %d', $order->getIncrementId() ));

                $this->sendResponse(
                    200,
                    array(
                        'status' => 'success',
                        'display_id' => $order->getIncrementId(),
                        'message' => $this->boltHelper()->__( 'Updated existing order %d', $order->getIncrementId() )
                    )
                );
            }

            /////////////////////////////////////////////////////
            /// Order was not found
            /// Create order from orphaned transaction
            /////////////////////////////////////////////////////
            $orderModel->createOrder($reference, null, false, $transaction);
            throw new Exception("Could not find order ".$transaction->order->cart->display_id. " Created it instead.");

        } catch (Bolt_Boltpay_InvalidTransitionException $boltPayInvalidTransitionException) {
            $this->boltHelper()->logException($boltPayInvalidTransitionException);
            $newTransactionStatus = $boltPayInvalidTransitionException->getNewStatus();
            $prevTransactionStatus = $boltPayInvalidTransitionException->getOldStatus();

            if ($boltPayInvalidTransitionException->getOldStatus() == Bolt_Boltpay_Model_Payment::TRANSACTION_ON_HOLD) {
                $message = $this->boltHelper()->__('The order is on-hold and requires manual merchant update before this hook can be processed');
                $this->boltHelper()->logWarning($message);

                $this->getResponse() ->setHeader("Retry-After", "86400");

                $this->sendResponse(
                    503,
                    array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $message ))
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
                    $message = $this->boltHelper()->__('Order already handled, so hook was ignored');
                    $this->boltHelper()->logWarning($message);
                    $this->sendResponse(
                        200,
                        array(
                            'status' => 'success',
                            'display_id' => $order->getIncrementId(),
                            'message' => $message
                        )
                    );
                } else {
                    $message = $this->boltHelper()->__('Invalid webhook transition from %s to %s', $prevTransactionStatus, $newTransactionStatus);
                    $this->boltHelper()->logWarning($message);
                    $this->sendResponse(
                        422,
                        array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $message ))
                    );
                }
            }
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logException($e);

            $this->sendResponse(
                422,
                array('status' => 'failure', 'error' => array('code' => 6009, 'message' => $e->getMessage()))
            );
        }
    }

    /**
     * Creates a Bolt order in response to a Bolt-side pre-authorization call for order creation
     *
     * @throws Zend_Controller_Response_Exception if there is an error in sending a response back to the caller
     * @throws Mage_Core_Model_Store_Exception  if there is a problem locating a reference to the underlying store
     */
    public function create_orderAction() {

        benchmark( "Starting create order controller endpoint" );

        try {
            $transaction = $this->getRequestData();
            $displayId = $transaction->order->cart->display_id;

            /** @var  Bolt_Boltpay_Model_Order $orderModel */
            $orderModel = Mage::getModel('boltpay/order');
            $immutableQuoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);

            if (strpos($displayId, '|') !== false) {
                /* This is when the order has not already been created, nor order success URL sent to Bolt */
                $order = $orderModel->getOrderByQuoteId($immutableQuoteId);
                benchmark( "Finished looking up order details by quote id" );
            } else {
                /* @var Mage_Sales_Model_Order $order */
                $order = Mage::getModel('sales/order')->loadByIncrementId($displayId);
                benchmark( "Finished looking up order details by display id" );
            }

            if ($order->isObjectNew()) {
                benchmark( "Calling createOrder function" );
                /** @var Mage_Sales_Model_Order $order */
                $order = $orderModel->createOrder($reference = null, $sessionQuoteId = null, $isPreAuthCreation = true, $transaction);
                benchmark( "Completed createOrder function" );
            } else {
                if ($order->getStatus() === 'canceled_bolt') {
                    throw new Bolt_Boltpay_OrderCreationException(
                        OCE::E_BOLT_CART_HAS_EXPIRED,
                        OCE::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED
                    );
                }
            }

            /////////////////////////////////
            // create success order URL
            /////////////////////////////////
            $orderSuccessUrl = $this->boltHelper()->doFilterEvent(
                'bolt_boltpay_filter_success_url',
                $this->createSuccessUrl($order, $immutableQuoteId),
                [
                    'order' => $order,
                    'quote_id' => $immutableQuoteId
                ]
            );
            /////////////////////////////////

            # We flush the response immediately, however, we'll continue to process.  As such, third party code
            # may be deferred to run after as to not block quick notification to Bolt of order creation or failure
            $this->sendResponse(
                200,
                array(
                    'status' => 'success',
                    'display_id' => $order->getIncrementId(),
                    'total' => (int)($order->getGrandTotal() * 100),
                    'order_received_url' => $orderSuccessUrl
                ),
                false
            );
        } catch ( Bolt_Boltpay_OrderCreationException $orderCreationException ) {
            $this->sendResponse(
                $orderCreationException->getHttpCode(),
                $orderCreationException->getJson(),
                false
            );

            //////////////////////////////////////////////////////
            /// Send the computed cart to Bugsnag for comparison
            //////////////////////////////////////////////////////
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($order->getQuoteId());
            $computedCart = Mage::getModel('boltpay/boltOrder')->buildCart($immutableQuote, false );
            $this->boltHelper()->notifyException($orderCreationException, array( 'magento_order_details' => json_encode($computedCart)));
            //////////////////////////////////////////////////////

        } finally {
            benchmark( "Response sent to Bolt");
        }
    }

    /**
     * If present, returns the capture amount on a transaction
     *
     * @param object $transaction  The Bolt transaction sent as the body of the API request
     *
     * @return float|int|null   returns the transaction amount as a float, if present, otherwise null
     */
    protected function getCaptureAmount($transaction) {
        if(isset($transaction->capture->amount->amount) && is_numeric($transaction->capture->amount->amount)) {
            return $transaction->capture->amount->amount/100;
        }

        return null;
    }

    /**
     * Creates the success url for Bolt to forward the customer browser to upon transaction authorization
     *
     * @param Mage_Sales_Model_Order    $order              Recently created pre-auth order
     * @param Mage_Sales_Model_Quote    $immutableQuoteId   Id of quote used to create the pre-auth order
     *
     * @return string   The URL for which Bolt is to forward the browser.  It contains variables normally
     *                  stored as session values as URL parameter
     *
     * @throws Mage_Core_Model_Store_Exception  if for any reason the store can not be found to generate the URL
     */
    private function createSuccessUrl($order, $immutableQuoteId) {
        $successUrlPath = $this->boltHelper()->getMagentoUrl(
            Mage::getStoreConfig('payment/boltpay/successpage'),
            [
                '_query' => [
                    'lastQuoteId' => $immutableQuoteId,
                    'lastSuccessQuoteId' => $immutableQuoteId,
                    'lastOrderId' => $order->getId(),
                    'lastRealOrderId' => $order->getIncrementId()
                ]
            ]
        );
        return $successUrlPath;
    }

    /**
     * Handles failed payment web hooks.  It attempts to cancel a specified pre-auth order
     * in addition to invalidating the cache associated with that orders session.
     *
     * @param string $displayId  the increment ID of the order that should be cancelled.
     *
     * @throws Zend_Controller_Response_Exception if there is an unexpected error in sending a response
     * @throws Mage_Core_Exception if the order cannot be canceled
     */
    private function handleFailedPaymentHook($displayId) {
        /** @var Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($displayId, 'increment_id');

        if (!$order->isObjectNew()) {
            //////////////////////////////////////////////////////////////////////////////////////
            // Remove order and expire cache only if the order is still pending authorization
            // Otherwise, ignore the failed payment hook because it arriving out of sync as
            // a payment has already been recorded
            //////////////////////////////////////////////////////////////////////////////////////
            $payment = $order->getPayment();
            if (
                $payment->getAdditionalInformation('bolt_reference')
                || $payment->getAuthorizationTransaction()
                || $payment->getLastTransId()
            ) {
                $message = $this->boltHelper()->__(
                    'Payment was already recorded. The failed payment hook for order %s seems out of sync.',
                    $order->getIncrementId()
                );
                $this->boltHelper()->logWarning($message);
                $this->boltHelper()->notifyException(new Exception($message), [], 'warning');
                $this->sendResponse(
                    200,
                    array(
                        'status' => 'success',
                        'message' => $message
                    ),
                    $exitImmediately = true
                );
            }
            //////////////////////////////////////////////////////////////////////////////////////

            $orderModel->removePreAuthOrder($order);
        }

        $this->sendResponse(
            200,
            array(
                'status' => 'success',
                'message' => $this->boltHelper()->__('Order %s has been canceled prior to authorization', $displayId)
            )
        );
    }

    /**
     * Handles discount web hooks
     *
     * @throws Zend_Controller_Response_Exception if there is an unexpected error in sending a response
     */
    private function handleDiscountHook() {
        /** @var Bolt_Boltpay_Model_Coupon $couponModel */
        $couponModel = Mage::getModel('boltpay/coupon');
        $couponModel->setupVariables(json_decode($this->payload));
        $couponModel->applyCoupon();

        $this->sendResponse($couponModel->getHttpCode(), $couponModel->getResponseData());
    }
}