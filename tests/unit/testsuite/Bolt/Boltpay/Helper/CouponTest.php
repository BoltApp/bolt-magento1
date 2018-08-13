<?php

require_once('TestHelper.php');
require_once('CouponHelper.php');

class Bolt_Boltpay_Helper_CouponTest extends PHPUnit_Framework_TestCase
{
    private $app = null;

    private static $couponExistCode = 'BOLT_EXIST_CODE';

    private static $invalidCouponCode = 'FOO_INVALID_CODE';

    /**
     * @var int|null
     */
    private static $couponCodeId = null;

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var int|null
     */
    private static $ruleId = null;

    /**
     * @var int|null
     */
    private static $invalidRuleId = null;

    /**
     * @var int|null
     */
    private static $quoteId = null;

    /**
     * @var Bolt_Boltpay_Helper_Coupon
     */
    private $currentMock = null;

    /**
     * @var $couponHelper Bolt_Boltpay_CouponHelper
     */
    private $couponHelper = null;

    /**
     * @var int|null
     */
    private static $customerId = null;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->couponHelper = new Bolt_Boltpay_CouponHelper();
        $this->currentMock = new Bolt_Boltpay_Helper_Coupon();
    }


    protected function tearDown()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
    }

    /**
     * Generates data for testing purposes
     */
    public static function setUpBeforeClass()
    {
        self::$ruleId = Bolt_Boltpay_CouponHelper::createDummyRule(self::$couponExistCode);
        self::$invalidRuleId = Bolt_Boltpay_CouponHelper::createDummyRule(
            self::$invalidCouponCode,
            array(
                'from_date'         => (new \DateTime('now +1 day'))->format('Y-m-d'),
                'to_date'           => '2000-01-01',
                'uses_per_customer' => 1
            ),
            array(
                'usage_limit'        => 100,
                'times_used'         => 100,
                'usage_per_customer' => 1
            )
        );
        self::$customerId = Bolt_Boltpay_CouponHelper::createDummyCustomer();
        self::$quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote(
            array('customer_id' => self::$customerId, 'coupon_code', self::$invalidCouponCode)
        );
        self::$couponCodeId = Bolt_Boltpay_CouponHelper::getCouponIdByCode(self::$invalidCouponCode);
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1');
    }

    /**
     * Deletes dummy data
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_CouponHelper::deleteDummyCustomer(self::$customerId);
        Bolt_Boltpay_CouponHelper::deleteDummyRule(self::$ruleId);
        Bolt_Boltpay_CouponHelper::deleteDummyRule(self::$invalidRuleId);
        Bolt_Boltpay_CouponHelper::deleteDummyQuote(self::$quoteId);
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Sets up environment for discount test cases
     *
     * @param array $additionalData
     *
     * @throws Exception
     */
    private function setupEnvironment($additionalData = array())
    {
        $requestObject = $this->couponHelper->setUpRequest($additionalData);
        $this->currentMock->setupVariables($requestObject);
    }

    /**
     * Unit test for validating empty coupon code from request if it is passed
     */
    public function testPassValidateEmptyCoupon()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => 'BOLT_CODE'));
            $this->currentMock->validateEmptyCoupon();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating coupon code from request if it is failed
     */
    public function testFailValidateEmptyCoupon()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => ''));
            $this->currentMock->validateEmptyCoupon();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating coupon exist from request if it is passed
     */
    public function testPassValidateCouponExists()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$couponExistCode));
            $this->currentMock->validateCouponExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating coupon exist from request if it is failed
     */
    public function testFailValidateCouponExists()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => 'BOLT_UN_EXIST_CODE'));
            $this->currentMock->validateCouponExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating rule exists from request if it is passed
     */
    public function testPassValidateRuleExists()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$couponExistCode));
            $this->currentMock->validateRuleExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating rule exists from request if it is failed
     */
    public function testFailValidateRuleExists()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => 'BOLT_UN_EXIST_CODE'));
            $this->currentMock->validateRuleExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating cart identification from request if it is passed
     */
    public function testPassValidateCartIdentificationData()
    {
        $check = true;
        try {
            $this->setupEnvironment();
            $this->currentMock->validateCartIdentificationData();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating cart identification from request if it is failed
     */
    public function testFailValidateCartIdentificationData()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('cart' => ''));
            $this->currentMock->validateCartIdentificationData();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validate order exists if it is passed
     */
    public function testPassValidateOrderExists()
    {
        $check = true;
        try {
            $this->setupEnvironment();
            $this->currentMock->validateOrderExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validate order exists if it is failed
     */
    public function testFailValidateOrderExists()
    {
        $check = true;
        try {
            $incrementId = Bolt_Boltpay_CouponHelper::createDummyOrder(self::$productId);

            $this->setupEnvironment(array('cart' => array('display_id' => "$incrementId|50256")));
            $this->currentMock->validateOrderExists();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
        if (@$incrementId) {
            Bolt_Boltpay_CouponHelper::deleteDummyOrder($incrementId);
        }
    }

    /**
     * Unit test for validating immutable quote exist if it is passed
     */
    public function testPassValidateImmutableQuote()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('cart' => array('display_id' => '100010289|' . self::$quoteId)));
            $this->currentMock->validateImmutableQuote();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating immutable quote exist if it is failed
     */
    public function testFailValidateImmutableQuote()
    {
        $check = true;
        try {
            $this->setupEnvironment();
            $this->currentMock->validateImmutableQuote();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating session quote exist if it is passed
     */
    public function testPassValidateSessionQuote()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
            $this->currentMock->validateSessionQuote();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating session quote exist if it is failed
     */
    public function testFailValidateSessionQuote()
    {
        $check = true;
        try {
            $this->setupEnvironment();
            $this->currentMock->validateSessionQuote();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating empty cart if valid cart exists
     */
    public function testPassValidateEmptyCart()
    {
        $check = true;
        try {
            $testHelper = new Bolt_Boltpay_TestHelper();
            $cart = $testHelper->addProduct(self::$productId, 2);
            $quote = $cart->getQuote();

            $this->setupEnvironment(array('cart' => array('display_id' => '100010289|' . $quote->getId())));
            $this->currentMock->validateEmptyCart();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating empty cart if it is failed
     */
    public function testFailValidateEmptyCart()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('cart' => array('display_id' => '100010289|' . self::$quoteId)));
            $this->currentMock->validateEmptyCart();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating to date for rule if it is passed
     */
    public function testPassValidateToDateForRule()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$couponExistCode));
            $this->currentMock->validateToDateForRule();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating to date for rule if it is failed
     */
    public function testFailValidateToDateForRule()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
            $this->currentMock->validateToDateForRule();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating from date for rule if it is passed
     */
    public function testPassValidateFromDateForRule()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$couponExistCode));
            $this->currentMock->validateFromDateForRule();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating from date for rule if it is failed
     */
    public function testFailValidateFromDateForRule()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
            $this->currentMock->validateFromDateForRule();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating coupon usage limits if it is passed
     */
    public function testPassValidateCouponUsageLimits()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$couponExistCode));
            $this->currentMock->validateCouponUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating coupon usage limits if it is failed
     */
    public function testFailValidateCouponUsageLimits()
    {
        $check = true;
        try {
            $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
            $this->currentMock->validateCouponUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating coupon customer usage limits if it is passed
     */
    public function testPassValidateCouponCustomerUsageLimits()
    {
        $check = true;
        try {
            $this->setupEnvironment(
                array(
                    'discount_code' => self::$couponExistCode,
                    'cart'          => array('display_id' => "100010289|" . self::$quoteId)
                )
            );
            $this->currentMock->validateCouponCustomerUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating coupon customer usage limits if it is failed
     */

    public function testFailValidateCouponCustomerUsageLimits()
    {
        Bolt_Boltpay_CouponHelper::createDummyCouponCustomerUsageLimits(self::$couponCodeId, self::$customerId,2);

        $check = true;
        try {
            $this->setupEnvironment(
                array(
                    'discount_code' => self::$invalidCouponCode,
                    'cart'          => array('display_id' => "100010289|" . self::$quoteId)
                )
            );
            $this->currentMock->validateCouponCustomerUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }

    /**
     * Unit test for validating rule customer usage limits if it is pass
     */
    public function testPassValidateRuleCustomerUsageLimits()
    {
        $check = true;
        try {
            $this->setupEnvironment(
                array(
                    'discount_code' => self::$couponExistCode,
                    'cart'          => array('display_id' => "100010289|" . self::$quoteId)
                )
            );
            $this->currentMock->validateRuleCustomerUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertTrue($check);
    }

    /**
     * Unit test for validating rule customer usage limits if it is failed
     */
    public function testFailValidateRuleCustomerUsageLimits()
    {
        Bolt_Boltpay_CouponHelper::createDummyRuleCustomerUsageLimits(self::$invalidRuleId, self::$customerId, 2);
        $check = true;
        try {
            $this->setupEnvironment(
                array(
                    'discount_code' => self::$invalidCouponCode,
                    'cart'          => array('display_id' => "100010289|" . self::$quoteId)
                )
            );
            $this->currentMock->validateRuleCustomerUsageLimits();
        } catch (\Exception $e) {
            $check = false;
        }

        $this->assertFalse($check);
    }
}
