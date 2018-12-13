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
                Mage::throwException(Mage::helper('boltpay')->__("Bolt_Boltpay_Adminhtml_Sales_Order_CreateController::createAction called with a non AJAX call"));
            }

            /** @var Bolt_Boltpay_Block_Checkout_Boltpay $block */
            $block = $this->getLayout()->createBlock('boltpay/checkout_boltpay');
            $checkoutType = $this->getRequest()->getParam('checkoutType', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);

            /** @var Mage_Sales_Model_Quote $quote */
            $quote = $block->getSessionQuote($checkoutType);

            /** @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::helper('boltpay')->cloneQuote($quote, $checkoutType);
            $result['cart_data'] = $block->buildCartData( $block->getBoltOrderToken($immutableQuote, $checkoutType) );

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
}