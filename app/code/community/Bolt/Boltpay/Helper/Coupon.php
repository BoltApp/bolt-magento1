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
 * Class Bolt_Boltpay_Helper_Coupon
 *
 * Base Magento Bolt Coupon Helper class
 *
 */
class Bolt_Boltpay_Helper_Coupon extends Mage_Core_Helper_Abstract
{
    const ERR_INSUFFICIENT_INFORMATION = 6200;
    const ERR_CODE_INVALID = 6201;
    const ERR_CODE_EXPIRED = 6202;
    const ERR_CODE_NOT_AVAILABLE = 6203;
    const ERR_CODE_LIMIT_REACHED = 6204;
    const ERR_MINIMUM_CART_AMOUNT_REQUIRED = 6205;
    const ERR_UNIQUE_EMAIL_REQUIRED = 6206;
    const ERR_ITEMS_NOT_ELIGIBLE = 6207;
    const ERR_SERVICE = 6001;

    /**
     * Response information sent to bolt
     */
    protected $responseStatus; // Bolt error status code
    protected $responseError; // Error description
    protected $responseCart; // Response quote
    protected $discountInfo; // Details about how much discount was applied as a result of coupon
    protected $httpCode; // HTTP response code

    protected $immutableQuote = null;
    protected $parentQuote = null; // Session quote
    protected $coupon = null;
    protected $rule = null;

    protected $requestObject = null;

    /**
     * Applies coupon from hook input to customer quote if the coupon is valid.
     *
     * @return bool Whether or not coupon was applied
     */
    public function applyCoupon()
    {
        try {
            $this->setupVariables();
            $this->validateCoupon();
            $this->applyCouponToQuotes();
            $this->validateAfterApplyingCoupon();

            $this->setSuccessResponse();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Sets up any variables needed to process coupon.
     *
     * @throws Exception     thrown if input fails to be read.
     */
    protected function setupVariables() {
        try {
            $this->requestObject = json_decode(file_get_contents('php://input'));
        } catch (\Exception $e) {
            $this->setErrorResponseAndThrowException(
                self::ERR_SERVICE,
                'Unknown error getting API request.',
                422
            );

            throw $e;
        }
    }

    /**
     * Makes several checks to confirm that coupon is valid.
     */
    protected function validateCoupon()
    {
        $this->validateEmptyCoupon();
        $this->validateCouponExists();
        $this->validateRuleExists();
        $this->validateCartIdentificationData();
        $this->validateOrderCreation();
        $this->validateSessionQuote();
        $this->validateImmutableQuote();
        $this->validateEmptyCart();
        $this->validateToDateForRule();
        $this->validateFromDateForRule();
        $this->validateCouponUsageLimits();
        $this->validateCouponCustomerUsageLimits();
        $this->validateRuleCustomerUsageLimits();
    }

    /**
     * Verifies the coupon isn't an empty string.
     */
    protected function validateEmptyCoupon()
    {
        // Check if empty coupon was sent
        if ($this->getCouponCode() === '') {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID,
                'No coupon code provided',
                422
            );
        }
    }

    /**
     * Verifies the coupon exists.
     */
    protected function validateCouponExists()
    {
        $coupon = $this->getCoupon();
        // Check if the coupon exists
        if ($coupon->isEmpty() || $coupon->isObjectNew()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', @$this->getCouponCode()),
                404
            );
        }
    }


