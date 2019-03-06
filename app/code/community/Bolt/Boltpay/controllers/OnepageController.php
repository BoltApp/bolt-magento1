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
	/**
	 * Order success action.  We need to set the session values that are normally set in
	 * a checkout session but are missed when we do pre-auth order creation in a separate
	 * context.
	 */
    public function successAction()
    {

    	/** @var Mage_Checkout_Model_Session $checkoutSession */
		$checkoutSession = Mage::getSingleton('checkout/session');
		$checkoutSession
			->clearHelperData();

		$requestParams = $this->getRequest()->getParams();

		$quote = $this->getOnepage()->getQuote();
		if (!$requestParams) {
			parse_str($quote->getExtShippingInfo(), $requestParams);
		}

		Mage::helper('boltpay/bugsnag')->notifyException(
			new Exception( Mage::helper('boltpay')->__( 	"Extra Shipping info: "  . $quote->getExtShippingInfo() ) ),
			array(),
			'info'
		);

		/* @var Mage_Sales_Model_Quote $immutableQuote */
		$immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId());
		$quote->setExtShippingInfo($immutableQuote->getExtShippingInfo())->save();

		$checkoutSession
			->setLastQuoteId($requestParams['lastQuoteId'])
			->setLastSuccessQuoteId($requestParams['lastSuccessQuoteId'])
			->setLastOrderId($requestParams['lastOrderId'])
			->setLastRealOrderId($requestParams['lastRealOrderId'])
			->setLastRecurringProfileIds(explode( ',', $requestParams['lastRecurringProfileIds']));

		Mage::helper('boltpay/bugsnag')->notifyException(
			new Exception( Mage::helper('boltpay')->__( 	file_get_contents('php://input') . " : params : ". var_export($requestParams, true)) ),
			array(),
			'info'
		);

		$quote->setIsActive(false)->save();

		parent::successAction();
    }
}
