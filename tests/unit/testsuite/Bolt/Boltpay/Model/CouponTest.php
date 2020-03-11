<?php

require_once('TestHelper.php');
require_once('CouponHelper.php');
require_once('OrderHelper.php');
require_once('MockingTrait.php');

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Coupon
 */
class Bolt_Boltpay_Model_CouponTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int Dummy immutable quote id */
    const IMMUTABLE_QUOTE_ID = 456;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Model_Coupon';

    /** @var string Valid dummy coupon code */
    private static $validCouponCode = 'BOLT_EXIST_CODE';

    /** @var string Invalid dummy coupon code */
    private static $invalidCouponCode = 'BOLT_INVALID_CODE';

    /** @var int|null id of dummy coupon */
    private static $couponId = null;

    /** @var int|null dummy product id */
    private static $productId = null;

    /** @var int|null dummy rule id */
    private static $ruleId = null;

    /** @var int|null invalid dummy rule id */
    private static $invalidRuleId = null;

    /** @var int|null dummy quote id */
    private static $quoteId = null;

    /** @var int|null dummy customer id */
    private static $customerId = null;

    /** @var Bolt_Boltpay_Model_Coupon mocked instance of the tested class*/
    private $currentMock = null;

    /** @var MockObject|Mage_Sales_Model_Quote mocked instance of parent quote */
    private $parentQuoteMock;

    /** @var MockObject|Mage_Sales_Model_Quote mocked instance of immutable quote */
    private $immutableQuoteMock;

    /** @var MockObject|Mage_SalesRule_Model_Rule mocked instance of sales rule */
    private $ruleMock;

    /** @var MockObject|Mage_SalesRule_Model_Coupon mocked instance of coupon rule */
    private $couponMock;

    /** @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of Bolt helper */
    private $boltHelperMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Exception if test class name is not defined
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'setErrorResponseAndThrowException',
                    'getParentQuote',
                    'getImmutableQuote',
                    'getRule',
                    'getCoupon',
                    'boltHelper'
                )
            )
            ->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array(
                    'logWarning',
                    'notifyException',
                    'logException',
                )
            )
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $quoteMockMethods = array('getIsActive', 'isEmpty', 'getCustomerId', 'getItemsCount', 'getId', 'getCouponCode');
        $this->parentQuoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods($quoteMockMethods)->getMock();
        $this->immutableQuoteMock = $this->getClassPrototype('sales/quote')
            ->setMethods($quoteMockMethods)->getMock();
        $this->ruleMock = $this->getClassPrototype('salesrule/rule')
            ->setMethods(
                array(
                    'getUsesPerCustomer',
                    'getId',
                    'getCustomerId',
                    'getFromDate',
                    'getToDate',
                    'isObjectNew',
                    'isEmpty'
                )
            )->getMock();
        $this->couponMock = $this->getClassPrototype('salesrule/rule')
            ->setMethods(
                array(
                    'getUsagePerCustomer',
                    'getId',
                    'getUsageLimit',
                    'getTimesUsed',
                    'isObjectNew',
                    'isEmpty',
                    'getRuleId'
                )
            )->getMock();
        $this->currentMock->method('getParentQuote')->willReturn($this->parentQuoteMock);
        $this->currentMock->method('getImmutableQuote')->willReturn($this->immutableQuoteMock);
        $this->currentMock->method('getRule')->willReturn($this->ruleMock);
        $this->currentMock->method('getCoupon')->willReturn($this->couponMock);
    }


    /**
     * Restore original stubbed methods
     *
     * @throws ReflectionException via _configProxy if Mage doesn't have _config property
     * @throws Mage_Core_Model_Store_Exception if store doesn't  exist
     * @throws Mage_Core_Exception from registry if key already exists
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * Generates data for testing purposes
     */
    public static function setUpBeforeClass()
    {
        try {
            self::$ruleId = Bolt_Boltpay_CouponHelper::createDummyRule(self::$validCouponCode);

            self::$couponId = Bolt_Boltpay_CouponHelper::getCouponIdByCode(self::$invalidCouponCode);
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
            self::$couponId = Bolt_Boltpay_CouponHelper::getCouponIdByCode(self::$invalidCouponCode);

            self::$customerId = Bolt_Boltpay_CouponHelper::createDummyCustomer();
            self::$quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();

            self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'));
        } catch (\Exception $e) {
            self::tearDownAfterClass();
            throw $e;
        }
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
     */
    private function setupEnvironment($additionalData = array())
    {
        $requestObject = Bolt_Boltpay_CouponHelper::setUpRequest($additionalData);
        $this->currentMock->setupVariables($requestObject);
    }

    /**
     * @test
     * that applyCoupon logs a warning and returns false if request object is not set by calling
     * @see Bolt_Boltpay_Model_Coupon::setupVariables
     *
     * @covers ::applyCoupon
     */
    public function applyCoupon_ifRequestObjectIsEmpty_logsWarningAndReturnsFalse()
    {
        $this->currentMock->setupVariables(null);
        $this->boltHelperMock->expects($this->once())->method('logWarning')
            ->with('Need to set setup variables in order to apply coupon');
        $this->assertFalse($this->currentMock->applyCoupon());
    }

    /**
     * @test
     * that applyCoupon applies coupon provided from setup and sets success response
     *
     * @covers ::applyCoupon
     *
     * @throws Mage_Core_Exception if unable to add product to cart
     * @throws Exception if unable to clone quote
     */
    public function applyCoupon_withValidRequest_appliesDiscountCoupon()
    {
        $quote = Mage::getModel('sales/quote')->setIsActive(1)->reserveOrderId()->save();
        Mage::getModel('checkout/cart', array('quote' => $quote))->addProduct(self::$productId, 1);
        $quote->collectTotals()->save();
        $immutableQuote = Mage::getModel('boltpay/boltOrder')->cloneQuote($quote);
        $immutableQuote->collectTotals()->save();
        $couponModel = Mage::getModel('boltpay/coupon');
        $couponModel->setupVariables(
            Bolt_Boltpay_CouponHelper::setUpRequest(
                array(
                    'discount_code' => self::$validCouponCode,
                    'cart'          => array(
                        'order_reference' => $quote->getId(),
                        'display_id'      => $quote->getReservedOrderId() . "|" . $immutableQuote->getId()
                    )
                )
            )
        );
        $this->assertTrue($couponModel->applyCoupon());
        $this->assertEquals(200, $couponModel->getHttpCode());
        $this->assertEquals(
            array(
                'status'          => 'success',
                'cart'            =>
                    array(
                        'total_amount' => 700,
                        'tax_amount'   => NULL,
                        'discounts'    =>
                            array(
                                array(
                                    'amount'      => 300,
                                    'description' => 'Discount (Dummy Percent Rule Frontend Label)',
                                    'type'        => 'fixed_amount',
                                ),
                            ),
                    ),
                'discount_code'   => self::$validCouponCode,
                'discount_amount' => 300,
                'description'     => 'Discount Dummy Percent Rule Frontend Label',
                'discount_type'   => 'percentage',
            ),
            $couponModel->getResponseData()
        );
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quote->getId());
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($immutableQuote->getId());
    }

    /**
     * @test
     * that applyCoupon logs and notifies exception and returns false if an exception is thrown that is not
     * @see Bolt_Boltpay_BadInputException
     *
     * @covers ::applyCoupon
     *
     * @throws Exception if test class name is not defined
     */
    public function applyCoupon_whenExceptionIsThrows_notifiesExceptionAndReturnsFalse()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Coupon $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('validateCoupon', 'boltHelper'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $currentMock->setupVariables(Bolt_Boltpay_CouponHelper::setUpRequest());
        $exception = new Exception('Dummy exception message');
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        $currentMock->expects($this->once())->method('validateCoupon')->willThrowException($exception);
        $this->assertFalse($currentMock->applyCoupon());
    }

    /**
     * @test
     * that setupVariables populates {@see Bolt_Boltpay_Model_Coupon::$requestObject}
     * and {@see Bolt_Boltpay_Model_Coupon::$mockTransaction} internal properties
     *
     * @covers ::setupVariables
     */
    public function setupVariables_always_setsRequestObject()
    {
        $requestObject = Bolt_Boltpay_CouponHelper::setUpRequest();
        $this->currentMock->setupVariables($requestObject);
        $this->assertAttributeEquals(
            $requestObject,
            'requestObject',
            $this->currentMock
        );
        $this->assertAttributeEquals(
            (object)array("order" => $requestObject),
            'mockTransaction',
            $this->currentMock
        );
    }

    /**
     * @test
     * that validateCoupon calls following validation methods
     * @see Bolt_Boltpay_Model_Coupon::validateEmptyCoupon
     * @see Bolt_Boltpay_Model_Coupon::validateCouponExists
     * @see Bolt_Boltpay_Model_Coupon::validateRuleExists
     * @see Bolt_Boltpay_Model_Coupon::validateCartIdentificationData
     * @see Bolt_Boltpay_Model_Coupon::validateOrderExists
     * @see Bolt_Boltpay_Model_Coupon::validateSessionQuote
     * @see Bolt_Boltpay_Model_Coupon::validateImmutableQuote
     * @see Bolt_Boltpay_Model_Coupon::validateEmptyCart
     * @see Bolt_Boltpay_Model_Coupon::validateToDateForRule
     * @see Bolt_Boltpay_Model_Coupon::validateFromDateForRule
     * @see Bolt_Boltpay_Model_Coupon::validateCouponUsageLimits
     * @see Bolt_Boltpay_Model_Coupon::validateCouponCustomerUsageLimits
     * @see Bolt_Boltpay_Model_Coupon::validateRuleCustomerUsageLimits
     *
     * @covers ::validateCoupon
     *
     * @throws Exception if test class name is not defined
     */
    public function validateCoupon_always_callsIndividualValidationMethods()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'validateEmptyCoupon',
                    'validateCouponExists',
                    'validateRuleExists',
                    'validateCartIdentificationData',
                    'validateOrderExists',
                    'validateSessionQuote',
                    'validateImmutableQuote',
                    'validateEmptyCart',
                    'validateToDateForRule',
                    'validateFromDateForRule',
                    'validateCouponUsageLimits',
                    'validateCouponCustomerUsageLimits',
                    'validateRuleCustomerUsageLimits',
                )
            )
            ->getMock();
        $currentMock->expects($this->once())->method('validateEmptyCoupon');
        $currentMock->expects($this->once())->method('validateCouponExists');
        $currentMock->expects($this->once())->method('validateRuleExists');
        $currentMock->expects($this->once())->method('validateCartIdentificationData');
        $currentMock->expects($this->once())->method('validateOrderExists');
        $currentMock->expects($this->once())->method('validateSessionQuote');
        $currentMock->expects($this->once())->method('validateImmutableQuote');
        $currentMock->expects($this->once())->method('validateEmptyCart');
        $currentMock->expects($this->once())->method('validateToDateForRule');
        $currentMock->expects($this->once())->method('validateFromDateForRule');
        $currentMock->expects($this->once())->method('validateCouponUsageLimits');
        $currentMock->expects($this->once())->method('validateCouponCustomerUsageLimits');
        $currentMock->expects($this->once())->method('validateRuleCustomerUsageLimits');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'validateCoupon');
    }

    /**
     * @test
     * that validateEmptyCoupon with non-empty coupon code does not throw exception
     *
     * @covers ::validateEmptyCoupon
     *
     * @throws Exception if validation fails
     */
    public function validateEmptyCoupon_withNonEmptyCouponCode_succeeds()
    {
        $this->setupEnvironment(array('discount_code' => 'BOLT_CODE'));
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateEmptyCoupon();
    }

    /**
     * @test
     * that validateEmptyCoupon with empty coupon code sets error response and throws exception by calling
     * @see Bolt_Boltpay_Model_Coupon::setErrorResponseAndThrowException
     *
     * @covers ::validateEmptyCoupon
     *
     * @throws Exception if validation fails
     */
    public function validateEmptyCoupon_withEmptyCouponCode_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => ''));
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_INVALID,
                'No coupon code provided',
                422
            );
        $this->currentMock->validateEmptyCoupon();
    }

    /**
     * @test
     * that validateCouponExists passes if coupon object is not new nor empty
     * {@see Mage_SalesCoupon_Model_Coupon::isEmpty} returns false and
     * {@see Mage_SalesCoupon_Model_Coupon::isObjectNew} returns true
     *
     * @covers ::validateCouponExists
     *
     * @throws Exception if validation fails
     */
    public function validateCouponExists_ifSalesCouponIsActiveAndNotEmpty_succeeds()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->couponMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->couponMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateCouponExists();
    }

    /**
     * @test
     * that validateCouponExists sets error response and throws exception if coupon object is new
     * ({@see Mage_SalesCoupon_Model_Coupon::isObjectNew} returns false)
     *
     * @covers ::validateCouponExists
     *
     * @throws Exception if validation fails
     */
    public function validateCouponExists_ifSalesCouponObjectIsNew_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->couponMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->couponMock->expects($this->once())->method('isObjectNew')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', self::$validCouponCode),
                404
            );
        $this->currentMock->validateCouponExists();
    }

    /**
     * @test
     * that validateCouponExists sets error response and throws exception if coupon object is empty
     * ({@see Mage_SalesCoupon_Model_Coupon::isEmpty} returns true)
     *
     * @covers ::validateCouponExists
     *
     * @throws Exception if validation fails
     */
    public function validateCouponExists_ifSalesCouponObjectIsEmpty_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->couponMock->expects($this->once())->method('isEmpty')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', self::$validCouponCode),
                404
            );
        $this->currentMock->validateCouponExists();
    }

    /**
     * @test
     * that validateRuleExists passes if rule object is active and not empty
     * {@see Mage_SalesRule_Model_Rule::isEmpty} returns false and
     * {@see Mage_SalesRule_Model_Rule::isObjectNew} returns true
     *
     * @covers ::validateRuleExists
     *
     * @throws Exception if validation fails
     */
    public function validateRuleExists_ifSalesRuleIsActiveAndNotEmpty_succeeds()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->ruleMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->ruleMock->expects($this->once())->method('isObjectNew')->willReturn(false);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateRuleExists();
    }

    /**
     * @test
     * that validateRuleExists sets error response and throws exception if rule is inactive
     * ({@see Mage_SalesRule_Model_Rule::isObjectNew} returns false)
     *
     * @covers ::validateRuleExists
     *
     * @throws Exception if validation fails
     */
    public function validateRuleExists_ifSalesRuleObjectIsNew_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->ruleMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->ruleMock->expects($this->once())->method('isObjectNew')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', self::$validCouponCode),
                404
            );
        $this->currentMock->validateRuleExists();
    }

    /**
     * @test
     * that validateRuleExists sets error response and throws exception if rule object is empty
     * ({@see Mage_SalesRule_Model_Rule::isEmpty} returns true)
     *
     * @covers ::validateRuleExists
     *
     * @throws Exception if validation fails
     */
    public function validateRuleExists_ifSalesRuleObjectIsEmpty_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->ruleMock->expects($this->once())->method('isEmpty')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_INVALID,
                sprintf('The coupon code %s was not found', self::$validCouponCode),
                404
            );
        $this->currentMock->validateRuleExists();
    }

    /**
     * @test
     * that validateCartIdentificationData doesn't set error response or throw exception
     * if all of the cart identification data (parent quote id, increment id and immutable quote id) are present
     *
     * @covers ::validateCartIdentificationData
     *
     * @throws Exception if validation fails
     */
    public function validateCartIdentificationData_withAllCartIdentificationDataPresent_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(
            array(
                'cart' => array(
                    'order_reference' => 455,
                    'display_id'      => '100010289|456'
                )
            )
        );
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateCartIdentificationData();
    }

    /**
     * @test
     * that validateCartIdentificationData sets error response and throws exception
     * if one of cart identification data is missing
     *
     * @covers ::validateCartIdentificationData
     *
     * @dataProvider validateCartIdentificationData_withMissingCartIdentificationDataProvider
     *
     * @param array $requestObject to be provided to {@see Bolt_Boltpay_Model_Coupon::setupVariables}
     *
     * @throws Exception if validation fails
     */
    public function validateCartIdentificationData_withMissingCartIdentificationData_setsErrorResponseAndThrowsException(
        $requestObject
    ) {
        $this->setupEnvironment($requestObject);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                'The order reference is invalid.',
                422
            );
        $this->currentMock->validateCartIdentificationData();
    }

    /**
     * Data provider for
     * @see validateCartIdentificationData_withMissingCartIdentificationData_setsErrorResponseAndThrowsException
     *
     * @return array containing request object with missing cart identification data
     */
    public function validateCartIdentificationData_withMissingCartIdentificationDataProvider()
    {
        return array(
            'Empty parent quote id'    => array(
                'requestObject' => array(
                    'cart' => array(
                        'order_reference' => null,
                        'display_id'      => '100010289|456'
                    )
                )
            ),
            'Empty increment id'       => array(
                'requestObject' => array(
                    'cart' => array(
                        'order_reference' => 455,
                        'display_id'      => '|456'
                    )
                )
            ),
            'Empty immutable quote id' => array(
                'requestObject' => array(
                    'cart' => array(
                        'order_reference' => 455,
                        'display_id'      => '100010289|'
                    )
                )
            ),
        );
    }

    /**
     * @test
     * that validateOrderExists doesn't throw exception when order with specified increment id is not found
     *
     * @covers ::validateOrderExists
     *
     * @throws Exception if validation fails
     */
    public function validateOrderExists_whenOrderWithProvidedIncrementIdDoesNotExist_succeeds()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        $incrementId = $order->getIncrementId();
        Bolt_Boltpay_OrderHelper::deleteDummyOrderByIncrementId($incrementId);
        $this->setupEnvironment(array('cart' => array('display_id' => "$incrementId|50256")));
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateOrderExists();
    }

    /**
     * @test
     * that validateOrderExists sets error response and throws exception order with specified increment id
     * already exists
     *
     * @covers ::validateOrderExists
     *
     * @throws Exception if validation fails
     */
    public function validateOrderExists_ifOrderWithProvidedIncrementIdAlreadyExists_setsErrorResponseAndThrowsException()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId);
        $incrementId = $order->getIncrementId();
        $this->setupEnvironment(array('cart' => array('display_id' => "$incrementId|50256")));
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The order #%s has already been created.', $incrementId),
                422
            );
        $this->currentMock->validateOrderExists();
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     * that validateImmutableQuote doesn't throw exception if immutable quote is not empty
     * ({@see Mage_Sales_Model_Quote::isEmpty} returns false)
     *
     * @covers ::validateImmutableQuote
     *
     * @throws Exception if validation fails
     */
    public function validateImmutableQuote_ifImmutableQuoteObjectIsNotEmpty_succeeds()
    {
        $this->setupEnvironment(array('cart' => array('display_id' => '100010289|' . self::IMMUTABLE_QUOTE_ID)));
        $this->immutableQuoteMock->expects($this->once())->method('isEmpty')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The immutable quote reference [%s] was not found.', self::IMMUTABLE_QUOTE_ID),
                404
            );
        $this->currentMock->validateImmutableQuote();
    }

    /**
     * @test
     * that validateImmutableQuote sets error response and throws exception if immutable quote object is empty
     * ({@see Mage_Sales_Model_Quote::isEmpty} returns true)
     *
     * @covers ::validateImmutableQuote
     *
     * @throws Exception if validation fails
     */
    public function validateImmutableQuote_ifImmutableQuoteObjectIsEmpty_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('cart' => array('display_id' => '100010289|' . self::IMMUTABLE_QUOTE_ID)));
        $this->immutableQuoteMock->expects($this->once())->method('isEmpty')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The immutable quote reference [%s] was not found.', self::IMMUTABLE_QUOTE_ID),
                404
            );
        $this->currentMock->validateImmutableQuote();
    }

    /**
     * @test
     * that validateSessionQuote passes if parent quote object is active and not empty
     * ({@see Mage_Sales_Model_Quote::isEmpty} returns false and {@see Mage_Sales_Model_Quote::getIsActive} returns true)
     *
     * @covers ::validateSessionQuote
     *
     * @throws Exception if validation fails
     */
    public function validateSessionQuote_ifParentQuoteIsActiveAndNotEmpty_succeeds()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->parentQuoteMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(true);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateSessionQuote();
    }

    /**
     * @test
     * that validateSessionQuote sets error response and throws exception if parent quote is inactive
     * ({@see Mage_Sales_Model_Quote::getIsActive} returns false)
     *
     * @covers ::validateSessionQuote
     *
     * @throws Exception if validation fails
     */
    public function validateSessionQuote_ifParentQuoteObjectIsNotActive_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->parentQuoteMock->expects($this->once())->method('isEmpty')->willReturn(false);
        $this->parentQuoteMock->expects($this->once())->method('getIsActive')->willReturn(false);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The session quote reference [%s] was not found.', self::$quoteId),
                404
            );
        $this->currentMock->validateSessionQuote();
    }

    /**
     * @test
     * that validateSessionQuote sets error response and throws exception if parent quote object is empty
     * ({@see Mage_Sales_Model_Quote::isEmpty} returns true)
     *
     * @covers ::validateSessionQuote
     *
     * @throws Exception if validation fails
     */
    public function validateSessionQuote_ifParentQuoteObjectIsEmpty_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->parentQuoteMock->expects($this->once())->method('isEmpty')->willReturn(true);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The session quote reference [%s] was not found.', self::$quoteId),
                404
            );
        $this->currentMock->validateSessionQuote();
    }

    /**
     * @test
     * that validateEmptyCart doesn't throw exception if quote item count is more than 0
     *
     * @covers ::validateEmptyCart
     *
     * @throws Exception if validation fails
     */
    public function validateEmptyCart_withNonEmptyCart_succeeds()
    {
        $this->immutableQuoteMock->expects($this->once())->method('getItemsCount')->willReturn(1);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateEmptyCart();
    }

    /**
     * @test
     * that validateEmptyCart throws exception if quote is empty
     *
     * @covers ::validateEmptyCart
     *
     * @throws Exception if validation fails
     */
    public function validateEmptyCart_withEmptyQuoteItems_setsErrorResponseAndThrowsException()
    {
        $this->immutableQuoteMock->expects($this->once())->method('getItemsCount')->willReturn(0);
        $this->immutableQuoteMock->expects($this->once())->method('getId')->willReturn(self::$quoteId);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_INSUFFICIENT_INFORMATION,
                sprintf('The quote for order reference [%s] is empty.', self::$quoteId),
                422
            );
        $this->currentMock->validateEmptyCart();
    }

    /**
     * @test
     * that validateToDateForRule doesn't throw exception if rule is not expired
     *
     * @covers ::validateToDateForRule
     *
     * @throws Exception if validation fails
     */
    public function validateToDateForRule_ifRuleIsNotExpired_succeeds()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->ruleMock->expects($this->once())->method('getToDate')
            ->willReturn((new \DateTime('now +1 day'))->format('Y-m-d'));
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateToDateForRule();
    }

    /**
     * @test
     * that validateToDateForRule throws exception if rule is expired
     *
     * @covers ::validateToDateForRule
     *
     * @throws Exception if validation fails
     */
    public function validateToDateForRule_ifRuleIsExpired_setsErrorResponseAndThrowException()
    {
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->ruleMock->expects($this->once())->method('getToDate')
            ->willReturn((new \DateTime('now -1 day'))->format('Y-m-d'));
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_EXPIRED,
                sprintf('The coupon code [%s] is expired.', self::$invalidCouponCode),
                422
            );
        $this->currentMock->validateToDateForRule();
    }

    /**
     * @test
     * that validateFromDateForRule doesn't throw exception if rule from date is in the past
     *
     * @covers ::validateFromDateForRule
     *
     * @throws Exception if validation fails
     */
    public function validateFromDateForRule_whenFromDateIsInPast_succeeds()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->ruleMock->expects($this->once())->method('getFromDate')
            ->willReturn((new \DateTime('now -1 day'))->format('Y-m-d'));
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateFromDateForRule();
    }

    /**
     * @test
     * that validateFromDateForRule throws exception if rule from date is in the future
     *
     * @covers ::validateFromDateForRule
     *
     * @throws Exception if validation fails
     */
    public function validateFromDateForRule_whenFromDateIsInFuture_setsErrorResponseAndThrowException()
    {
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->ruleMock->expects($this->once())->method('getFromDate')
            ->willReturn((new \DateTime('now +1 day'))->format('Y-m-d'));
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_NOT_AVAILABLE,
                $this->stringStartsWith('Code available from '),
                422
            );
        $this->currentMock->validateFromDateForRule();
    }

    /**
     * @test
     * that validateCouponUsageLimits doesn't throw exception if coupon doesn't have usage limit
     *
     * @covers ::validateCouponUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateCouponUsageLimits_whenCouponDoesNotHaveUsageLimit_succeeds()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->couponMock->expects($this->once())->method('getUsageLimit')->willReturn(0); //0 means unlimited usage
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateCouponUsageLimits();
    }

    /**
     * @test
     * that validateCouponUsageLimits throws exception if coupon times used is equal or greater than usage limit
     *
     * @covers ::validateCouponUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateCouponUsageLimits_couponUsageEqualToUsageLimit_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->couponMock->expects($this->exactly(2))->method('getUsageLimit')->willReturn(1);
        $this->couponMock->expects($this->once())->method('getTimesUsed')->willReturn(1);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', self::$invalidCouponCode),
                422
            );
        $this->currentMock->validateCouponUsageLimits();
    }

    /**
     * @test
     * that validateRuleCustomerUsageLimits doesn't throw exception if coupon doesn't have usage per customer
     *
     * @covers ::validateCouponCustomerUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateCouponCustomerUsageLimits_whenCouponDoesNotHaveUsageLimitPerCustomer_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->immutableQuoteMock->expects($this->once())->method('getCustomerId')->willReturn(self::$customerId);
        $this->couponMock->expects($this->once())->method('getUsagePerCustomer')->willReturn(0);
        $this->currentMock->validateCouponCustomerUsageLimits();
    }

    /**
     * @test
     * that validateRuleCustomerUsageLimits throws exception if discount coupon is used up by quote customer
     *
     * @covers ::validateCouponCustomerUsageLimits
     *
     * @throws Varien_Exception if unable to create dummy coupon customer usage limit
     * @throws Exception if validation fails
     */
    public function validateCouponCustomerUsageLimits_whenCouponIsUsedUpByCustomer_setsErrorResponseAndThrowsException()
    {
        Bolt_Boltpay_CouponHelper::createDummyCouponCustomerUsageLimits(
            self::$couponId,
            self::$customerId,
            2
        );
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->immutableQuoteMock->expects($this->once())->method('getCustomerId')->willReturn(self::$customerId);
        $this->couponMock->expects($this->exactly(2))->method('getUsagePerCustomer')->willReturn(1);
        $this->couponMock->expects($this->once())->method('getId')->willReturn(self::$couponId);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', self::$invalidCouponCode),
                422
            );
        $this->currentMock->validateCouponCustomerUsageLimits();
    }

    /**
     * @test
     * that validateRuleCustomerUsageLimits doesn't throw exception if discount rule usage is not limited per customer
     *
     * @covers ::validateRuleCustomerUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateRuleCustomerUsageLimits_whenRuleUsageIsNotLimitedByCustomer_succeeds()
    {
        $this->ruleMock->expects($this->once())->method('getUsesPerCustomer')->willReturn(0); //0 means unlimited usage
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateRuleCustomerUsageLimits();
    }

    /**
     * @test
     * that validateRuleCustomerUsageLimits throws exception if discount rule is used less than
     * the maximum number of times for current customer
     *
     * @covers ::validateRuleCustomerUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateRuleCustomerUsageLimits_whenRuleIsNotUsedUpByCustomer_succeeds()
    {
        Bolt_Boltpay_CouponHelper::createDummyRuleCustomerUsageLimits(
            self::$invalidRuleId,
            self::$customerId,
            1
        );
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->ruleMock->expects($this->once())->method('getUsesPerCustomer')->willReturn(10);
        $this->ruleMock->expects($this->once())->method('getId')->willReturn(self::$invalidRuleId);
        $this->immutableQuoteMock->expects($this->once())->method('getCustomerId')->willReturn(self::$customerId);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        $this->currentMock->validateRuleCustomerUsageLimits();
    }

    /**
     * @test
     * that validateRuleCustomerUsageLimits throws exception if discount rule is used up by quote customer
     *
     * @covers ::validateRuleCustomerUsageLimits
     *
     * @throws Exception if validation fails
     */
    public function validateRuleCustomerUsageLimits_whenRuleIsUsedUpByCustomer_setsErrorResponseAndThrowsException()
    {
        Bolt_Boltpay_CouponHelper::createDummyRuleCustomerUsageLimits(
            self::$invalidRuleId,
            self::$customerId,
            2
        );
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->ruleMock->expects($this->once())->method('getUsesPerCustomer')->willReturn(1);
        $this->ruleMock->expects($this->once())->method('getId')->willReturn(self::$invalidRuleId);
        $this->immutableQuoteMock->expects($this->once())->method('getCustomerId')->willReturn(self::$customerId);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')
            ->with(
                Bolt_Boltpay_Model_Coupon::ERR_CODE_LIMIT_REACHED,
                sprintf('The code [%s] has exceeded usage limit.', self::$invalidCouponCode),
                422
            );
        $this->currentMock->validateRuleCustomerUsageLimits();
    }

    /**
     * @test
     * that applyCouponToQuotes calls {@see Bolt_Boltpay_Model_Coupon::applyCouponToQuote} to apply coupon to
     * first to parent and then to immutable quote
     *
     * @covers ::applyCouponToQuotes
     *
     * @throws Exception if test class name is not defined
     */
    public function applyCouponToQuotes_always_appliesCouponToParentAndImmutableQuotes()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(
                array(
                    'getParentQuote',
                    'getImmutableQuote',
                    'getCouponCode',
                    'applyCouponToQuote',
                    'setErrorResponseAndThrowException'
                )
            )
            ->getMock();
        $currentMock->expects($this->once())->method('getCouponCode')->willReturn(self::$validCouponCode);
        $currentMock->expects($this->once())->method('getParentQuote')->willReturn($this->parentQuoteMock);
        $currentMock->expects($this->once())->method('getImmutableQuote')->willReturn($this->immutableQuoteMock);
        $currentMock->expects($this->exactly(2))->method('applyCouponToQuote')->withConsecutive(
            array($this->parentQuoteMock, self::$validCouponCode),
            array($this->immutableQuoteMock, self::$validCouponCode)
        );
        $currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'applyCouponToQuotes');
    }

    /**
     * @test
     * that applyCouponToQuotes catches exceptions and calls
     * @see Bolt_Boltpay_Model_Coupon::setErrorResponseAndThrowException
     *
     * @covers ::applyCouponToQuotes
     *
     * @throws Exception if test class name is not defined
     */
    public function applyCouponToQuotes_ifAnExceptionOccurs_setsErrorResponseAndThrowsException()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getParentQuote', 'setErrorResponseAndThrowException'))
            ->getMock();
        $exception = new Exception('Dummy exception message');
        $currentMock->expects($this->once())->method('getParentQuote')->willThrowException($exception);
        $currentMock->expects($this->once())->method('setErrorResponseAndThrowException')->with(
            Bolt_Boltpay_Model_Coupon::ERR_SERVICE,
            $exception->getMessage(),
            422
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'applyCouponToQuotes');
    }

    /**
     * @test
     * that applyCouponToQuote properly applies provided coupon code to the provided quote
     *
     * @covers ::applyCouponToQuote
     *
     * @throws ReflectionException if applyCouponToQuote method doesn't exist
     * @throws Mage_Core_Exception if unable to add product to cart
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy quote
     */
    public function applyCouponToQuote_always_appliesCouponToQuote()
    {
        $quote = Mage::getModel('sales/quote');
        $cart = Mage::getModel('checkout/cart', array('quote' => $quote));
        $cart->addProduct(self::$productId, 1);
        $this->boltHelperMock->collectTotals($quote, true);
        $grandTotalBeforeDiscount = $quote->getGrandTotal();
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'applyCouponToQuote',
            array(
                $quote,
                self::$validCouponCode
            )
        );
        $grandTotalAfterDiscount = $quote->getGrandTotal();
        $this->assertLessThan($grandTotalBeforeDiscount, $grandTotalAfterDiscount);
        $this->assertEquals(self::$validCouponCode, $quote->getCouponCode());
        $this->stringContains(self::$ruleId, $quote->getAppliedRuleIds());
        $this->assertGreaterThan(0, abs($quote->getTotals()['discount']->getValue()));
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quote->getId());
    }

    /**
     * @test
     * that validateAfterApplyingCoupon sets error response and throws exception
     * if coupon code applied to immutable quote doesn't match the one in request
     *
     * @covers ::validateAfterApplyingCoupon
     *
     * @throws ReflectionException if validateAfterApplyingCoupon method is not defined
     */
    public function validateAfterApplyingCoupon_withDifferentCouponCodes_setsErrorResponseAndThrowsException()
    {
        $this->setupEnvironment(array('discount_code' => self::$invalidCouponCode));
        $this->immutableQuoteMock->expects($this->once())->method('getCouponCode')
            ->willReturn(self::$validCouponCode);
        $this->currentMock->expects($this->once())->method('setErrorResponseAndThrowException')->with(
            Bolt_Boltpay_Model_Coupon::ERR_SERVICE,
            sprintf("Invalid coupon code response for coupon [%s]", self::$invalidCouponCode),
            422
        );
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'validateAfterApplyingCoupon'
        );
    }

    /**
     * @test
     * that validateAfterApplyingCoupon succeeds if coupon code applied to immutable quote matches the one in request
     *
     * @covers ::validateAfterApplyingCoupon
     *
     * @throws ReflectionException if validateAfterApplyingCoupon method is not defined
     */
    public function validateAfterApplyingCoupon_withSameCouponCodes_succeeds()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->immutableQuoteMock->expects($this->once())->method('getCouponCode')
            ->willReturn(self::$validCouponCode);
        $this->currentMock->expects($this->never())->method('setErrorResponseAndThrowException');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'validateAfterApplyingCoupon');
    }

    /**
     * @test
     * that setErrorResponseAndThrowException sets provided error code, message and https status code to properties
     * and throws {@see Bolt_Boltpay_BadInputException} containing provided message
     *
     * @covers ::setErrorResponseAndThrowException
     *
     * @expectedException Bolt_Boltpay_BadInputException
     * @expectedExceptionMessage Dummy exception message
     *
     * @throws Bolt_Boltpay_BadInputException from tested method
     * @throws ReflectionException if setErrorResponseAndThrowException method is undefined
     * @throws Exception if test class name is not defined
     */
    public function setErrorResponseAndThrowException_withoutException_setsPropertiesAndThrowsBadInputException()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Coupon $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper', 'getCartTotals'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $dummyCartTotals = array(
            'total_amount' => 23456,
            'tax_amount'   => 0,
            'discounts'    => 0,
        );
        $currentMock->method('getCartTotals')->willReturn($dummyCartTotals);
        $errorMessage = 'Dummy exception message';
        $httpStatusCode = 500;
        $errorCode = Bolt_Boltpay_Model_Coupon::ERR_SERVICE;
        try {
            $this->boltHelperMock->expects($this->once())->method('logWarning')->with($errorMessage);
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $currentMock,
                'setErrorResponseAndThrowException',
                array(
                    $errorCode,
                    $errorMessage,
                    $httpStatusCode
                )
            );
        } catch (Bolt_Boltpay_BadInputException $e) {
            $this->assertAttributeEquals(
                array('code' => $errorCode, 'message' => $errorMessage),
                'responseError',
                $currentMock
            );
            $this->assertAttributeEquals($httpStatusCode, 'httpCode', $currentMock);
            $this->assertAttributeEquals($dummyCartTotals, 'responseCart', $currentMock);
            throw $e;
        }
    }

    /**
     * @test
     * that setErrorResponseAndThrowException sets provided error code, message and https status code to properties
     * and throws provided exception
     *
     * @covers ::setErrorResponseAndThrowException
     *
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage Dummy exception message
     *
     * @throws Bolt_Boltpay_BadInputException from tested method
     * @throws ReflectionException if setErrorResponseAndThrowException method is undefined
     * @throws Exception if test class name is not defined
     */
    public function setErrorResponseAndThrowException_withException_throwsBadInputException()
    {
        /** @var MockObject|Bolt_Boltpay_Model_Coupon $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper', 'getCartTotals'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $dummyCartTotals = array(
            'total_amount' => 23456,
            'tax_amount'   => 0,
            'discounts'    => 0,
        );
        $currentMock->method('getCartTotals')->willReturn($dummyCartTotals);
        $errorMessage = 'Dummy exception message';
        $httpStatusCode = 500;
        $errorCode = Bolt_Boltpay_Model_Coupon::ERR_SERVICE;
        $exception = new Mage_Core_Exception($errorMessage);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        try {
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $currentMock,
                'setErrorResponseAndThrowException',
                array(
                    $errorCode,
                    $errorMessage,
                    $httpStatusCode,
                    $exception
                )
            );
        } catch (Exception $e) {
            $this->assertAttributeEquals(
                array('code' => $errorCode, 'message' => $errorMessage),
                'responseError',
                $currentMock
            );
            $this->assertAttributeEquals($httpStatusCode, 'httpCode', $currentMock);
            $this->assertAttributeEquals($dummyCartTotals, 'responseCart', $currentMock);
            throw $e;
        }
    }

    /**
     * @test
     * that getCartTotals returns select totals data from cart data build using {@see Bolt_Boltpay_Model_BoltOrder::buildCart}
     * for immutable quote
     *
     * @covers ::getCartTotals
     *
     * @throws ReflectionException if unable to stub model or getCartTotals method is undefined
     */
    public function getCartTotals_always_returnImmutableQuoteTotalsFromBoltOrder()
    {
        $boltOrderMock = $this->getClassPrototype('boltpay/boltOrder')->setMethods(array('buildCart'))->getMock();
        $cartData = array(
            'total_amount' => 2345,
            'tax_amount'   => 123,
            'discounts'    => 321,
        );
        $boltOrderMock->expects($this->once())->method('buildCart')->with($this->immutableQuoteMock, true)
            ->willReturn(
                $cartData
            );
        Bolt_Boltpay_TestHelper::stubModel('boltpay/boltOrder', $boltOrderMock);
        $this->assertEquals(
            $cartData,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getCartTotals'
            )
        );
    }

    /**
     * @test
     * that setSuccessResponse populates response properties
     *
     * @covers ::setSuccessResponse
     *
     * @throws ReflectionException if setSuccessResponse class is not defined
     * @throws Exception if test class name is not defined
     */
    public function setSuccessResponse_always_setsSuccessResponse()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getCartTotals', 'getImmutableQuote', 'getCouponCode', 'boltHelper', 'getRule'))
            ->getMock();
        $dummyCartTotals = array(
            'total_amount' => 23456,
            'tax_amount'   => 0,
            'discounts'    => 0,
        );
        $currentMock->expects($this->once())->method('getCartTotals')->willReturn($dummyCartTotals);
        $currentMock->expects($this->once())->method('getImmutableQuote')->willReturn($this->immutableQuoteMock);
        $currentMock->expects($this->once())->method('getCouponCode')->willReturn(self::$validCouponCode);
        $currentMock->expects($this->once())->method('boltHelper')->willReturn($this->boltHelperMock);
        $currentMock->expects($this->once())->method('getRule')->willReturn($this->ruleMock);
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $currentMock,
            'setSuccessResponse'
        );
        $this->assertAttributeEquals($dummyCartTotals, 'responseCart', $currentMock);
        $this->assertAttributeEquals(200, 'httpCode', $currentMock);
        $this->assertAttributeEquals(
            array(
                'discount_code'   => 'BOLT_EXIST_CODE',
                'discount_amount' => 0.0,
                'description'     => 'Discount ',
                'discount_type'   => '',
            ),
            'discountInfo',
            $currentMock
        );
    }

    /**
     * @test
     * that convertToBoltDiscountTypeDataProvider returns expected Bolt discount type
     * when provided with Magento sales rule action type
     *
     * @dataProvider convertToBoltDiscountTypeDataProvider
     *
     * @covers ::convertToBoltDiscountType
     *
     * @param string $ruleActionType Magento sales rule action type
     * @param string $boltDiscountType expected Bolt discount type matching the Magento sales rule action type
     *
     * @throws ReflectionException if convertToBoltDiscountType is not defined
     */
    public function convertToBoltDiscountType_withVariousActionTypes_returnsBoltDiscountType(
        $ruleActionType,
        $boltDiscountType
    ) {
        $this->assertEquals(
            $boltDiscountType,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'convertToBoltDiscountType',
                array(
                    $ruleActionType
                )
            )
        );
    }

    /**
     * Data provider for @see {convertToBoltDiscountType_withVariousActionTypes_returnsBoltDiscountType}
     *
     * @return array containing Magento rule action type and appropriate Bolt discount type
     */
    public function convertToBoltDiscountTypeDataProvider()
    {
        return array(
            array('ruleActionType' => 'by_fixed', 'boltDiscountType' => 'fixed_amount'),
            array('ruleActionType' => 'cart_fixed', 'boltDiscountType' => 'fixed_amount'),
            array('ruleActionType' => 'by_percent', 'boltDiscountType' => 'percentage'),
            array('ruleActionType' => 'by_shipping', 'boltDiscountType' => 'shipping'),
            array('ruleActionType' => 'buy_x_get_y', 'boltDiscountType' => ''),
            array('ruleActionType' => 'to_fixed', 'boltDiscountType' => ''),
            array('ruleActionType' => 'to_percent', 'boltDiscountType' => ''),
        );
    }

    /**
     * @test
     * that getParentQuote returns null if both {@see Bolt_Boltpay_Model_Coupon::$parentQuote}
     * and {@see Bolt_Boltpay_Model_Coupon::$requestObject} are not set
     *
     * @covers ::getParentQuote
     *
     * @throws ReflectionException if getParentQuote method is undefined
     */
    public function getParentQuote_withoutRequestAndParentQuoteProperties_returnsNull()
    {
        $this->currentMock->setupVariables(null);
        $this->assertNull(Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getParentQuote'));
    }

    /**
     * @test
     * that getParentQuote loads quote by parent quote id if {@see Bolt_Boltpay_Model_Coupon::$parentQuote} is not set
     *
     * @covers ::getParentQuote
     *
     * @throws ReflectionException if getParentQuote method is undefined
     */
    public function getParentQuote_withEmptyParentQuote_loadsParentQuoteById()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        /** @var Mage_Sales_Model_Quote $parentQuote */
        $parentQuote = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getParentQuote');
        $this->assertAttributeEquals(
            $parentQuote,
            'parentQuote',
            $this->currentMock
        );
        $this->assertEquals(self::$quoteId, $parentQuote->getId());
    }

    /**
     * @test
     * that getParentQuote returns parent quote from {@see Bolt_Boltpay_Model_Coupon::$parentQuote} if set
     *
     * @covers ::getParentQuote
     *
     * @throws ReflectionException if getParentQuote method is undefined
     */
    public function getParentQuote_withParentQuoteProperty_returnsQuoteFromProperty()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'parentQuote',
            $this->parentQuoteMock
        );
        $this->assertEquals(
            $this->parentQuoteMock,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getParentQuote')
        );
    }

    /**
     * @test
     * that getImmutableQuote returns null if both {@see Bolt_Boltpay_Model_Coupon::$immutableQuote}
     * and {@see Bolt_Boltpay_Model_Coupon::$requestObject} are not set
     *
     * @covers ::getImmutableQuote
     *
     * @throws ReflectionException if getImmutableQuote method is undefined
     */
    public function getImmutableQuote_withoutRequestAndImmutableQuoteProperties_returnsNull()
    {
        $this->currentMock->setupVariables(null);
        $this->assertNull(Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getImmutableQuote'));
    }

    /**
     * @test
     * that getImmutableQuote loads quote by immutable quote id if {@see Bolt_Boltpay_Model_Coupon::$immutableQuote} is not set
     *
     * @covers ::getImmutableQuote
     *
     * @throws ReflectionException if getImmutableQuote method is undefined
     */
    public function getImmutableQuote_withEmptyImmutableQuote_loadsImmutableQuoteById()
    {
        $this->setupEnvironment(array('cart' => array('display_id' => '|' . self::$quoteId)));
        /** @var Mage_Sales_Model_Quote $immutableQuote */
        $immutableQuote = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getImmutableQuote');
        $this->assertAttributeEquals(
            $immutableQuote,
            'immutableQuote',
            $this->currentMock
        );
        $this->assertEquals(self::$quoteId, $immutableQuote->getId());
    }

    /**
     * @test
     * that getImmutableQuote returns immutable quote from {@see Bolt_Boltpay_Model_Coupon::$immutableQuote} if set
     *
     * @covers ::getImmutableQuote
     *
     * @throws ReflectionException if getImmutableQuote method is undefined
     */
    public function getImmutableQuote_withImmutableQuoteProperty_returnsQuoteFromProperty()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'immutableQuote',
            $this->immutableQuoteMock
        );
        $this->assertEquals(
            $this->immutableQuoteMock,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getImmutableQuote')
        );
    }

    /**
     * @test
     * that getCoupon returns null if both {@see Bolt_Boltpay_Model_Coupon::$coupon}
     * and {@see Bolt_Boltpay_Model_Coupon::$requestObject} are not set
     *
     * @covers ::getCoupon
     *
     * @throws ReflectionException if getCoupon method is undefined
     */
    public function getCoupon_withoutRequestAndCouponProperties_returnsNull()
    {
        $this->currentMock->setupVariables(null);
        $this->assertNull(Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getCoupon'));
    }

    /**
     * @test
     * that getCoupon loads quote by coupon id if {@see Bolt_Boltpay_Model_Coupon::$coupon} is not set
     *
     * @covers ::getCoupon
     *
     * @throws ReflectionException if getCoupon method is undefined
     */
    public function getCoupon_withEmptyCoupon_loadsCouponById()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        /** @var Mage_SalesRule_Model_Coupon $coupon */
        $coupon = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getCoupon');
        $this->assertAttributeEquals(
            $coupon,
            'coupon',
            $this->currentMock
        );
        $this->assertEquals(self::$validCouponCode, $coupon->getCode());
    }

    /**
     * @test
     * that getCoupon returns coupon from {@see Bolt_Boltpay_Model_Coupon::$coupon} if set
     *
     * @covers ::getCoupon
     *
     * @throws ReflectionException if getCoupon method is undefined
     */
    public function getCoupon_withCouponProperty_returnsQuoteFromProperty()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'coupon',
            $this->couponMock
        );
        $this->assertEquals(
            $this->couponMock,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getCoupon')
        );
    }

    /**
     * @test
     * that getRule returns null if both {@see Bolt_Boltpay_Model_Rule::$rule}
     * and {@see Bolt_Boltpay_Model_Rule::$requestObject} are not set
     *
     * @covers ::getRule
     *
     * @throws ReflectionException if getRule method is undefined
     */
    public function getRule_withoutRequestAndRuleProperties_returnsNull()
    {
        $this->currentMock->setupVariables(null);
        $this->assertNull(Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getRule'));
    }

    /**
     * @test
     * that getRule loads quote by rule id if {@see Bolt_Boltpay_Model_Rule::$rule} is not set
     *
     * @covers ::getRule
     *
     * @throws ReflectionException if getRule method is undefined
     */
    public function getRule_withEmptyRule_loadsRuleById()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->couponMock->expects($this->once())->method('getRuleId')->willReturn(self::$ruleId);
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getRule');
        $this->assertAttributeEquals(
            $rule,
            'rule',
            $this->currentMock
        );
        $this->assertEquals(self::$ruleId, $rule->getId());
    }

    /**
     * @test
     * that getRule returns rule from {@see Bolt_Boltpay_Model_Rule::$rule} if set
     *
     * @covers ::getRule
     *
     * @throws ReflectionException if getRule method is undefined
     */
    public function getRule_withRuleProperty_returnsQuoteFromProperty()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'rule',
            $this->ruleMock
        );
        $this->assertEquals(
            $this->ruleMock,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getRule')
        );
    }

    /**
     * @test
     * that getCouponCode returns coupon code from request object
     *
     * @covers ::getCouponCode
     *
     * @throws ReflectionException if getCouponCode method is not defined
     */
    public function getCouponCode_always_returnsDiscountCodeFromRequestObject()
    {
        $this->setupEnvironment(array('discount_code' => self::$validCouponCode));
        $this->assertEquals(
            self::$validCouponCode,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getCouponCode')
        );
    }

    /**
     * @test
     * that getParentQuoteId returns parent quote id from request object
     *
     * @covers ::getParentQuoteId
     *
     * @throws ReflectionException if getParentQuoteId method is not defined
     */
    public function getParentQuoteId_always_returnsParentQuoteIdFromRequestObject()
    {
        $this->setupEnvironment(array('cart' => array('order_reference' => self::$quoteId)));
        $this->assertEquals(
            self::$quoteId,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getParentQuoteId')
        );
    }

    /**
     * @test
     * that getResponseData returns error response if set
     *
     * @covers ::getResponseData
     *
     * @throws ReflectionException if responseError or responseCart properties do not exist
     */
    public function getResponseData_ifResponseErrorIsSet_returnsErrorResponse()
    {
        $responseError = array(
            'code'    => Bolt_Boltpay_Model_Coupon::ERR_SERVICE,
            'message' => 'Dummy error message'
        );
        $responseCart = array(
            'total_amount' => 2345,
            'tax_amount'   => 123,
            'discounts'    => 321,
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'responseError',
            $responseError
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'responseCart',
            $responseCart
        );
        $this->assertEquals(
            array(
                'status' => 'error',
                'error'  => $responseError,
                'cart'   => $responseCart
            ),
            $this->currentMock->getResponseData()
        );
    }

    /**
     * @test
     * that getResponseData returns success response containing cart and discount data
     *
     * @covers ::getResponseData
     *
     * @throws ReflectionException if responseError or responseCart properties do not exist
     */
    public function getResponseData_ifResponseErrorIsNotSet_returnsCartAndDiscountResponse()
    {
        $discountInfo = array(
            'discount_code'   => self::$validCouponCode,
            'discount_amount' => 30,
            'description'     => 'Dummy Percent Rule',
            'discount_type'   => 'fixed_amount',
        );
        $responseCart = array(
            'total_amount' => 2345,
            'tax_amount'   => 123,
            'discounts'    => 321,
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'discountInfo',
            $discountInfo
        );
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'responseCart',
            $responseCart
        );
        $this->assertEquals(
            array_merge(
                array(
                    'status' => 'success',
                    'cart'   => $responseCart
                ),
                $discountInfo
            ),
            $this->currentMock->getResponseData()
        );
    }

    /**
     * @test
     * that getHttpCode returns HTTP code from {@see Bolt_Boltpay_Model_Coupon::$httpCode}
     *
     * @covers ::getHttpCode
     *
     * @throws ReflectionException if httpCode property doesn't exist
     */
    public function getHttpCode_always_returnsHttpCode()
    {
        $httpCode = 500;
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, 'httpCode', $httpCode);
        $this->assertEquals($httpCode, $this->currentMock->getHttpCode());
    }

    /**
     * @test
     * that decreaseCouponTimesUsed decreases coupon object times used and saves it
     *
     * @covers ::decreaseCouponTimesUsed
     *
     * @throws Exception if unable to save coupon
     */
    public function decreaseCouponTimesUsed_always_decreasesCouponTimesUsed()
    {
        $timesUsed = 123;
        Mage::getModel('salesrule/coupon')->load(self::$validCouponCode, 'code')
            ->setTimesUsed($timesUsed)->save();
        $coupon = $this->currentMock->decreaseCouponTimesUsed(self::$validCouponCode);
        $this->assertEquals($timesUsed - 1, $coupon->getTimesUsed());
        $this->assertFalse($coupon->hasDataChanges());
    }

    /**
     * @test
     * that decreaseCustomerCouponTimesUsed updates coupon usage database entry by decreasing times used by 1
     *
     * @covers ::decreaseCustomerCouponTimesUsed
     *
     * @throws Varien_Exception if unable to create dummy customer usage limits
     */
    public function decreaseCustomerCouponTimesUsed_whenTimesUsedMoreThanOnce_decreasesCustomerTimeUsed()
    {
        $timesUsed = 123;
        Bolt_Boltpay_CouponHelper::createDummyCouponCustomerUsageLimits(
            self::$couponId,
            self::$customerId,
            $timesUsed
        );
        /** @var Mage_SalesRule_Model_Coupon $coupon */
        $coupon = Mage::getModel('salesrule/coupon')->load(self::$couponId);
        $this->currentMock->decreaseCustomerCouponTimesUsed(self::$customerId, $coupon);
        $couponUsage = new Varien_Object();
        Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon(
            $couponUsage,
            self::$customerId,
            self::$couponId
        );
        $this->assertEquals($timesUsed - 1, $couponUsage->getTimesUsed());
    }

    /**
     * @test
     * that decreaseCustomerCouponTimesUsed deletes customer coupon usage entry if previous times used is 1
     *
     * @covers ::decreaseCustomerCouponTimesUsed
     *
     * @throws Varien_Exception if unable to create dummy customer usage limits
     */
    public function decreaseCustomerCouponTimesUsed_whenTimesUsedOnce_removesCustomerCouponUsageEntry()
    {
        $timesUsed = 1;
        Bolt_Boltpay_CouponHelper::createDummyCouponCustomerUsageLimits(
            self::$couponId,
            self::$customerId,
            $timesUsed
        );
        /** @var Mage_SalesRule_Model_Coupon $coupon */
        $coupon = Mage::getModel('salesrule/coupon')->load(self::$couponId);
        $this->currentMock->decreaseCustomerCouponTimesUsed(self::$customerId, $coupon);
        $couponUsage = new Varien_Object();
        Mage::getResourceModel('salesrule/coupon_usage')->loadByCustomerCoupon(
            $couponUsage,
            self::$customerId,
            self::$couponId
        );
        $this->assertTrue($couponUsage->isEmpty());
    }

    /**
     * @test
     * @covers ::decreaseCustomerRuleTimesUsed
     */
    public function decreaseCustomerRuleTimesUsed_always_decreasesCustomerRuleTimesUsed()
    {
        $timesUsed = 123;
        Bolt_Boltpay_CouponHelper::createDummyRuleCustomerUsageLimits(
            self::$ruleId,
            self::$customerId,
            $timesUsed
        );
        $this->currentMock->decreaseCustomerRuleTimesUsed(self::$customerId, self::$ruleId);
        $customerRule = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule(self::$customerId, self::$ruleId);
        $this->assertEquals($timesUsed - 1, $customerRule->getTimesUsed());
    }
}