<?php

/**
 * Class Bolt_Boltpay_OrderController
 *
 * Saves the order in Magento system after successful Bolt transaction processing.
 */
class Bolt_Boltpay_OrderController extends Mage_Core_Controller_Front_Action {

    /**
     * Frontend save order action. Called from BoltConnect.process success callback.
     * The actual order creation is done in the helper class, for both frontend and backend (API) requests.
     */
    public function saveAction()
    {
        $bugsnag = Mage::helper('boltpay/bugsnag')-> getBugsnag();

        try {

            $boltHelper = Mage::helper('boltpay/api');

            if (!$this->getRequest()->isAjax()) {
                Mage::throwException("OrderController::saveAction called with a non AJAX call");
            }

            $checkout_session = Mage::getSingleton('checkout/session');

            $reference = $this->getRequest()->getPost('reference');

            $bugsnag->leaveBreadcrumb(
                'OrderController::saveAction',
                \Bugsnag\Breadcrumbs\Breadcrumb::LOG_TYPE,
                ['reference'  => $reference]);

            $session_quote = $checkout_session->getQuote();

            $order = $boltHelper->createOrder($reference, $session_quote->getId());

            $checkout_session->setLastQuoteId($session_quote->getId())
                ->setLastSuccessQuoteId($session_quote->getId())
                ->clearHelperData();

            if ($order) {

                // add order information to the session
                $checkout_session->setLastOrderId($order->getId())
                    ->setRedirectUrl('')
                    ->setLastRealOrderId($order->getIncrementId());
            }

        } catch (Exception $e) {
            $bugsnag->notifyException($e);
            throw $e;
        }


    }
}