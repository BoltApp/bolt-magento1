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
 * Class Bolt_Boltpay_Model_Coupon
 *
 * Base Magento Bolt Coupon Model class
 *
 */
class Bolt_Boltpay_Model_Coupon extends Mage_Core_Model_Abstract
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
    protected $mockTransaction = null;

    /** @var Bolt_Boltpay_Helper_Transaction|null  */
    protected $transactionHelper = null;

    /**
     * Bolt_Boltpay_Model_Coupon constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->transactionHelper = Mage::helper('boltpay/transaction');
    }

    /**
     * Applies coupon from hook input to customer quote if the coupon is valid.
     *
     * @return bool Whether or not coupon was applied
     */
    public function applyCoupon()
    {
        try {

            if (!$this->requestObject){
                throw new \Bolt_Boltpay_BadInputException('Need to set setup variables in order to apply coupon');
            }

            $this->validateCoupon();
            $this->applyCouponToQuotes();
            $this->validateAfterApplyingCoupon();

            $this->setSuccessResponse();
        } catch (\Bolt_Boltpay_BadInputException $e){
            return false;
        } catch (\Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            return false;
        }
        return true;
    }

    /**
     * Sets up any variables needed to process coupon.
     *
     * @param $requestObject
     */
    public function setupVariables($requestObject)
    {
        $this->requestObject = $requestObject;
        $this->mockTransaction = (object) array("order" => $requestObject );
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
        $this->validateOrderExists();
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
     *
     * @throws Exception
     */
    public function validateEmptyCoupon()
    {
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
     *
     * @throws Exception
     */
    public function validateCouponExists()
    {
        $coupon = $this->getCoupon();
        if ($coupon->isEmpty() || $coupon->isObjectNew()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', $this->getCouponCode()),
                404
            );
        }
    }


    /**
     * Verifies the coupon rule exists.
     *
     * @throws Exception
     */
    public function validateRuleExists()
    {
        $rule = $this->getRule();
        if ($rule->isEmpty() || $rule->isObjectNew()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', $this->getCouponCode()),
                404
            );
        }
    }

    /**
     * Verifies that the identifying factors for the care parent quote, immutable quote, and increment id were passed
     * in.
     *
     * @throws Exception
     */
    public function validateCartIdentificationData()
    {
        $parentQuoteId = $this->getParentQuoteId();
        $incrementId = $this->transactionHelper->getIncrementIdFromTransaction($this->mockTransaction);
        $immutableQuoteId = $this->transactionHelper->getImmutableQuoteIdFromTransaction($this->mockTransaction);

        if (empty($parentQuoteId) || empty($incrementId) || empty($immutableQuoteId)) {
            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                422
            );
        }
    }

    /**
     * Verifies that order doesn't already exist based on increment id.
     *
     * @throws Exception
     */
    public function validateOrderExists()
    {
        $incrementId = $this->transactionHelper->getIncrementIdFromTransaction($this->mockTransaction);
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
     *
     * @throws Exception
     */
    public function validateSessionQuote()
    {
        $parentQuote = $this->getParentQuote();

        if ($parentQuote->isEmpty() || !$parentQuote->getIsActive()) {

            $this->setErrorResponseAndThrowException(
                self::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The session quote reference [%s] was not found.', $this->getParentQuoteId()),
                404
            );
        }
    }

    /**
     * Verifies that the immutable quote exists.
     *
     * @throws Exception
     */
    public function validateImmutableQuote()
    {
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
     *
     * @throws Exception
     */
    public function validateEmptyCart()
    {
        $immutableQuote = $this->getImmutableQuote();
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
     *
     * @throws Exception
     */
    public function validateToDateForRule()
    {
        $rule = $this->getRule();
        $date = $rule->getToDate();
        if ($date && date('Y-m-d', strtotime($date)) < date('Y-m-d')) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_EXPIRED,
                sprintf('The coupon code [%s] is expired.', $this->getCouponCode()),
                422
            );
        }
    }

    /**
     * Verifies that the from date is in the past.
     *
     * @throws Exception
     */
    public function validateFromDateForRule()
    {
        $rule = $this->getRule();
        $date = $rule->getFromDate();
        if ($date && date('Y-m-d', strtotime($date)) > date('Y-m-d')) {
            $desc = 'Code available from ' . date('m/d/Y', strtotime($date));
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_NOT_AVAILABLE,
                $desc,
                422
            );
        }
    }

    /**
     * Verifies that the coupon hasn't been used too many times.
     *
     * @throws Exception
     */
    public function validateCouponUsageLimits()
    {
        $coupon = $this->getCoupon();
        if ($coupon->getUsageLimit() && $coupon->getTimesUsed() >= $coupon->getUsageLimit()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', $this->getCouponCode()),
                422
            );
        }
    }

    /**
     * Verifies that the coupon hasn't been used too many times for the current customer.
     *
     * @throws Exception
     */
    public function validateCouponCustomerUsageLimits()
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
                        422
                    );
                }
            }
        }
    }

    /**
     * Verifies that the rule hasn't been used too many times for the current customer.
     *
     * @throws Exception
     */
    public function validateRuleCustomerUsageLimits()
    {
        $rule = $this->getRule();
        $immutableQuote = $this->getImmutableQuote();
        if ($usesPerCustomer = $rule->getUsesPerCustomer()) {
            $customerId = $immutableQuote->getCustomerId();
            $ruleCustomer = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $rule->getId());

            if ($ruleCustomer->getId() && $ruleCustomer->getTimesUsed() >= $usesPerCustomer) {
                $this->setErrorResponseAndThrowException(
                    self::ERR_CODE_LIMIT_REACHED,
                    sprintf('The code [%s] has exceeded usage limit.', $this->getCouponCode()),
                    422
                );
            }
        }
    }

    /**
     * Attempts to apply the coupon to both the immutable and session quote.
     *
     * @throws Exception
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
                422
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
     * @throws Exception  thrown if coupon in quote doesn't match the coupon applied.
     */
    protected function validateAfterApplyingCoupon()
    {
        $immutableQuote = $this->getImmutableQuote();
        if ($immutableQuote->getCouponCode() != $this->getCouponCode()) {
            $this->setErrorResponseAndThrowException(
                self::ERR_SERVICE,
                sprintf("Invalid coupon code response for coupon [%s]", $this->getCouponCode()),
                422
            );
        }
    }

    /**
     * Sets the response error and the http status code and then throws an exception.
     * @param int $errCode
     * @param string $message
     * @param int $httpStatusCode
     * @param Exception $exception
     *
     * @throws Exception
     */
    protected function setErrorResponseAndThrowException($errCode, $message, $httpStatusCode, \Exception $exception = null)
    {
        $this->responseError = array(
            'code'    => $errCode,
            'message' => $message,
        );

        $this->httpCode = $httpStatusCode;
        $this->responseCart = $this->getCartTotals();

        if ($exception){
            throw $exception;
        }

        throw new \Bolt_Boltpay_BadInputException($message);
    }

    /**
     * Gets all cart totals from Bolt buildCart.
     *
     * @return array
     * @throws Varien_Exception
     */
    protected function getCartTotals()
    {
        $quote = $this->getImmutableQuote();
        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');
        $items = @$quote->getAllVisibleItems();

        $cart = $boltHelper->buildCart($quote, $items, 'multi-page');

        return array(
            'total_amount' => $cart['total_amount'],
            'tax_amount'   => $cart['tax_amount'],
            'discounts'    => $cart['discounts'],
        );
    }

    /**
     * Sets the success response (discount code, amount, description, and type). Also sets the HTTP status to 200.
     *
     * @return array
     * @throws Varien_Exception
     */
    protected function setSuccessResponse()
    {
        $this->responseCart = $this->getCartTotals();

        $immutableQuote = $this->getImmutableQuote();
        $address = $immutableQuote->isVirtual() ?
            $immutableQuote->getBillingAddress() :
            $immutableQuote->getShippingAddress();

        $this->discountInfo = array(
            'discount_code'   => $this->getCouponCode(),
            'discount_amount' => abs(round($address->getDiscountAmount() * 100)),
            'description'     => Mage::helper('boltpay')->__('Discount ') . $address->getDiscountDescription(),
            'discount_type'   => $this->convertToBoltDiscountType($this->getRule()->getSimpleAction()),
        );

        $this->httpCode = 200;
    }

    /**
     * Maps the Magento discount type to a Bolt discount type
     *
     * @param $type
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
     * @return Mage_Sales_Model_Quote|null
     * @throws Varien_Exception
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
     * @return Mage_Sales_Model_Quote|null
     * @throws Varien_Exception
     */
    protected function getImmutableQuote()
    {
        if (!$this->immutableQuote) {
            if (!$this->requestObject) {
                return null;
            }

            /** @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore(
                $this->transactionHelper->getImmutableQuoteIdFromTransaction($this->mockTransaction)
            );
            $this->immutableQuote = $immutableQuote;
        }

        return $this->immutableQuote;
    }

    /**
     * Get the coupon based on the coupon code if it exists.
     *
     * @return Mage_SalesRule_Model_Coupon|null
     */
    protected function getCoupon()
    {
        if (!$this->coupon) {
            if (!$this->requestObject) {
                return null;
            }
            /** @var Mage_SalesRule_Model_Coupon $coupon */
            $coupon = Mage::getModel('salesrule/coupon')->load($this->getCouponCode(), 'code');
            $this->coupon = $coupon;
        }

        return $this->coupon;
    }

    /**
     * Gets rule based on the coupon.
     *
     * @return Mage_SalesRule_Model_Rule|null
     */
    protected function getRule()
    {
        if (!$this->rule) {
            if (!$this->requestObject) {
                return null;
            }

            $coupon = $this->getCoupon();
            // Load the coupon discount rule
            /** @var Mage_SalesRule_Model_Rule $rule */
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
     * Gets session quote id from the request.
     *
     * @return int
     */
    public function getParentQuoteId()
    {
        return @$this->requestObject->cart->order_reference;
    }

    /**
     * Get response data for Bolt response
     *
     * @return array
     */
    public function getResponseData()
    {
        if ($this->responseError) {
            return array(
                'status' => 'error',
                'error'  => $this->responseError,
                'cart'   => $this->responseCart
            );
        }

        return array_merge(
            array(
                'status' => 'success',
                'cart'   => $this->responseCart
            ),
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