    /**
     * Verifies the coupon rule exists.
     */
    protected function validateRuleExists()
    {
        // Load the coupon discount rule
        $rule = $this->getRule();

        // check if the rule exists
        if ($rule->isEmpty() || $rule->isObjectNew()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', @$this->getCouponCode()),
                404
            );
        }
    }

    /**
     * Verifies that the identifying factors for the care parent quote, immutable quote, and increment id were passed in.
     */
    protected function validateCartIdentificationData()
    {
        $parentQuoteId = $this->getParentQuoteId();
        $incrementId = $this->getIncrementId();
        $immutableQuoteId = $this->getImmutableQuoteId();

        if (empty($parentQuoteId) || empty($incrementId) || empty($immutableQuoteId)) {
            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                422
            );
        }
    }

    /**
     * Verifies that order doesn't already exist (based on increment id.
     */
    protected function validateOrderCreation()
    {
        // Check if the order has already been created
        $incrementId = $this->getIncrementId();
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if ($order->getId()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The order #%s has already been created.', $incrementId),
                422
            );
        }
    }

    /**
     * Verifies that the session quote exists.
     */
    protected function validateSessionQuote()
    {
        $parentQuote = $this->getParentQuote();

        if ($parentQuote->isEmpty() || !$parentQuote->getIsActive()) {

            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The session quote reference [%s] was not found.', $this->requestObject->cart->order_reference),
                404
            );
        }
    }

    /**
     * Verifies that the immutable quote exists.
     */
    protected function validateImmutableQuote()
    {
        // check the existence of child quote
        $immutableQuote = $this->getImmutableQuote();

        if ($immutableQuote->isEmpty()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The immutable quote reference [%s] was not found.', $immutableQuote->getId()),
                404
            );
        }
    }

    /**
     * Verifies that the immutable quote is not empty and has items in the cart.
     */
    protected function validateEmptyCart()
    {
        $immutableQuote = $this->getImmutableQuote();
        // check if cart is empty
        if (!$immutableQuote->getItemsCount()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The quote for order reference [%s] is empty.', $immutableQuote->getId()),
                422
            );
        }
    }

    /**
     * Verifies that the coupon isn't expired
     */
    protected function validateToDateForRule()
    {
        $rule = $this->getRule();
        $immutableQuote = $this->getImmutableQuote();

        $date = $rule->getToDate();
        if ($date && date('Y-m-d', strtotime($date)) < date('Y-m-d')) {

            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_EXPIRED,
                sprintf('The coupon code [%s] is expired.', $this->getCouponCode()),
                422,
                $immutableQuote
            );
        }
    }

    /**
     * Verifies that the from date is in the past.
     */
    protected function validateFromDateForRule()
    {
        $rule = $this->getRule();
        $immutableQuote = $this->getImmutableQuote();

        $date = $rule->getFromDate();
        if ($date && date('Y-m-d', strtotime($date)) > date('Y-m-d')) {

            $desc = 'Code available from ' . Mage::helper('core')->formatDate(
                    new \DateTime($rule->getFromDate()),
                    \IntlDateFormatter::MEDIUM
                )->format('m/d/Y');
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_NOT_AVAILABLE,
                $desc,
                422,
                $immutableQuote
            );
        }
    }

    /**
     * Verifies that the coupon hasn't been used too many times.
     */
    protected function validateCouponUsageLimits()
    {
        $coupon = $this->getCoupon();
        $immutableQuote = $this->getImmutableQuote();
        // Check coupon usage limits.
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {

            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', $this->getCouponCode()),
                422,
                $immutableQuote
            );
        }
    }

    /**
     * Verifies that the coupon hasn't been used too many times for the current customer.
     */
    protected function validateCouponCustomerUsageLimits()
    {
        $coupon = $this->getCoupon();
        $immutableQuote = $this->getImmutableQuote();

        if ($customerId = $immutableQuote->getCustomerId()) {
            if ($customerId && $coupon->getUsagePerCustomer()) {
                $couponUsage = new Varien_Object();
                Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon(
                    $couponUsage,
                    $customerId,
                    $coupon->getId()
                );
                if ($couponUsage->getCouponId() && $couponUsage->getTimesUsed() >= $coupon->getUsagePerCustomer()) {
                    $this->setErrorResponseAndThrowException(
                        self::ERR_CODE_LIMIT_REACHED,
                        sprintf('The code [%s] has exceeded usage limit.', $this->getCouponCode()),
                        422,
                        $immutableQuote
                    );
                }
            }
        }
    }

    /**
     * Verifies that the rule hasn't been used too many times.
     */
    protected function validateRuleCustomerUsageLimits()
    {
        $rule = $this->getRule();
        $immutableQuote = $this->getImmutableQuote();
        // rule per customer usage
        if ($usesPerCustomer = $rule->getUsesPerCustomer()) {
            $customerId = $immutableQuote->getCustomerId();
            $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $rule->getId());
            if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $usesPerCustomer) {
                $this->setErrorResponseAndThrowException(
                    self::ERR_CODE_LIMIT_REACHED,
                    sprintf('The code [%s] has exceeded usage limit.', $this->getCouponCode()),
                    422,
                    $immutableQuote
                );
            }
        }
    }

    /**
     * Attempts to apply the coupon to both the immutable and session quote.
     */
    protected function applyCouponToQuotes()
    {
        try {

            $parentQuote = $this->getParentQuote();
            $immutableQuote = $this->getImmutableQuote();
            $couponCode = $this->getCouponCode();

            // Try applying to parent first
            $this->applyCouponToQuote($parentQuote, $couponCode);
            // Apply coupon to clone
            $this->applyCouponToQuote($immutableQuote, $couponCode);
        } catch (\Exception $e) {
            $this->setErrorResponseAndThrowException(
                self::ERR_SERVICE,
                $e->getMessage(),
                422,
                @$immutableQuote
            );
        }
    }

    /**
     * Applies the coupon code to the quote and recalculates totals.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $couponCode
     */
    protected function applyCouponToQuote($quote, $couponCode)
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode($couponCode);

        Mage::helper('boltpay')->collectTotals($quote, true)->save();
    }

    /**
     * Verifies that the coupon code was applied to the quote.
     *
     * @throws Exception     thrown if coupon in quote doesn't match the coupon applied.
     */
    protected function validateAfterApplyingCoupon()
    {
        $immutableQuote = $this->getImmutableQuote();
        if ($immutableQuote->getCouponCode() != $this->getCouponCode()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_SERVICE,
                sprintf("Invalid coupon code response for coupon [%s]", $this->getCouponCode()),
                422,
                $immutableQuote
            );
        }
    }

    /**
     * Sets the response error and the http status code and then throws an exception.
     * @param int $errCode
     * @param string $message
     * @param int $httpStatusCode
     * @throws Exception
     */
    protected function setErrorResponseAndThrowException($errCode, $message, $httpStatusCode)
    {
        $this->responseError = [
            'code' => $errCode,
            'message' => $message,
        ];

        $this->httpCode = $httpStatusCode;
        $this->responseCart = $this->getCartTotals();
        throw new \Exception($message);
    }

    /**
     * Gets all cart totals from Bolt buildCart.
     *
     * @return array
     */
    protected function getCartTotals()
    {
        $quote = $this->getImmutableQuote();
        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');
        $items = @$quote->getAllVisibleItems();

        $cart = $boltHelper->buildCart($quote, $items, 'multi-page');
        return [
            'total_amount' => $cart['total_amount'],
            'tax_amount' => $cart['tax_amount'],
            'discounts' => $cart['discounts'],
        ];
    }

    /**
     * Sets the success response (discount code, amount, description, and type). Also sets the HTTP status to 200.
     *
     * @return array
     */
    protected function setSuccessResponse()
    {
        $this->responseCart = $this->getCartTotals();

        $immutableQuote = $this->getImmutableQuote();
        $address = $immutableQuote->isVirtual() ?
            $immutableQuote->getBillingAddress() :
            $immutableQuote->getShippingAddress();

        $this->discountInfo = [
            'discount_code' => $this->getCouponCode(),
            'discount_amount' => abs(round($address->getDiscountAmount() * 100)),
            'description' => Mage::helper('boltpay')->__('Discount ') . $address->getDiscountDescription(),
            'discount_type' => $this->convertToBoltDiscountType($this->getRule()->getSimpleAction()),
        ];

        $this->httpCode = 200;
    }

    /**
     * Maps the Magento discount type to a Bolt discount type
     *
     * @return string
     */
    protected function convertToBoltDiscountType($type)
    {
        switch ($type) {
            case "by_fixed":
            case "cart_fixed":
                return "fixed_amount";
            case "by_percent":
                return "percentage";
            case "by_shipping":
                return "shipping";
        }
        return "";
    }

    /**
     * Gets the session quote if it exists.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function getParentQuote()
    {
        if (!$this->parentQuote) {
            if (!$this->requestObject) {
                return null;
            }

            /** @var Mage_Sales_Model_Quote $parentQuote */
            $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->getParentQuoteId());
            $this->parentQuote = $parentQuote;
        }

        return $this->parentQuote;
    }

    /**
     * Gets the immutable quote if it exists.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function getImmutableQuote()
    {
        if (!$this->immutableQuote) {
            if (!$this->requestObject) {
                return null;
            }

            /** @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->getImmutableQuoteId());
            $this->immutableQuote = $immutableQuote;
        }

        return $this->immutableQuote;
    }

    /**
     * Get the coupon based on the coupon code if it exists.
     *
     * @return object
     */
    protected function getCoupon()
    {
        if (!$this->coupon) {
            if (!$this->requestObject) {
                return null;
            }
            // Load the coupon
            $coupon = Mage::getModel('salesrule/coupon')->load($this->getCouponCode(), 'code');
            $this->coupon = $coupon;
        }
        return $this->coupon;
    }

    /**
     * Gets rule based on the coupon.
     *
     * @return object
     */
    protected function getRule()
    {
        if (!$this->rule) {
            if (!$this->requestObject) {
                return null;
            }

            $coupon = $this->getCoupon();
            // Load the coupon discount rule
            $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
            $this->rule = $rule;
        }
        return $this->rule;
    }

    /**
     * Gets the coupon code from the request.
     *
     * @return string
     */
    protected function getCouponCode()
    {
        return @$this->requestObject->discount_code;
    }

    /**
     * Gets increment id from the request.
     *
     * @return string
     */
    protected function getIncrementId()
    {
        // The latter two are transmitted as display_id field, separated by " | "
        list($incrementId, $immutableQuoteId) = array_pad(
            explode('|', @$this->requestObject->cart->display_id),
            2,
            null
        );
        return $incrementId;
    }

    /**
     * Gets immutable quote id from the request.
     *
     * @return int
     */
    protected function getImmutableQuoteId()
    {
        // The latter two are transmitted as display_id field, separated by " | "
        list($incrementId, $immutableQuoteId) = array_pad(
            explode('|', @$this->requestObject->cart->display_id),
            2,
            null
        );
        return $immutableQuoteId;
    }

    /**
     * Gets session quote id from the request.
     *
     * @return int
     */
    public function getParentQuoteId()
    {
        return @$this->requestObject->cart->order_reference;
    }

    /**
     * Get response data for bolt response
     *
     * @return array
     */
    public function getResponseData()
    {
        if ($this->responseError) {
            return [
                'status' => 'error',
                'error' => $this->responseError,
                'cart' => $this->responseCart
            ];
        }

        return array_merge(
            [
                'status' => 'success',
                'cart' => $this->responseCart
            ],
            $this->discountInfo
        );
    }

    /**
     * Gets HTTP code for Bolt response.
     *
     * @return int
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }
}