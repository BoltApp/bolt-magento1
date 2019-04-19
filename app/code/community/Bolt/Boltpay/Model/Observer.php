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
     * Adds the Bolt User Id to the newly registered customer.
     *
     * event: bolt_boltpay_authorization_after
     *
     * @param Varien_Event_Observer $observer  Observer event contains `quote`
     */
    public function setBoltUserId($observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $session = Mage::getSingleton('customer/session');

        try {
            $customer = $quote->getCustomer();
            $boltUserId = $session->getBoltUserId();

            if ($customer != null && $boltUserId != null) {
                if ($customer->getBoltUserId() == null || $customer->getBoltUserId() == 0) {
                    //Mage::log("Bolt_Boltpay_Model_Observer.saveOrderAfter: Adding bolt_user_id to the customer from the quote", null, 'bolt.log');
                    $customer->setBoltUserId($boltUserId);
                    $customer->save();
                }
            }
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
        }

        $session->unsBoltUserId();
    }

    /**
     * Event handler called after Bolt confirms order authorization
     *
     * event: bolt_boltpay_authorization_after
     *
     * @param Varien_Event_Observer $observer Observer event contains `quote`, `order`, and the bolt transaction `reference`
     *
     * @throws Mage_Core_Exception if the bolt transaction reference is an object instead of expected string
     */
    public function completeAuthorize($observer)
    {
        /* @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        $reference = $observer->getEvent()->getReference();

        Mage::getModel('boltpay/order')->getParentQuoteFromOrder($order)->setIsActive(false)->save();
        $order->getPayment()->setAdditionalInformation('bolt_reference', $reference)->save();
        Mage::getModel('boltpay/order')->sendOrderEmail($order);
    }

    /**
     * Clears the Shopping Cart after the success page
     *
     * @param Varien_Event_Observer $observer   An Observer object with an empty event object
     *
     * Event: checkout_onepage_controller_success_action
     */
    public function clearShoppingCart($observer) {
        $cartHelper = Mage::helper('checkout/cart');
        $cartHelper->getCart()->truncate()->save();
    }

    /**
     * This will clear the cart cache, forcing creation of a new immutable quote, if
     * the parent quote has been flagged by having a parent quote Id as its on
     * id.
     *
     * event: controller_front_init_before
     *
     * @param Varien_Event_Observer $observer event contains front (Mage_Core_Controller_Varien_Front)
     */
    public function clearCartCacheOnOrderCanceled($observer) {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if ($quote->getId() === $quote->getParentQuoteId()) {
            Mage::getSingleton('core/session')->unsCachedCartData();
            // clear the parent quote ID to re-enable cart cache
            $quote->setParentQuoteId(null);
        }
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
    public function filterPreAuthOrders($observer) {
        /** @var Mage_Sales_Model_Resource_Order_Grid_Collection $orderGridCollection */
        $orderGridCollection = $observer->getEvent()->getOrderGridCollection();
        $orderGridCollection->addFieldToFilter('main_table.status',array('nin'=>array('pending_bolt','canceled_bolt')));
    }
}