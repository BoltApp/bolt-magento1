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

require_once 'Mage/Checkout/controllers/OnepageController.php';

/**
 * Class Bolt_Boltpay_OnepageController
 */
class Bolt_Boltpay_OnepageController
    extends Mage_Checkout_OnepageController implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Order success action.  For Bolt orders, we need to set the session values that are normally set in
     * a checkout session but are missed when we do pre-auth order creation in a separate
     * context.
     */
    public function successAction()
    {
        $requestParams = $this->getRequest()->getParams();

        // Handle only Bolt orders
        if (!isset($requestParams['bolt_payload'])) {
            parent::successAction();
            return;
        }

        $payload = base64_decode(@$requestParams['bolt_payload']);

        if (!$this->boltHelper()->verify_hook($payload, @$requestParams['bolt_signature'])) {
            // If signature verification fails, we log the error and immediately return control to Magento
            $exception = new Bolt_Boltpay_OrderCreationException(
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
            );
            $this->boltHelper()->notifyException($exception, array(), 'warning');
            $this->boltHelper()->logWarning($exception->getMessage());
            parent::successAction();
            return;
        }

        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');
        $checkoutSession
            ->clearHelperData();

        $quote = $this->getOnepage()->getQuote();

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

        Mage::getModel('boltpay/order')->receiveOrder($requestParams['lastRealOrderId'], $this->payload);

        parent::successAction();
    }
}
