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
 * Class Bolt_Boltpay_Model_Observer
 *
 * This class implements order event behavior
 */
class Bolt_Boltpay_Model_Observer
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Clears the Shopping Cart except product page checkout order after the success page
     *
     * @param Varien_Event_Observer $observer   An Observer object with an empty event object
     *
     * Event: checkout_onepage_controller_success_action
     * @param $observer
     */
    public function clearShoppingCartExceptPPCOrder()
    {
        $cartHelper = Mage::helper('checkout/cart');
        if (Mage::app()->getRequest()->getParam('checkoutType') == Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_PRODUCT_PAGE) {
            $quoteId = Mage::app()->getRequest()->getParam('session_quote_id');
            Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
        } else {
            $cartHelper->getCart()->truncate()->save();
        }
    }

    /**
     * This will clear the cart cache, forcing creation of a new immutable quote, if
     * the parent quote has been flagged by having a parent quote Id as its own
     * id.
     *
     * event: controller_front_init_before
     *
     * @param Varien_Event_Observer $observer event contains front (Mage_Core_Controller_Varien_Front)
     */
    public function clearCartCacheOnOrderCanceled($observer) {

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if ($quote && is_int($quote->getId()) && $quote->getId() === $quote->getParentQuoteId()) {
            Mage::getSingleton('core/session')->unsCachedCartData();
            // clear the parent quote ID to re-enable cart cache
            $quote->setParentQuoteId(null);
        }
    }

    /**
     * Sets native session variables for the order success method that were made available via params sent by
     * Bolt.  If this is not an order success call invoked by Bolt, then the function is exited.
     *
     * event: controller_front_init_before
     *
     * @param Varien_Event_Observer $observer unused
     */
    public function setSuccessSessionData($observer) {

        $requestParams = Mage::app()->getRequest()->getParams();

        // Handle only Bolt orders
        if (!isset($requestParams['bolt_payload'])) {
            return;
        }

        $payload = @$requestParams['bolt_payload'];
        $signature = base64_decode(@$requestParams['bolt_signature']);

        if (!$this->boltHelper()->verify_hook($payload, $signature)) {
            // If signature verification fails, we log the error and immediately return control to Magento
            $exception = new Bolt_Boltpay_OrderCreationException(
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
            );
            $this->boltHelper()->notifyException($exception, array(), 'warning');
            $this->boltHelper()->logWarning($exception->getMessage());

            return;
        }

        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession
            ->clearHelperData();

        $quote = $checkoutSession->getQuote();

        /* @var Mage_Sales_Model_Quote $immutableQuote */
        $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId());

        $recurringPaymentProfilesIds = array();
        $recurringPaymentProfiles = $immutableQuote->collectTotals()->prepareRecurringPaymentProfiles();

        /** @var Mage_Payment_Model_Recurring_Profile $profile */
        foreach((array)$recurringPaymentProfiles as $profile) {
            $recurringPaymentProfilesIds[] = $profile->getId();
        }

        $checkoutSession
            ->setLastQuoteId($requestParams['lastQuoteId'])
            ->setLastSuccessQuoteId($requestParams['lastSuccessQuoteId'])
            ->setLastOrderId($requestParams['lastOrderId'])
            ->setLastRealOrderId($requestParams['lastRealOrderId'])
            ->setLastRecurringProfileIds($recurringPaymentProfilesIds);

    }

    /**
     * Event handler called when bolt payment capture.
     * Add the message Magento Order Id: "xxxxxxxxx" to the standard payment capture message.
     *
     * event: sales_order_payment_capture
     *
     * @param Varien_Event_Observer $observer Observer event contains payment object
     */
    public function addMessageWhenCapture($observer)
    {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();
        $method = $payment->getMethod();
        $message = '';

        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            $message .= ($incrementId = $order->getIncrementId()) ? $this->boltHelper()->__('Magento Order ID: "%s".', $incrementId) : "";
            if (!empty($message)) {
                $observer->getEvent()->getPayment()->setPreparedMessage($message);
            }
        }
    }
    
    /**
     * Hides the Bolt Pre-auth order states from the admin->Sales->Order list
     *
     * event: sales_order_grid_collection_load_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an orderGridCollection object
     */
    public function hidePreAuthOrders($observer) {
        if ($this->boltHelper()->getExtraConfig('displayPreAuthOrders')) { return; }

        /** @var Mage_Sales_Model_Resource_Order_Grid_Collection $orderGridCollection */
        $orderGridCollection = $observer->getEvent()->getOrderGridCollection();
        $orderGridCollection->addFieldToFilter('main_table.status',
            array(
                'nin'=>array(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
                )
            )
        );
    }

    /**
     * Prevents Magento from changing the Bolt preauth statuses
     *
     * event: sales_order_save_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an order object
     */
    public function safeguardPreAuthStatus($observer) {
        $order = $observer->getEvent()->getOrder();
        if (
            !Bolt_Boltpay_Helper_Data::$fromHooks
            && in_array(
                $order->getOrigData('status'),
                array(
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING,
                    Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_CANCELED
                )
            )
        )
        {
            $order->setStatus($order->getOrigData('status'));
        }
    }

    /**
     * This is the last chance, bottom line price check.  It is done after the submit service
     * has created the order, but before the order is committed to the database.  This allows
     * to get the actual totals that will be stored in the database and catch all unexpected
     * changes.  We have the option to attempt to correct any problems here.  If there remain
     * any unhandled problems, we can throw an exception and avoid complex order rollback.
     *
     * This is called from the observer context
     *
     * event: sales_model_service_quote_submit_before
     *
     * @param Varien_Event_Observer $observer Observer event contains an order and (immutable) quote
     *                                        -  Mage_Sales_Model_Order order
     *                                        -  Mage_Sales_Model_Quote quote
     *
     *                                        The $quote, in turn holds
     *                                        -  Mage_Sales_Model_Quote parent (ONLY pre-auth; will be empty for admin)
     *                                        -  object (bolt) transaction (ONLY pre-auth; will be empty for admin)
     *
     *
     * @throws Exception    if an unknown error occurs
     * @throws Bolt_Boltpay_OrderCreationException if the bottom line price total differs by allowed tolerance
     *
     */
    public function validateBeforeOrderCommit($observer) {
        /** @var  Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');
        $orderModel->validateBeforeOrderCommit($observer);
    }
}