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
 * Trait Bolt_Boltpay_OrderControllerTrait
 *
 * Defines generalized actions and elements used in Bolt order process
 * that is common to both backend and frontend
 */
trait Bolt_Boltpay_OrderControllerTrait {

    /**
     * Creates the Bolt order and returns the Bolt.process javascript.
     */
    public function createAction() {
        try {
            if (!$this->getRequest()->isAjax()) {
                Mage::throwException(Mage::helper('boltpay')->__(get_class()."::createAction called with a non AJAX call"));
            }

            /** @var Bolt_Boltpay_Block_Checkout_Boltpay $block */
            $block = $this->getLayout()->createBlock('boltpay/checkout_boltpay');
            $checkoutType = $this->getRequest()->getParam('checkoutType', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);

            /** @var Mage_Sales_Model_Quote $quote */
            $quote = $block->getSessionQuote($checkoutType);

            $result = array();
            $result['cart_data'] = $this->getCartData($quote, $checkoutType);

            if (@$result['cart_data']['error']) {
                $result['success'] = false;
                $result['error']   = true;
                $result['error_messages'] = $result['cart_data']['error'];
            }

            $this->getResponse()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Retrieves the cart data for the session either from the cache or by
     * creating it anew and then caching the result
     *
     * @param Mage_Sales_Model_Quote $sessionQuote  The Magento session quote
     * @param string                 $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     *
     * @return array  The Bolt cart data as a PHP array containing the order token and the
     *                auto capture value.
     */
    protected function getCartData($sessionQuote, $checkoutType) {
        /** @var Bolt_Boltpay_Block_Checkout_Boltpay $boltpayCheckout */
        $boltpayCheckout = $this->getLayout()->createBlock('boltpay/checkout_boltpay');
        /** @var Bolt_Boltpay_Model_BoltOrder $boltOrder */
        $boltOrder = Mage::getModel('boltpay/boltOrder');

        $cart_data = $boltOrder->getCachedCartData($sessionQuote, $checkoutType);

        if (!$cart_data) {
            $immutableQuote = $this->cloneQuote($sessionQuote, $checkoutType);
            $cart_data = $boltpayCheckout->buildCartData($boltOrder->getBoltOrderToken($immutableQuote, $checkoutType),$checkoutType);
        }

        $boltOrder->cacheCartData($cart_data, $sessionQuote, $checkoutType);

        return $cart_data;
    }


    /**
     * Clones the session quote to be used as an immutable quote for this order
     *
     * @param Mage_Sales_Model_Quote $sessionQuote  The Magento session quote
     * @param string                 $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'

     * @return Mage_Sales_Model_Quote   The cloned quote
     */
    protected function cloneQuote($sessionQuote, $checkoutType) {
        /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
        $shippingAndTaxModel = Mage::getModel('boltpay/shippingAndTax');

        $isMultiPage = ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);

        // For multi-page, remove shipping that may have been added by Magento shipping and tax estimate interface
        if ($isMultiPage) {
            // Resets shipping rate
            $shippingMethod = $sessionQuote->getShippingAddress()->getShippingMethod();
            $shippingAndTaxModel->applyShippingRate($sessionQuote, null);
        }

        /** @var Mage_Sales_Model_Quote $clonedQuote */
        $clonedQuote = Mage::helper('boltpay')->cloneQuote($sessionQuote, $checkoutType);

        // For multi-page, reapply shipping to quote that may be used for shipping and tax estimate
        if ($isMultiPage) {
            $shippingAndTaxModel->applyShippingRate($sessionQuote, $shippingMethod);
        }

        return $clonedQuote;
    }
}