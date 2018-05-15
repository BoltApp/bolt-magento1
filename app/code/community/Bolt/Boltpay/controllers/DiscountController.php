<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_DiscountController
 * Discount endpoint.
 */
class Bolt_Boltpay_DiscountController extends Mage_Core_Controller_Front_Action
{
    /**
     * Apply discount action
     *
     * Returns resulting discount amount if the coupon is applied successfully and error if it isn't
     */
    public function applyAction()
    {
        $quote = null;
        $responseData = null;

        try {
            $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];
            $request_json = file_get_contents('php://input');

            /** @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');
            if (!$boltHelper->verify_hook($request_json, $hmac_header)) {
                throw new Exception("Failed HMAC Authentication");
            }

            $bodyParams = json_decode(file_get_contents('php://input'), true);

            $quoteId = $bodyParams['quote_id'];
            $couponCode = $bodyParams['coupon'];

            $responseData = $this->initSuccessResponseData($couponCode);

            /** @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->load($quoteId);

            if (!$quote->getItemsCount()) {
                throw new Exception("Cannot apply coupon on empty shopping cart");
            }

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setCouponCode($couponCode)
                ->collectTotals()
                ->save();

            if (strlen($couponCode) > 0 && strpos($couponCode, $quote->getCouponCode()) >= 0) {
                $responseData['amount'] = $this->getQuoteDiscountAmount($quote);
            } else {
                $responseData = $this->setFailureResponseData($responseData, "Invalid coupon code response for '$couponCode'");
            }

            $this->getResponse()->setHttpResponseCode(200);
        } catch (\Exception $e) {
            $responseData = $this->setFailureResponseData($responseData, $e->getMessage());

            $this->getResponse()->setHttpResponseCode(422);

            $metaData = array(
                'response'   => $responseData,
                'quote' => !empty($quote) ? var_export($quote->debug(), true) : null,
            );
            Mage::helper('boltpay/bugsnag')->notifyException($e, $metaData);
        }

        $response = Mage::helper('core')->jsonEncode($responseData);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($response);
    }

    /**
     * @param array $responseData
     * @param string $description
     * @param string $code
     * @return mixed
     */
    protected function setFailureResponseData($responseData = [], $description = '', $code = '')
    {
        $responseData['status'] = 'failure';
        $responseData['error'] = [
            'description' => $description,
            'code' => $code,
        ];
        return $responseData;
    }

    /**
     * @param $couponCode
     * @return array
     */
    protected function initSuccessResponseData($couponCode)
    {
        $responseData = [
            'discount_code' => $couponCode,
            'status' => 'success'
        ];

        return array_merge($responseData, $this->getCouponDetails($couponCode));
    }

    protected function getCouponDetails($couponCode) {
        $couponDetails = array();

        $couponRuleId = Mage::getModel('salesrule/coupon')->load($couponCode, 'code')->getRuleId();

        if(!empty($couponRuleId) && $couponRuleId >= 0) {
            $rule = Mage::getModel('salesrule/rule')->load($couponRuleId);

            if($rule->getRuleId()) {
                $couponDetails['description'] = $rule->getName();
                $couponDetails['type'] = $rule->getSimpleAction();

                return $couponDetails;
            }
        }

    }

    protected function getQuoteDiscountAmount($quote) {
        $totals = $quote->getTotals();
        if(isset($totals["discount"]) && is_numeric($totals["discount"]->getValue()) && $totals["discount"]->getValue() < 0) {
            return abs($totals["discount"]->getValue() * 100);
        }

        return 0;
    }
}