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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
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
     * @param $observer  Observer event contains quote
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
     * @param $observer Observer event contains quote, order, and the bolt transaction reference
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
     * Event: checkout_onepage_controller_success_action
     */
    public function clearShoppingCart() {
        $cartHelper = Mage::helper('checkout/cart');
        $cartHelper->getCart()->truncate()->save();
    }

    /**
     * Event handler called when bolt payment capture.
     * Add the message Magento Order Id: "xxxxxxxxx" to the standard payment capture message.
     *
     * event: sales_order_payment_capture
     *
     * @param $observer Observer event contains payment object
     */
    public function addMessageWhenCapture($observer)
    {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();

        $method = $payment->getMethod();
        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            $message = $this->_addMagentoOrderIdToMessage($order->getIncrementId());
            if (!empty($message)) {
                $observer->getEvent()->getPayment()->setPreparedMessage($message);
            }
        }
    }

    /**
     * Add Magento Order ID to the prepared message.
     *
     * @param number|string $incrementId
     * @return string
     */
    protected function _addMagentoOrderIdToMessage($incrementId)
    {
        if ($incrementId) {
            return $this->boltHelper()->__('Magento Order ID: "%s".', $incrementId);
        }

        return '';
    }
}