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
 * Class Bolt_Boltpay_CartDataLoadTestController
 *
 * Cart Data Load Test Endpoint.
 */
class Bolt_Boltpay_CartDataLoadTestController
    extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_Controller_Traits_WebHookTrait;

    /**
     * @var Bolt_Boltpay_Model_CartDataLoadTest  Object that creates the cart and saves it to the session
     */
    protected $_cartDataLoadTest;

    /**
     * Initializes Controller member variables
     */
    protected function _construct()
    {
        $this->_cartDataLoadTest = Mage::getModel("boltpay/cartDataLoadTest");
    }

    /**
     * Receives json formated request from a Load test,
     * containing cart with an array of cart items in
     * format: {"cart":[{"id": 1} ... ]}
     * Responds with order_token and order_reference
     * of the order that is created in Bolt.
     */
    public function indexAction()
    {
        try {
            // creates a cart in session
            $cartItems = $this->getRequestData()->cart;
            $this->_cartDataLoadTest->saveCart($cartItems);

            // OrderControllerTrait::createAction():
            $boltpayCheckout = Mage::app()->getLayout()->createBlock('boltpay/checkout_boltpay');
            /** @var Mage_Sales_Model_Quote $quote */
            $quote = $boltpayCheckout->getSessionQuote(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);

            // OrderControllerTrait::getCartData():
            /** @var Bolt_Boltpay_Model_BoltOrder $boltOrder */
            $boltOrder = Mage::getModel('boltpay/boltOrder');

            $cartData = $boltOrder->getCachedCartData($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);

            if (!$cartData) {
                $immutableQuote = $boltOrder->cloneQuote($quote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE);
                $cartData = $boltpayCheckout->buildCartData($boltOrder->getBoltOrderToken($immutableQuote, Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE));
            }

            $this->getResponse()->setHeader('Content-type', 'application/json', true);
            $cartData["order_reference"] = $immutableQuote->getParentQuoteId();
            $responseJSON = json_encode($cartData, JSON_PRETTY_PRINT);
            $this->sendResponse(
                200,
                $responseJSON
            );
        } catch (Exception $e) {
            $metaData = array();
            if (isset($quote)){
                $metaData['quote'] = var_export($quote->debug(), true);
            }
            $this->sendResponse(
                422,
                $e->getMessage()
            );

            $this->boltHelper()->notifyException($e, $metaData);
            $this->boltHelper()->logException($e, $metaData);
        }
    }
}