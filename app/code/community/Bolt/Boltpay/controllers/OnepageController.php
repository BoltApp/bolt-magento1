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

require_once 'Mage/Checkout/controllers/OnepageController.php';

/**
 * Class Bolt_Boltpay_OnepageController
 *
 */
class Bolt_Boltpay_OnepageController extends Mage_Checkout_OnepageController
{
    use Bolt_Boltpay_Controller_Traits_ApiControllerTrait;

    /**
     * Sets up this  controller for non-Bolt orders.  For Bolt orders,
     * we will call @see Bolt_Boltpay_Controller_Traits_ApiControllerTrait::preDispatch()
     * explicitly in the success action.
     */
    public function _construct()
    {
        $this->willReturnJson = false;
        $this->requestMustBeSigned = false;

        parent::_construct();
    }


    /**
	 * Order success action.  For Bolt orders, we need to set the session values that are normally set in
	 * a checkout session but are missed when we do pre-auth order creation in a separate
	 * context.
	 */
    public function successAction()
    {
        $requestParams = $this->getRequest()->getParams();

        if (isset($requestParams['bolt_payload'])) {
            // Handle Bolt Orders only

            $this->payload = base64_decode($requestParams['bolt_payload']);
            $this->signature = $requestParams['bolt_signature'];
            $this->preDispatch();  // this handles signature verification

            /** @var Mage_Checkout_Model_Session $checkoutSession */
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession
                ->clearHelperData();

            $quote = $this->getOnepage()->getQuote();

            /* @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId());

            $checkoutSession
                ->setLastQuoteId($requestParams['lastQuoteId'])
                ->setLastSuccessQuoteId($requestParams['lastSuccessQuoteId'])
                ->setLastOrderId($requestParams['lastOrderId'])
                ->setLastRealOrderId($requestParams['lastRealOrderId'])
                ->setLastRecurringProfileIds(explode( ',', $requestParams['lastRecurringProfileIds']));

            Mage::getModel('boltpay/order')->receiveOrder($requestParams['lastRealOrderId'], $this->payload);
        }

		parent::successAction();
    }
}
