<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');
require_once('CouponHelper.php');

use Bolt_Boltpay_Block_Checkout_Boltpay as BoltpayCheckoutBlock;
use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Checkout_Boltpay
 */
class Bolt_Boltpay_Block_Checkout_BoltpayTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int dummy quote id */
    const QUOTE_ID = 1234;

    /** @var int dummy reserved customer id */
    const RESERVED_CUSTOMER_ID = 1234;

    /** @var string dummy IP address */
    const DEFAULT_IP = '4.17.138.68';

    /** @var string dummy location info from IPStack */
    const LOCATION_INFO = '{"ip":"4.17.138.68","type":"ipv4","continent_code":"NA","continent_name":"North America","country_code":"US","country_name":"United States","region_code":"MA","region_name":"Massachusetts","city":"Westford","zip":"01460","latitude":42.537960052490234,"longitude":-71.48497009277344}';

    /** @var string dummy function for transforming hint data for checkout JS */
    const HINTS_TRANSFORM_FUNCTION = 'function(a){return a;}';

    /** @var string dummy custom check function for checkout JS */
    const CHECK_FUNCTION = 'if(false)return;';

    /** @var string dummy on check callback for chekout JS */
    const ON_CHECK_CALLBACK = 'if (!checkout.validate()) return false;';

    /** @var string dummy order token */
    const TOKEN = '6dbyZ9XuB33n9sgZ';

    /** @var string Test class name */
    protected $testClassName = 'Bolt_Boltpay_Block_Checkout_Boltpay';

    /** @var MockObject|BoltpayCheckoutBlock */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_BoltOrder
     */
    private $boltOrderMock;

    /**
     * @var MockObject|Mage_Customer_Model_Session
     */
    private $customerSessionMock;

    /**
     * @var MockObject|Mage_Checkout_Model_Type_Onepage
     */
    private $checkoutTypeOnepageMock;

    /**
     * @var MockObject|Boltpay_Guzzle_ApiClient
     */
    private $apiClientMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws ReflectionException if unable to stub BoltOrder model
     * @throws Exception if test class name is not set
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods()
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->apiClientMock = $this->getClassPrototype('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('get', 'getBody'))->getMock();

        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')->getMock();
        $this->boltHelperMock->method('getApiClient')->willReturn($this->apiClientMock);

        $this->boltOrderMock = $this->getClassPrototype('Bolt_Boltpay_Model_BoltOrder', false )
            ->setMethods(array('getBoltOrderTokenPromise', 'transmit'))
            ->enableProxyingToOriginalMethods()->getMock();

        TestHelper::stubModel('boltpay/boltOrder', $this->boltOrderMock);

        $this->customerSessionMock = $this->getClassPrototype('Mage_Customer_Model_Session')
            ->setMethods(array('isLoggedIn', 'getId', 'setBoltUserId'))->getMock();

        $this->checkoutTypeOnepageMock = $this->getClassPrototype('Mage_Checkout_Model_Type_Onepage')
            ->getMock();
    }

    /**
     * Restore original stubbed values and reset route
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        Mage::app()->getRequest()->setRouteName(null)->setControllerName(null);
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that Mage internal constructor sets _jsUrl property to expected value when module is in sandbox mode
     *
     * @covers ::_construct
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function _construct_inSandbox_setsJSUrlFromHelper()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', 1);
        $currentObject = new BoltpayCheckoutBlock();
        $this->assertAttributeEquals('https://connect-sandbox.bolt.com/connect.js', '_jsUrl', $currentObject);
    }

    /**
     * @test
     * that getTrackJsUrl constructor returns expected value when module is in production mode
     *
     * @covers ::_construct
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function _construct_inProduction_setsJSUrlFromHelper()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', 0);
        $currentObject = new BoltpayCheckoutBlock();
        $this->assertAttributeEquals('https://connect.bolt.com/connect.js', '_jsUrl', $currentObject);
    }

    /**
     * @test
     * that getTrackJsUrl constructor returns expected value when module is in sandbox mode
     *
     * @covers ::getTrackJsUrl
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getTrackJsUrl_inSandbox_returnsJSUrlFromHelperWithSuffix()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', 1);
        $this->assertEquals(
            'https://connect-sandbox.bolt.com/track.js',
            $this->currentMock->getTrackJsUrl()
        );
    }

    /**
     * @test
     * that Mage internal constructor sets _jsUrl property to expected value when module is in production mode
     *
     * @covers ::getTrackJsUrl
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getTrackJsUrl_inProduction_returnsJSUrlFromHelperWithSuffix()
    {
        TestHelper::stubConfigValue('payment/boltpay/test', 0);
        $this->assertEquals(
            'https://connect.bolt.com/track.js',
            $this->currentMock->getTrackJsUrl()
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Block_Checkout_Boltpay::getCartDataJs}
     *
     * @param string $checkoutType
     * @return array consisting of quote and current class mock
     * @throws Mage_Core_Exception if unable to stub Boltpay helper
     * @throws Exception if test class name is not defined
     */
    private function getCartDataJsSetUp($checkoutType)
    {
        $quote = Mage::getModel('sales/quote');
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $currentMock = $this->getTestClassPrototype(true)
            ->setMethods(
                array(
                    'getSessionQuote',
                    'getAddressHints',
                    'getReservedUserId',
                    'isEnableMerchantScopedAccount',
                    'buildBoltCheckoutJavascript'
                )
            )
            ->getMock();
        $currentMock->expects($this->once())->method('getSessionQuote')->with($checkoutType)->willReturn($quote);
        return array($quote, $currentMock);
    }

    /**
     * @test
     * that getCartDataJs adds signed_merchant_user_id to hint data if reserved user id exists in session
     * and merchant scoped account is enabled and proceeds to build Bolt checkout javascript
     *
     * @covers ::getCartDataJs
     * @covers ::getAddressHints
     *
     * @throws Mage_Core_Exception if unable to setup test dependencies
     */
    public function getCartDataJs_withCustomerIdAndMerchantScoped_buildsJsWithMerchantUserId()
    {
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE;
        list($quote, $currentMock) = $this->getCartDataJsSetUp($checkoutType);

        $this->boltOrderMock->expects($this->atLeastOnce())->method('getBoltOrderTokenPromise')->with($checkoutType);
        $currentMock->expects($this->once())->method('getReservedUserId')->with($quote)
            ->willReturn(self::RESERVED_CUSTOMER_ID);
        $currentMock->expects($this->once())->method('isEnableMerchantScopedAccount')->willReturn(true);

        $signedApiResponse = array(
            "merchant_user_id" => self::RESERVED_CUSTOMER_ID,
            "signature"        => sha1('test'),
            "nonce"            => rand(100000000, 999999999),
        );
        $this->boltHelperMock->expects($this->once())->method('transmit')
            ->with('sign', array('merchant_user_id' => self::RESERVED_CUSTOMER_ID))
            ->willReturn((object)$signedApiResponse);

        $currentMock->expects($this->once())->method('buildBoltCheckoutJavascript')
            ->with(
                $checkoutType,
                $quote,
                new PHPUnit_Framework_Constraint_ArraySubset(array('signed_merchant_user_id' => $signedApiResponse)),
                $this->equalTo(Mage::getModel('boltpay/boltOrder')->getBoltOrderTokenPromise($checkoutType))
            );
        $currentMock->getCartDataJs($checkoutType);
    }

    /**
     * @test
     * that getCartDataJs logs exception if thrown from getBoltOrderTokenPromise and proceeds to build Bolt Checkout JS
     *
     * @covers ::getCartDataJs
     *
     * @throws Mage_Core_Exception if unable to setup test dependencies
     */
    public function getCartDataJs_whenGetPromiseThrowsException_logsExceptionAndBuildsJS()
    {
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE;
        list($quote, $currentMock) = $this->getCartDataJsSetUp($checkoutType);
        $metaData = array('quote' => var_export($quote->debug(), true));

        $exception = new Exception('Expected exception');
        $this->boltOrderMock->expects($this->once())->method('getBoltOrderTokenPromise')->with($checkoutType)
            ->willThrowException($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception, $metaData);
        $this->boltHelperMock->expects($this->once())->method('notifyException')
            ->with(new Exception($exception), $metaData);

        $currentMock->expects($this->once())->method('buildBoltCheckoutJavascript')
            ->with($checkoutType, $quote, $this->anything(), null);
        $currentMock->getCartDataJs($checkoutType);
    }

    /**
     * @test
     * that getCartDataJs logs exception thrown from buildBoltCheckoutJavascript and returns null
     *
     * @covers ::getCartDataJs
     *
     * @throws Mage_Core_Exception if unable to setup test dependencies
     */
    public function getCartDataJs_whenBuildingJSThrowsException_logsExceptionAndReturnsNull()
    {
        list(, $currentMock) = $this->getCartDataJsSetUp(BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE);

        $exception = new Exception('Expected exception');
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);

        $currentMock->expects($this->once())->method('buildBoltCheckoutJavascript')->willThrowException($exception);
        $this->assertNull($currentMock->getCartDataJs(BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE));
    }

    /**
     * @test
     * that isAdminAndUseJsInAdmin returns true only if checkout type is admin and use js in admin is enabled
     *
     * @covers ::isAdminAndUseJsInAdmin
     *
     * @dataProvider isAdminAndUseJsInAdmin_withVariousConfigs_returnsExpectedBooleanProvider
     *
     * @param string $checkoutType currently used
     * @param bool   $useJavascriptInAdmin configuration value
     * @param bool   $expectedResult of method call
     *
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function isAdminAndUseJsInAdmin_withVariousConfigs_returnsExpectedBoolean($checkoutType, $useJavascriptInAdmin, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/use_javascript_in_admin', $useJavascriptInAdmin);
        $this->assertEquals(
            $expectedResult,
            TestHelper::callNonPublicFunction($this->currentMock, 'isAdminAndUseJsInAdmin', array($checkoutType))
        );
    }

    /**
     * Data provider for {@see isAdminAndUseJsInAdmin_withVariousConfigs_returnsExpectedBoolean}
     *
     * @return array containing checkout type, config value for using js in admin and expected result of method call
     */
    public function isAdminAndUseJsInAdmin_withVariousConfigs_returnsExpectedBooleanProvider()
    {
        return array(
            'Admin and use disabled'      => array(
                'checkoutType'         => BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN,
                'useJavascriptInAdmin' => false,
                'expectedResult'       => true
            ),
            'Admin and use enabled'       => array(
                'checkoutType'         => BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN,
                'useJavascriptInAdmin' => true,
                'expectedResult'       => false
            ),
            'Multi-page and use disabled' => array(
                'checkoutType'         => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE,
                'useJavascriptInAdmin' => false,
                'expectedResult'       => false
            ),
            'Multi-page and use enabled'  => array(
                'checkoutType'         => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE,
                'useJavascriptInAdmin' => true,
                'expectedResult'       => false
            ),
        );
    }

    /**
     * @test
     * that buildCartData returns array containing order token from provided input object
     *
     * @covers ::buildCartData
     */
    public function buildCartData_withoutError_returnsArrayContainingOrderToken()
    {
        $this->assertEquals(
            array('orderToken' => self::TOKEN),
            $this->currentMock->buildCartData((object)array('token' => self::TOKEN))
        );
    }

    /**
     * @test
     * that buildCartData returns array containing empty order token if provided with empty array
     *
     * @covers ::buildCartData
     */
    public function buildCartData_withEmptyOrderCreationResponse_returnsEmptyStringAsOrderToken()
    {
        $this->assertEquals(
            array('orderToken' => ''),
            $this->currentMock->buildCartData(array())
        );
    }

    /**
     * @test
     * that buildCartData returns array containing error message if it exists in Magento registry
     *
     * @covers ::buildCartData
     *
     * @throws Mage_Core_Exception if unable to stub registry value
     */
    public function buildCartData_withApiError_returnsArrayContainingError()
    {
        $apiErrorMessage = 'Expected API array';
        TestHelper::stubRegistryValue('bolt_api_error', $apiErrorMessage);

        $this->assertEquals(
            array('orderToken' => self::TOKEN, 'error' => $apiErrorMessage),
            $this->currentMock->buildCartData((object)array('token' => self::TOKEN))
        );
    }

    /**
     * @test
     * that buildCartData returns array containing both token and error message if present in object provided
     *
     * @covers ::buildCartData
     */
    public function buildCartData_withErrorInOrderCreationResponse_returnsArrayContainingBothErrorMessageAndOrderToken()
    {
        $errorMessage = 'Expected error.';

        $this->assertEquals(
            array('orderToken' => self::TOKEN, 'error' => $errorMessage),
            $this->currentMock->buildCartData((object)array('token' => self::TOKEN, 'error' => $errorMessage))
        );
    }

    /**
     * @test
     * that getSessionObject returns adminhtml/session_quote singleton if checkout type provided is admin
     *
     * @covers ::getSessionObject
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     * @throws ReflectionException if current mock doesn't have getSessionObject method
     */
    public function getSessionObject_withAdminCheckoutType_returnsAdminSessionQuote()
    {
        $adminSessionCartMock = $this->getClassPrototype('Mage_Adminhtml_Model_Session_Quote')->getMock();
        TestHelper::stubSingleton('adminhtml/session_quote', $adminSessionCartMock);
        $this->assertEquals(
            $adminSessionCartMock,
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getSessionObject',
                array(BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN)
            )
        );
    }

    /**
     * @test
     * that getSessionObject returns checkout/session singleton for any non-admin checkout type
     *
     * @covers ::getSessionObject
     *
     * @param string $checkoutType code currently in use
     * @throws Mage_Core_Exception if unable to stub checkout session
     * @throws ReflectionException if method tested doesn't exist
     */
    public function getSessionObject_withNonAdminCheckoutType_returnCheckoutSession($checkoutType)
    {
        $checkoutSession = $this->getClassPrototype('Mage_Checkout_Model_Session')->getMock();
        TestHelper::stubSingleton('checkout/session', $checkoutSession);
        $this->assertEquals(
            $checkoutSession,
            TestHelper::callNonPublicFunction(
                $this->currentMock,
                'getSessionObject',
                array($checkoutType)
            )
        );
    }

    /**
     * Data provider for {@see getSessionObject_withNonAdminCheckoutType_returnCheckoutSession}
     * Provides all checkout type codes except admin
     *
     * @return array of checkout type codes
     */
    public function getSessionObject_withNonAdminCheckoutType_returnCheckoutSessionProvider()
    {
        return array(
            'Multi-page checkout' => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE),
            'Firecheckout'        => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_FIRECHECKOUT),
            'One-page checkout'   => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE),
            'Product page'        => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_PRODUCT_PAGE),
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Block_Checkout_Boltpay::getAddressHints}
     *
     * @param int|null                            $customerId to be set as customer session id
     * @param Mage_Sales_Model_Quote_Address|null $shippingAddress to be set as shipping address to quote returned
     * @return Mage_Sales_Model_Quote instance with configured shipping address if provided
     * @throws Mage_Core_Exception if unable to stub customer/session singleton
     */
    private function getAddressHintsSetUp($customerId = null, $shippingAddress = null)
    {
        $this->customerSessionMock->method('isLoggedIn')->willReturn($customerId !== null);
        $this->customerSessionMock->method('getId')->willReturn($customerId);
        TestHelper::stubSingleton('customer/session', $this->customerSessionMock);

        $quote = Mage::getModel('sales/quote');
        if ($shippingAddress) {
            $quote->setShippingAddress($shippingAddress);
        }

        return $quote;
    }

    /**
     * @test
     * that getAddressHints returns quote shipping address data as hints
     *
     * @covers ::getAddressHints
     *
     * @throws ReflectionException if getAddressHints method doesn't exist
     * @throws Mage_Core_Exception from test setup if unable to stub customer session singleton
     */
    public function getAddressHints_withValidShippingAddress_returnsArrayWithAddressInPrefill()
    {
        $shippingAddress = Mage::getModel('sales/quote_address');
        $shippingAddress
            ->setEmail('test@example.com')
            ->setFirstname('Test')
            ->setLastname('Test')
            ->setStreet("Address line 1\nAddress line 2")
            ->setCity('Test city')
            ->setRegion('Test region')
            ->setZip('11000')
            ->setTelephone('0123456789')
            ->setCountryId('test');
        $quote = $this->getAddressHintsSetUp(null, $shippingAddress);
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE;

        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getAddressHints',
            array($quote, $checkoutType)
        );

        $this->assertEquals(
            array(
                'prefill' => array(
                    'email'        => 'test@example.com',
                    'firstName'    => 'Test',
                    'lastName'     => 'Test',
                    'addressLine1' => 'Address line 1',
                    'addressLine2' => 'Address line 2',
                    'city'         => 'Test city',
                    'state'        => 'Test region',
                    'phone'        => '0123456789',
                    'country'      => 'test',
                )
            ),
            $result
        );
    }

    /**
     * @test
     * that getAddressHints doesn't return pre-fill data when quote address is Apple Pay related
     *
     * @covers ::getAddressHints
     * @throws ReflectionException if getAddressHints method doesn't exist
     * @throws Mage_Core_Exception from test setup if unable to stub customer session singleton
     */
    public function getAddressHints_withApplePayRelatedData_returnsArrayWithoutPrefill()
    {
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE;
        $shippingAddress = Mage::getModel('sales/quote_address');
        $shippingAddress
            ->setEmail('fake@email.com')
            ->setTelephone('1111111111');
        $quote = $this->getAddressHintsSetUp(null, $shippingAddress);

        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getAddressHints',
            array($quote, $checkoutType)
        );

        $this->assertArrayNotHasKey('prefill', $result);
    }

    /**
     * @test
     * that getAddressHints returns pre-fill data from customer primary shipping address if quote doesn't have
     * shipping address
     *
     * @covers ::getAddressHints
     *
     * @throws ReflectionException if getAddressHints method doesn't exist
     * @throws Mage_Core_Exception from test setup if unable to stub session singleton
     */
    public function getAddressHints_whehLoggedInAndNoShippingAddress_returnsPrefillFromPrimaryShippingAddress()
    {
        $customerAddress = Mage::getModel('customer/address');
        $customerAddress
            ->setEmail('test@example.com')
            ->setFirstname('Test')
            ->setLastname('Test')
            ->setStreet("Address line 1\nAddress line 2")
            ->setCity('Test city')
            ->setRegion('Test region')
            ->setZip('11000')
            ->setTelephone('0123456789')
            ->setCountryId('test');

        $quote = $this->getAddressHintsSetUp(self::RESERVED_CUSTOMER_ID, null);
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE;

        $customerMock = $this->getClassPrototype('Mage_Customer_Model_Customer')
            ->setMethods(array('load', 'getPrimaryShippingAddress', 'getEmail'))->getMock();
        $customerMock->expects($this->once())->method('load')->with(self::RESERVED_CUSTOMER_ID)->willReturnSelf();
        $customerMock->expects($this->once())->method('getPrimaryShippingAddress')->willReturn($customerAddress);
        $customerMock->expects($this->once())->method('getEmail')->willReturn('test2@example.com');
        TestHelper::stubModel('customer/customer', $customerMock);

        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getAddressHints',
            array($quote, $checkoutType)
        );

        $this->assertEquals(
            array(
                'prefill' => array(
                    'email'        => 'test@example.com',
                    'firstName'    => 'Test',
                    'lastName'     => 'Test',
                    'addressLine1' => 'Address line 1',
                    'addressLine2' => 'Address line 2',
                    'city'         => 'Test city',
                    'state'        => 'Test region',
                    'phone'        => '0123456789',
                    'country'      => 'test',
                )
            ),
            $result
        );
    }

    /**
     * @test
     * that getAddressHints returns pre-fill data from customer primary shipping address if quote doesn't have
     * shipping address
     *
     * @covers ::getAddressHints
     *
     * @throws ReflectionException if getAddressHints method doesn't exist
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function getAddressHints_fromAdminCheckout_returnsEmailPrefillFromAdminSession()
    {
        $quote = $this->getAddressHintsSetUp(self::RESERVED_CUSTOMER_ID, null);
        $checkoutType = BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN;
        $adminSessionMock = $this->getClassPrototype('Mage_Adminhtml_Model_Session')
            ->setMethods(array('getOrderShippingAddress'))->getMock();
        $adminSessionMock->expects($this->once())->method('getOrderShippingAddress')
            ->willReturn(array('email' => 'test@example.com'));
        TestHelper::stubSingleton('admin/session', $adminSessionMock);
        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'getAddressHints',
            array($quote, $checkoutType)
        );
        $this->assertEquals(
            array(
                'prefill'               => array(
                    'email' => 'test@example.com',
                ),
                'virtual_terminal_mode' => true
            ),
            $result
        );
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Block_Checkout_Boltpay::getAddressHints}
     *
     * @param null|string $checkoutMethod to be set ase current checkout method to onepage checkout
     * @param null|int    $customerId to be set as current session customer id
     *
     * @return Mage_Sales_Model_Quote dummy quote
     *
     * @throws Mage_Core_Exception if unable to stub customer/session or checkout/type_onepage singleton
     */
    private function getReservedUserIdSetUp($customerId = null, $checkoutMethod = null)
    {
        $this->customerSessionMock->method('isLoggedIn')->willReturn($customerId !== null);
        $this->customerSessionMock->method('getId')->willReturn($customerId);
        $this->checkoutTypeOnepageMock->method('getCheckoutMethod')->willReturn($checkoutMethod);

        TestHelper::stubSingleton('customer/session', $this->customerSessionMock);
        TestHelper::stubSingleton('checkout/type_onepage', $this->checkoutTypeOnepageMock);
        return $quote = Mage::getModel('sales/quote')->setStoreId(1);
    }

    /**
     * @test
     * that getReservedUserId returns new Bolt user id from customer increment id
     * after setting it to current customer if customer is logged in
     *
     * @covers ::getReservedUserId
     *
     * @throws Mage_Core_Model_Store_Exception if unable to create dummy customer
     * @throws Varien_Exception if unable to delete dummy customer
     * @throws Mage_Core_Exception from test setup if unable to stub required singletons
     */
    public function getReservedUserId_withCustomerLoggedInWithoutBoltUserId_setsBoltUserIdAndSaves()
    {
        $customerId = Bolt_Boltpay_CouponHelper::createDummyCustomer(array(), 'getreserveduserid@bolt.com');

        $quote = $this->getReservedUserIdSetUp($customerId);

        $boltUserId = $this->currentMock->getReservedUserId($quote);

        $customer = Mage::getModel('customer/customer')->load($customerId);
        $this->assertEquals($boltUserId, $customer->getBoltUserId());

        Bolt_Boltpay_CouponHelper::deleteDummyCustomer($customerId);
    }

    /**
     * @test
     * that getReservedUserId returns new Bolt user id from customer increment id after setting it on session
     * if onepage checkout method is equal to register
     *
     * @covers ::getReservedUserId
     *
     * @throws Mage_Core_Exception from test setup if unable to stub singletons
     */
    public function getReservedUserId_withRegisterCheckoutMethod_setsBoltUserIdToSession()
    {
        $quote = $this->getReservedUserIdSetUp(null, Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
        $boltUserId = null;
        $this->customerSessionMock->expects($this->once())->method('setBoltUserId')->willReturnCallback(
            function ($value) use (&$boltUserId) {
                $boltUserId = $value;
            }
        );
        $reservedUserId = $this->currentMock->getReservedUserId($quote);
        $this->assertEquals($boltUserId, $reservedUserId);
        $this->assertNotNull($reservedUserId);

    }

    /**
     * @test
     * that getReservedUserId returns null if customer is not logged in and checkout method is not register
     *
     * @covers ::getReservedUserId
     *
     * @throws Mage_Core_Exception from test setup if unable to stub singleton
     */
    public function getReservedUserId_customerNotSignedInAndCheckoutMethodIsNotRegister_returnsNull()
    {
        $quote = $this->getReservedUserIdSetUp(null, null);
        $this->assertNull($this->currentMock->getReservedUserId($quote));

    }

    /**
     * @test
     * that getCssSuffix returns CSS_SUFFIX class constant
     *
     * @covers ::getCssSuffix
     */
    public function getCssSuffix_always_returnsCSSSuffixConstant()
    {
        $this->assertEquals(BoltpayCheckoutBlock::CSS_SUFFIX, $this->currentMock->getCssSuffix());
    }

    /**
     * @test
     * that getSelectorsCSS follows expected formula when returning CSS based on stored configuration
     *
     * @covers ::getSelectorsCSS
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getSelectorsCSS_withFormula_returnsExpectedOutput()
    {
        $parentSelector = '.btn-proceed-checkout';
        $parentSelectorWithSuffix = $parentSelector . '-' . BoltpayCheckoutBlock::CSS_SUFFIX;
        $childSelector = 'body';
        $style = '{ max-width: 95%;}';

        $css = "$parentSelector { $childSelector $style}";

        TestHelper::stubConfigValue('payment/boltpay/selector_styles', $css);
        $result = $this->currentMock->getSelectorsCSS();
        $this->assertEquals(
            $childSelector . $parentSelectorWithSuffix . $style . $parentSelectorWithSuffix . " " . $childSelector . $style,
            $result
        );
    }

    /**
     * @test
     * that getSelectorsCSS returns expected CSS output when provided with example that is displayed on Magento backend
     *
     * @covers ::getSelectorsCSS
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getSelectorsCSS_withExample_returnsExpectedOutput()
    {
        $css = /** @lang SCSS */
            <<<SCSS
  .btn-proceed-checkout {
    body { max-width: 95%;}
    li.button-box { float: right; margin: 2px;}
  }
  ||
  .btn-quickbuy {
    li.button-container { float: left; }
    .btn-quickbuy { width: 10px; }
  }
SCSS;

        TestHelper::stubConfigValue('payment/boltpay/selector_styles', $css);
        $result = $this->currentMock->getSelectorsCSS();
        $this->assertEquals(
            /** @lang CSS */
            "body.btn-proceed-checkout-bolt-css-suffix{ max-width: 95%;}.btn-proceed-checkout-bolt-css-suffix body{ max-width: 95%;}li.button-box.btn-proceed-checkout-bolt-css-suffix{ float: right; margin: 2px;}.btn-proceed-checkout-bolt-css-suffix li.button-box{ float: right; margin: 2px;}li.button-container.btn-quickbuy-bolt-css-suffix{ float: left; }.btn-quickbuy-bolt-css-suffix li.button-container{ float: left; }.btn-quickbuy.btn-quickbuy-bolt-css-suffix{ width: 10px; }.btn-quickbuy-bolt-css-suffix .btn-quickbuy{ width: 10px; }",
            $result
        );
    }

    /**
     * @test
     * that getPublishableKey returns result of appropriate helper method
     * depending on checkout type provided as parameter
     *
     * @covers ::getPublishableKey
     *
     * @dataProvider getPublishableKey_withVariousCheckoutTypes_callsRelatedHelperMethodProvider
     *
     * @param string $checkoutType currently used
     * @param string $methodExpected to be called on helper
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function getPublishableKey_withVariousCheckoutTypes_callsRelatedHelperMethod($checkoutType, $methodExpected)
    {
        $expectedResult = sha1('bolt');
        $this->boltHelperMock->expects($this->once())->method($methodExpected)->willReturn($expectedResult);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $this->assertEquals(
            $expectedResult,
            $this->currentMock->getPublishableKey($checkoutType)
        );
    }

    /**
     * Data provider for {@see getPublishableKey_withVariousCheckoutTypes_callsRelatedHelperMethod}
     *
     * @return array containing checkout type and method that is expected to be called
     */
    public function getPublishableKey_withVariousCheckoutTypes_callsRelatedHelperMethodProvider()
    {
        return array(
            array('checkoutType' => 'multi-page', 'methodExpected' => 'getPublishableKeyMultiPage'),
            array('checkoutType' => 'multipage', 'methodExpected' => 'getPublishableKeyMultiPage'),
            array('checkoutType' => 'back-office', 'methodExpected' => 'getPublishableKeyBackOffice'),
            array('checkoutType' => 'backoffice', 'methodExpected' => 'getPublishableKeyBackOffice'),
            array('checkoutType' => 'admin', 'methodExpected' => 'getPublishableKeyBackOffice'),
            array('checkoutType' => 'one-page', 'methodExpected' => 'getPublishableKeyOnePage'),
            array('checkoutType' => 'onepage', 'methodExpected' => 'getPublishableKeyOnePage'),
            array('checkoutType' => '', 'methodExpected' => 'getPublishableKeyOnePage'),
        );
    }

    /**
     * @test
     * that getIpAddress returns IP address from provided request headers
     *
     * @covers ::getIpAddress
     *
     * @dataProvider getIpAddress_withVariousHeaders_willReturnExpectedIPProvider
     *
     * @param array  $headers to be added to $_SERVER
     * @param string $expectedResult of the method call
     */
    public function getIpAddress_withVariousHeaders_willReturnExpectedIP($headers, $expectedResult)
    {
        $previousHeaders = $_SERVER;
        $_SERVER = array_merge($_SERVER, $headers);
        $this->assertEquals(
            $expectedResult,
            $this->currentMock->getIpAddress()
        );
        $_SERVER = $previousHeaders;
    }

    /**
     * Data provider for {@see getIpAddress_withVariousHeaders_willReturnExpectedIP}
     *
     * @return array containing array of IP-related headers and expected result of the method call
     */
    public function getIpAddress_withVariousHeaders_willReturnExpectedIPProvider()
    {
        return array(
            'Only REMOTE_ADDR with trim'         => array(
                'headers'        => array('REMOTE_ADDR' => sprintf(" %s ", self::DEFAULT_IP)),
                'expectedResult' => self::DEFAULT_IP
            ),
            'No headers'                         => array(
                'headers'        => array(),
                'expectedResult' => null
            ),
            'Loopback IP'                        => array(
                'headers'        => array('REMOTE_ADDR' => '127.0.0.1'),
                'expectedResult' => null
            ),
            'Local IP'                           => array(
                'headers'        => array('REMOTE_ADDR' => '192.168.1.1'),
                'expectedResult' => null
            ),
            'Additional loopback IP'             => array(
                'headers'        => array('REMOTE_ADDR' => '::1'),
                'expectedResult' => null
            ),
            'Invalid IP'                         => array(
                'headers'        => array('REMOTE_ADDR' => 'TEST12345'),
                'expectedResult' => null
            ),
            'Priorities'                         => array(
                'headers'        => array(
                    'HTTP_FORWARDED_FOR' => sprintf("%s ", self::DEFAULT_IP),
                    'HTTP_CLIENT_IP'     => ' 5.67.89.9'
                ),
                'expectedResult' => '5.67.89.9'
            ),
            'Skip invalid even when prioritized' => array(
                'headers'        => array(
                    'HTTP_FORWARDED_FOR' => sprintf("%s ", self::DEFAULT_IP),
                    'HTTP_CLIENT_IP'     => ' 5.67.89.9'
                ),
                'expectedResult' => '5.67.89.9'
            ),
        );
    }

    /**
     * Setup method for tests covering {@see BoltpayCheckoutBlock::getLocationEstimate}
     *
     * @param null|string $locationInfo to be returned from session as cached data
     *
     * @return MockObject|BoltpayCheckoutBlock preconfigured mocked instance of class tested
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function getLocationEstimateSetUp($locationInfo = null)
    {
        $sessionMock = $this->getClassPrototype('Mage_Core_Model_Session')
            ->setMethods(array('getLocationInfo', 'setLocationInfo'))->getMock();
        $sessionMock->expects($this->once())->method('getLocationInfo')->willReturn($locationInfo);
        TestHelper::stubSingleton('core/session', $sessionMock);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        return $sessionMock;
    }

    /**
     * @test
     * that getLocationEstimate returns data from session cache if present
     *
     * @covers ::getLocationEstimate
     *
     * @throws Mage_Core_Exception from test setup if unable to stub singleton
     */
    public function getLocationEstimate_withExistingLocationInfoInSession_returnsFromSession()
    {
        $this->apiClientMock->expects($this->never())->method('get');
        $this->getLocationEstimateSetUp(self::LOCATION_INFO);
        $this->assertEquals(self::LOCATION_INFO, $this->currentMock->getLocationEstimate());
    }

    /**
     * @test
     * that getLocationEstimate will request IP info from API if it's not cached in session
     *
     * @covers ::getLocationEstimate
     * @covers ::url_get_contents
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     * @throws Mage_Core_Exception from test setup if unable to stub singleton
     */
    public function getLocationEstimate_withoutLocationInSession_requestsFromAPIAndSavesToSession()
    {
        $ipStackAccessKey = sha1('bolt');
        TestHelper::stubConfigValue(
            'payment/boltpay/ipstack_key',
            Mage::helper('core')->encrypt($ipStackAccessKey)
        );

        $previousIp = $_SERVER['REMOTE_ADDR'];
        $_SERVER['REMOTE_ADDR'] = self::DEFAULT_IP;

        $this->apiClientMock->expects($this->once())->method('get')
            ->with(
                "http://api.ipstack.com/" . self::DEFAULT_IP . "?access_key=" . $ipStackAccessKey . "&output=json&legacy=1"
            )
            ->willReturnSelf();
        $this->apiClientMock->expects($this->once())->method('getBody')->willReturn(self::LOCATION_INFO);
        $sessionMock = $this->getLocationEstimateSetUp();
        $sessionMock->expects($this->once())->method('setLocationInfo')->with(self::LOCATION_INFO);
        $this->assertEquals(self::LOCATION_INFO, $this->currentMock->getLocationEstimate());
        $_SERVER['REMOTE_ADDR'] = $previousIp;
    }

    /**
     * @test
     * that url_get_contents logs exception and returns null if an exception happens inside API client call
     *
     * @covers ::url_get_contents
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    public function url_get_contents_whenApiClientThrowsException_logsExceptionAndReturnsNull()
    {
        $url = 'https://bolt.com';
        $exception = new Exception('Request timed out');
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $this->apiClientMock->expects($this->once())->method('get')->with($url)->willThrowException($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception);
        $this->assertNull($this->currentMock->url_get_contents($url));
    }

    /**
     * @test
     * that getPublishableKeyForRoute returns publishable key for checkout type that is based on route and controller
     *
     * @covers ::getPublishableKeyForRoute
     *
     * @dataProvider getPublishableKeyForRoute_withVariousRoutes_returnsKeyForExpectedCheckoutTypeProvider
     *
     * @param string $route current Magento route
     * @param string $controller current Magento controller
     * @param string $checkoutType that is expected to be used
     *
     * @throws Exception if test class name is not defined
     */
    public function getPublishableKeyForRoute_withVariousRoutes_returnsKeyForExpectedCheckoutType($route, $controller, $checkoutType)
    {
        /** @var MockObject|BoltpayCheckoutBlock $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getPublishableKey'))->getMock();
        Mage::app()->getRequest()->setRouteName($route);
        Mage::app()->getRequest()->setControllerName($controller);
        $currentMock->expects($this->once())->method('getPublishableKey')->with($checkoutType);
        $currentMock->getPublishableKeyForRoute();
    }

    /**
     * Data provider for {@see getPublishableKeyForRoute_withVariousRoutes_returnsKeyForExpectedCheckoutType}
     *
     * @return array containing route, controller and checkout type
     */
    public function getPublishableKeyForRoute_withVariousRoutes_returnsKeyForExpectedCheckoutTypeProvider()
    {
        return array(
            'Admin'        => array(
                'route'        => 'adminhtml',
                'controller'   => 'index',
                'checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN
            ),
            'Firecheckout' => array(
                'route'        => 'firecheckout',
                'controller'   => 'index',
                'checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE
            ),
            'One-page'     => array(
                'route'        => 'checkout',
                'controller'   => 'onepage',
                'checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE
            ),
            'Multi-page'   => array(
                'route'        => '',
                'controller'   => '',
                'checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE
            ),
        );
    }

    /**
     * Sets dependencies used by test for {@see Bolt_Boltpay_Block_Checkout_Boltpay::getPublishableKeyForThisPage()}
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     *
     * @throws Exception if there is a failure retrieving the focus mock's Request object
     */
    private function setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName) {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Checkout_Boltpay')
            ->setMethods(array('getPublishableKey'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock()
        ;
        $this->currentMock->getRequest()
            ->setRouteName($routeName)
            ->setControllerName($controllerName);

        $mockProxy = new Bolt_Boltpay_Block_Checkout_Boltpay();

        TestHelper::stubConfigValue('payment/boltpay/publishable_key_multipage', $multiStepKey);
        TestHelper::stubConfigValue('payment/boltpay/publishable_key_onepage', $paymentOnlyKey);

        $this->currentMock->expects($this->exactly(2))->method('getPublishableKey')
            ->withConsecutive(
                array($this->equalTo('multi-page')),
                array($this->equalTo('one-page'))
            )
            ->willReturnOnConsecutiveCalls(
                $mockProxy->getPublishableKey('multi-page'),
                $mockProxy->getPublishableKey('one-page')
            )
        ;
    }

    /**
     * @test
     * that the appropriate product key is returned for a particular page when at least a multi-step key or payment only
     * key has been configured
     *
     * @covers ::getPublishableKeyForThisPage
     * @dataProvider getPublishableKeyForThisPage_withAtLeastOneKeyConfiguredProvider
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     * @param string      $expectedReturnValue      The key that is expected to be returned by the mock
     *
     * @throws Exception if there is a problem in setting up test dependencies
     */
    public function getPublishableKeyForThisPage_withAtLeastOneKeyConfigured_returnsExpectedValue($multiStepKey, $paymentOnlyKey, $routeName, $controllerName, $expectedReturnValue)
    {
        $this->setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName);

        $this->boltHelperMock->expects($this->never())->method('logException');
        $this->boltHelperMock->expects($this->never())->method('notifyException');

        $actualMultiStepKey =  Mage::app()->getStore()->getConfig('payment/boltpay/publishable_key_multipage');
        $actualPaymentOnlyKey = Mage::app()->getStore()->getConfig('payment/boltpay/publishable_key_onepage');

        $actualReturnedValue = $this->currentMock->getPublishableKeyForThisPage();
        $this->assertTrue($actualMultiStepKey || $actualPaymentOnlyKey);
        $this->assertEquals($multiStepKey, $actualMultiStepKey);
        $this->assertEquals($paymentOnlyKey, $actualPaymentOnlyKey);
        $this->assertEquals($expectedReturnValue, $actualReturnedValue);
    }

    /**
     * Provides data for a multi-step key, or a payment-only key, or for both publishable keys in the context of
     * the standard cart page, a custom cart page, the standard checkout page, a standard product page, and any
     * unexpected page identified by route and controller name for
     * {@see Bolt_Boltpay_Block_Checkout_BoltpayTest::getPublishableKeyForThisPage_withAtLeastOneKeyConfigured()}
     *
     * @return array[] in the format of [$multiStepKey, $paymentOnlyKey, $routeName, $controllerName, $expectedReturnValue]
     */
    public function getPublishableKeyForThisPage_withAtLeastOneKeyConfiguredProvider()
    {
        return array(
            "When both keys exist on standard cart page, multi-step is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-standard-cart",
                    "paymentOnlyKey" => "payOnly+multi-key-on-standard-cart",
                    "routeName" => "checkout",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "multi+payOnly-key-on-standard-cart"
                ),
            "When only multi-step key exists on standard cart page, empty string payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi-key-on-standard-cart",
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "multi-key-on-standard-cart"
                ),
            "When only multi-step key exists on standard cart page, null payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi-key-on-standard-cart",
                    "paymentOnlyKey" => null,
                    "routeName" => "checkout",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "multi-key-on-standard-cart"
                ),
            "When only payment-only key exists on standard cart page, null multi-step, payment-only is used" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "payOnly-key-on-standard-cart",
                    "routeName" => "checkout",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "payOnly-key-on-standard-cart"
                ),
            "When both keys exist on custom cart page, multi-step is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-custom-cart",
                    "paymentOnlyKey" => "payOnly+multi-key-on-custom-cart",
                    "routeName" => "custom",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "multi+payOnly-key-on-custom-cart"
                ),
            "When only multi-step key exists on custom cart page, null payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi-key-on-custom-cart",
                    "paymentOnlyKey" => null,
                    "routeName" => "custom",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "multi-key-on-custom-cart"
                ),
            "When only payment-only key exists on custom cart page, null multi-step, payment-only is used" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "payOnly-key-on-custom-cart",
                    "routeName" => "custom",
                    "controllerName" => "cart",
                    "expectedReturnValue" => "payOnly-key-on-custom-cart"
                ),
            "When both keys exist on standard onepage, payment-only is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-onepage",
                    "paymentOnlyKey" => "payOnly+multi-key-on-onepage",
                    "routeName" => "checkout",
                    "controllerName" => "onepage",
                    "expectedReturnValue" => "payOnly+multi-key-on-onepage"
                ),
            "When only payment-only key exists on standard onepage, null multi-step, payment-only is used" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "payOnly-key-on-onepage",
                    "routeName" => "checkout",
                    "controllerName" => "onepage",
                    "expectedReturnValue" => "payOnly-key-on-onepage"
                ),
            "When only multi-step key exists on standard onepage, empty string payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi-key-on-onepage",
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "onepage",
                    "expectedReturnValue" => "multi-key-on-onepage"
                ),
            "When both keys exist on unexpected page, payment-only is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-homepage",
                    "paymentOnlyKey" => "payOnly+multi-key-on-homepage",
                    "routeName" => "cms",
                    "controllerName" => "index",
                    "expectedReturnValue" => "payOnly+multi-key-on-homepage"
                ),
            "When only payment-only key exists on unexpected page, empty string multi-step, payment-only is used" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "payOnly-key-on-homepage",
                    "routeName" => "cms",
                    "controllerName" => "index",
                    "expectedReturnValue" => "payOnly-key-on-homepage"
                ),
            "When only multi-step key exists on unexpected page, empty string payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-homepage",
                    "paymentOnlyKey" => "",
                    "routeName" => "cms",
                    "controllerName" => "index",
                    "expectedReturnValue" => "multi+payOnly-key-on-homepage"
                ),
            "When both keys exist on product page, multi-step is used" =>
                array(
                    "multiStepKey" => "multi+payOnly-key-on-product",
                    "paymentOnlyKey" => "payOnly+multi-key-on-product",
                    "routeName" => "catalog",
                    "controllerName" => "product",
                    "expectedReturnValue" => "multi+payOnly-key-on-product"
                ),
            "When only multi-step key exists on product page, null payment-only, multi-step is used" =>
                array(
                    "multiStepKey" => "multi-key-on-product",
                    "paymentOnlyKey" => null,
                    "routeName" => "catalog",
                    "controllerName" => "product",
                    "expectedReturnValue" => "multi-key-on-product"
                ),
            "When only payment-only key exists on product page, null multi-step, payment-only is used" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "payOnly-key-on-product",
                    "routeName" => "catalog",
                    "controllerName" => "product",
                    "expectedReturnValue" => "payOnly-key-on-product"
                )
        );
    }

    /**
     * @test
     * that an exception is thrown for all variants if neither a multi-step nor a payment-only key has been configured
     *
     * @covers ::getPublishableKeyForThisPage
     * @dataProvider getPublishableKeyForThisPage_whenNoKeyIsConfiguredProvider
     * @expectedException Bolt_Boltpay_BoltException
     * @expectedExceptionMessage No publishable key has been configured.
     *
     * @param string|null $multiStepKey             the multi-step publishable key, empty string or null indicates not set
     * @param string|null $paymentOnlyKey           the payment-only publishable key, empty string or null indicates not set
     * @param string      $routeName                The magento path route name
     * @param string      $controllerName           The magento path controller name
     *
     * @throws Exception if there is a problem in setting up test dependencies
     */
    public function getPublishableKeyForThisPage_whenNoKeyIsConfigured_throwsException($multiStepKey, $paymentOnlyKey, $routeName, $controllerName)
    {
        $this->setUp_getPublishableKeyForThisPage($multiStepKey, $paymentOnlyKey, $routeName, $controllerName);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);

        $this->boltHelperMock->expects($this->once())->method('logException');
        $this->boltHelperMock->expects($this->once())->method('notifyException');

        $actualMultiStepKey =  Mage::app()->getStore()->getConfig('payment/boltpay/publishable_key_multipage');
        $actualPaymentOnlyKey = Mage::app()->getStore()->getConfig('payment/boltpay/publishable_key_onepage');

        $this->currentMock->getPublishableKeyForThisPage();
        $this->assertFalse($actualMultiStepKey || $actualPaymentOnlyKey, "No key should be configured: multi [$actualMultiStepKey], paymentOnly [$actualPaymentOnlyKey]");
        $this->assertEquals($multiStepKey, $actualMultiStepKey);
        $this->assertEquals($paymentOnlyKey, $actualPaymentOnlyKey);
    }

    /**
     * Provides data with no publishable keys in the context of the standard cart page, a custom cart page,
     * the standard checkout page, a standard product page, and any unexpected page identified by route and controller
     * name for {@see Bolt_Boltpay_Block_Checkout_BoltpayTest::getPublishableKeyForThisPage_whenNoKeyIsConfigured()}
     *
     * @return array[] in the format of [$multiStepKey, $paymentOnlyKey, $routeName, $controllerName]
     */
    public function getPublishableKeyForThisPage_whenNoKeyIsConfiguredProvider()
    {
        return array(
            "When no keys are configured, null multi-step and empty string payment-only, on standard cart page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, empty string multi-step and null payment-only, on standard cart page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => null,
                    "routeName" => "checkout",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, empty strings, on standard cart page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, nulls, on standard cart page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => null,
                    "routeName" => "checkout",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, null multi-step and empty string payment-only, on custom cart page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "",
                    "routeName" => "custom",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, empty string multi-step and null payment-only, on custom cart page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => null,
                    "routeName" => "custom",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, empty strings, on custom cart page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "",
                    "routeName" => "custom",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, nulls, on custom cart page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => null,
                    "routeName" => "custom",
                    "controllerName" => "cart"
                ),
            "When no keys are configured, null multi-step and empty string payment-only, on standard onepage" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "onepage"
                ),
            "When no keys are configured, empty string multi-step and null payment-only, on standard onepage" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => null,
                    "routeName" => "checkout",
                    "controllerName" => "onepage"
                ),
            "When no keys are configured, empty strings, on standard onepage" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "",
                    "routeName" => "checkout",
                    "controllerName" => "onepage"
                ),
            "When no keys are configured, nulls, on standard onepage" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => null,
                    "routeName" => "checkout",
                    "controllerName" => "onepage"
                ),
            "When no keys are configured, null multi-step and empty string payment-only, on unexpected page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "",
                    "routeName" => "cms",
                    "controllerName" => "index"
                ),
            "When no keys are configured, empty string multi-step and null payment-only, on unexpected page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => null,
                    "routeName" => "cms",
                    "controllerName" => "index"
                ),
            "When no keys are configured, empty strings, on unexpected page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "",
                    "routeName" => "cms",
                    "controllerName" => "index"
                ),
            "When no keys are configured, nulls, on unexpected page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => null,
                    "routeName" => "cms",
                    "controllerName" => "index"
                ),
            "When no keys are configured, null multi-step and empty string payment-only, on product page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => "",
                    "routeName" => "catalog",
                    "controllerName" => "product"
                ),
            "When no keys are configured, empty string multi-step and null payment-only, on product page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => null,
                    "routeName" => "catalog",
                    "controllerName" => "product"
                ),
            "When no keys are configured, empty strings, on product page" =>
                array(
                    "multiStepKey" => "",
                    "paymentOnlyKey" => "",
                    "routeName" => "catalog",
                    "controllerName" => "product"
                ),
            "When no keys are configured, nulls, on product page" =>
                array(
                    "multiStepKey" => null,
                    "paymentOnlyKey" => null,
                    "routeName" => "catalog",
                    "controllerName" => "product"
                )
        );
    }

    /**
     * @test
     * that getCartURL returns checkout cart Magento URL
     *
     * @covers ::getCartURL
     *
     * @throws Mage_Core_Model_Store_Exception if unable to get Magento url
     */
    public function getCartURL_always_returnsCheckoutCartMagentoUrl()
    {
        $expected = Mage::helper('boltpay')->getMagentoUrl('checkout/cart');

        $this->assertEquals($expected, $this->currentMock->getCartURL());
    }

    /**
     * @test
     * that getAdditionalJs returns value from store configuration
     *
     * @covers ::getAdditionalCSS
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getAdditionalCSS_always_returnsAdditionalCSSFromConfiguration()
    {
        $style = '.test-selector { color: red; }';

        TestHelper::stubConfigValue('payment/boltpay/additional_css', $style);

        $this->assertEquals($style, $this->currentMock->getAdditionalCSS());
    }

    /**
     * @test
     * that getAdditionalJs returns value from store configuration
     *
     * @covers ::getAdditionalJs
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getAdditionalJs_always_returnsValueFromConfiguration()
    {
        $js = 'jQuery("body div").text("Hello, world.")';

        TestHelper::stubConfigValue('payment/boltpay/additional_js', $js);

        $this->assertEquals($js, $this->currentMock->getAdditionalJs());
    }

    /**
     * @test
     * that getSuccessUrl returns Magento url based on successpage config value
     *
     * @covers ::getSuccessURL
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getSuccessURL_always_returnsMagentoUrlBasedOnSuccessPageConfigValue()
    {
        $url = 'checkout/onepage/success';
        TestHelper::stubConfigValue('payment/boltpay/successpage', $url);
        $this->assertEquals(Mage::helper('boltpay')->getMagentoUrl($url), $this->currentMock->getSuccessURL());
    }

    /**
     * Setup method for {@see Bolt_Boltpay_Block_Checkout_BoltpayTest::buildBoltCheckoutJavascript_withVariousConfigs_returnsBoltJs}
     *
     * @param string $checkoutType currently in use
     * @param bool   $cloneOnClick extra-config flag for cloneOnClick
     * @param bool   $isShoppingCartPage whether current page is checkout/cart
     *
     * @return array containing hint data, cart data, mock instance and quote mock
     * @throws Mage_Core_Exception if unable to stub helper
     * @throws Exception if test class name is not set
     */
    private function buildBoltCheckoutJavascript_withVariousConfigsSetUp($checkoutType, $cloneOnClick = false, $isShoppingCartPage = false)
    {
        $hintData = array(
            'signed_merchant_user_id' => array(
                "merchant_user_id" => self::RESERVED_CUSTOMER_ID,
                "signature"        => sha1('test'),
                "nonce"            => rand(100000000, 999999999),
            )
        );
        $cartData = Mage::getModel('boltpay/boltOrder')->getBoltOrderTokenPromise($checkoutType);
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        /** @var MockObject|Bolt_Boltpay_Block_Checkout_Boltpay $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('buildOnCheckCallback', 'buildOnSuccessCallback', 'buildOnCloseCallback'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        /** @var MockObject|Mage_Sales_Model_Quote $quoteMock */
        $quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')->getMock();
        $quoteMock->method('getId')->willReturn(self::QUOTE_ID);

        $boltCallbacksDummyValue = '/*BOLT-CALLBACKS*/';
        $this->boltHelperMock->expects($this->once())->method('getBoltCallbacks')->with($checkoutType, $quoteMock)
            ->willReturn($boltCallbacksDummyValue);
        $this->boltHelperMock->expects($this->once())->method('getPaymentBoltpayConfig')->with('check', $checkoutType)
            ->willReturn(self::CHECK_FUNCTION);
        $this->boltHelperMock->expects($this->once())->method('buildOnCheckCallback')->with($checkoutType, $quoteMock)
            ->willReturn(self::ON_CHECK_CALLBACK);
        $this->boltHelperMock->method('getExtraConfig')->willReturnMap(
            array(
                array('hintsTransform', array(), self::HINTS_TRANSFORM_FUNCTION),
                array('cloneOnClick', array(), $cloneOnClick)
            )
        );
        $this->boltHelperMock->method('isShoppingCartPage')->willReturn($isShoppingCartPage);
        return array($hintData, $cartData, $currentMock, $quoteMock);
    }

    /**
     * @test
     * that buildBoltCheckoutJavascript returns normal or postponed configuration depending on parameters
     *
     * @covers ::buildBoltCheckoutJavascript
     *
     * @dataProvider buildBoltCheckoutJavascript_withVariousConfigsProvider
     *
     * @param string $checkoutType currently in use
     * @param bool   $isShoppingCartPage whether current page is checkout/cart
     * @param bool   $shouldCloneImmediately inverse of extra-config flag for cloneOnClick
     * @param bool   $expectPostponedConfiguration is postponed or normal configuration expected
     *
     * @throws Mage_Core_Exception from test setup if unable to stub helper
     */
    public function buildBoltCheckoutJavascript_withVariousConfigs_returnsBoltJs($checkoutType, $isShoppingCartPage, $shouldCloneImmediately, $expectPostponedConfiguration)
    {
        list($hintData, $cartData, $currentMock, $quoteMock) = $this->buildBoltCheckoutJavascript_withVariousConfigsSetUp(
            $checkoutType,
            !$shouldCloneImmediately,
            $isShoppingCartPage
        );
        $this->boltHelperMock->expects($this->once())->method('doFilterEvent')
            ->with(
                'bolt_boltpay_filter_bolt_checkout_javascript',
                $this->callback(
                    function ($js) use ($cartData, $hintData, $expectPostponedConfiguration) {
                        $this->assertContains(
                            sprintf('var $hints_transform = %s;', self::HINTS_TRANSFORM_FUNCTION),
                            $js
                        );
                        $this->assertContains(
                            sprintf(
                                'var get_json_cart = function() { return %s };',
                                (is_string($cartData)) ? $cartData : json_encode($cartData)
                            ),
                            $js
                        );
                        $this->assertContains(
                            sprintf(
                                'var json_hints = $hints_transform(%s);',
                                json_encode($hintData, JSON_FORCE_OBJECT)
                            ),
                            $js
                        );
                        $this->assertContains(sprintf("var quote_id = '%s';", self::QUOTE_ID), $js);
                        $this->assertContains('var order_completed = false;', $js);
                        $this->assertContains(sprintf('var do_checks = %d;', !$expectPostponedConfiguration), $js);
                        $windowBoltModal = explode('window.BoltModal = ', $js)[1];
                        $this->assertContains(
                            'BoltCheckout.configure(get_json_cart(),json_hints,/*BOLT-CALLBACKS*/);',
                            preg_replace('/\s*/', '', $windowBoltModal)
                        );

                        if ($expectPostponedConfiguration) {
                            $this->assertRegExp(
                                /** @lang PhpRegExp */ '/BoltCheckout\.configure\(\s*new Promise/',
                                $windowBoltModal
                            );
                            $this->assertRegExp(
                                sprintf(
                                    /** @lang PhpRegExp */ '/\{\s*check: function\(\) \{\s*%s\s*%s/',
                                    preg_quote(self::CHECK_FUNCTION),
                                    preg_quote(self::ON_CHECK_CALLBACK)
                                ),
                                $windowBoltModal
                            );
                        }

                        return true;
                    }
                ),
                array(
                    'checkoutType' => $checkoutType,
                    'quote'        => $quoteMock,
                    'hintData'     => $hintData,
                    'cartData'     => $cartData
                )
            );

        $currentMock->buildBoltCheckoutJavascript($checkoutType, $quoteMock, $hintData, $cartData);
    }

    /**
     * Data provider for {@see buildBoltCheckoutJavascript_withVariousConfigs_returnsBoltJs}
     *
     * @return array containing checkout type, is current page cart flag, should clone immediately config and flag whether to expect postponed configuration
     */
    public function buildBoltCheckoutJavascript_withVariousConfigsProvider()
    {
        return array(
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE,
                'isShoppingCartPage'           => true,
                'shouldCloneImmediately'       => true,
                'expectPostponedConfiguration' => false
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE,
                'isShoppingCartPage'           => true,
                'shouldCloneImmediately'       => false,
                'expectPostponedConfiguration' => true
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE,
                'isShoppingCartPage'           => false,
                'shouldCloneImmediately'       => false,
                'expectPostponedConfiguration' => true
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE,
                'isShoppingCartPage'           => true,
                'shouldCloneImmediately'       => true,
                'expectPostponedConfiguration' => false
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE,
                'isShoppingCartPage'           => true,
                'shouldCloneImmediately'       => false,
                'expectPostponedConfiguration' => true
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN,
                'isShoppingCartPage'           => false,
                'shouldCloneImmediately'       => false,
                'expectPostponedConfiguration' => true
            ),
            array(
                'checkoutType'                 => BoltpayCheckoutBlock::CHECKOUT_TYPE_FIRECHECKOUT,
                'isShoppingCartPage'           => false,
                'shouldCloneImmediately'       => false,
                'expectPostponedConfiguration' => true
            ),
        );
    }

    /**
     * @test
     * that getSaveOrderURL returns save order URL
     *
     * @covers ::getSaveOrderURL
     *
     * @throws Mage_Core_Model_Store_Exception if unable to get Magento url
     */
    public function getSaveOrderURL_always_returnsBoltSaveOrderUrl()
    {
        $expected = Mage::helper('boltpay')->getMagentoUrl('boltpay/order/save');

        $result = $this->currentMock->getSaveOrderURL();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * that isTestMode returns flag value from configuration for test field
     *
     * @covers ::isTestMode
     *
     * @dataProvider isTestMode_always_termsIfTestModeIsSetInConfigurationProvider
     *
     * @param mixed $isTestModeConfig config value for_test_mode
     * @param bool  $expectedResult of the method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function isTestMode_always_termsIfTestModeIsSetInConfiguration($isTestModeConfig, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/test', $isTestModeConfig);
        $result = $this->currentMock->isTestMode();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see isTestMode_always_termsIfTestModeIsSetInConfiguration}
     *
     * @return array[] containing configuration value for test and expected result of the method call
     */
    public function isTestMode_always_termsIfTestModeIsSetInConfigurationProvider()
    {
        return array(
            'Empty value should return false'   => array('isTestModeConfig' => '', 'expectedResult' => false),
            'Zero should return false'          => array('isTestModeConfig' => '0', 'expectedResult' => false),
            'Null should return false'          => array('isTestModeConfig' => null, 'expectedResult' => false),
            'Boolean false should return false' => array('isTestModeConfig' => 'false', 'expectedResult' => false),
            'String false should return false'  => array('isTestModeConfig' => false, 'expectedResult' => false),
            'String true should return true'    => array('isTestModeConfig' => 'true', 'expectedResult' => true),
            'String one should return true'     => array('isTestModeConfig' => '1', 'expectedResult' => true),
            'Integer one should return true'    => array('isTestModeConfig' => 1, 'expectedResult' => true),
        );
    }

    /**
     * @test
     * that getConfigSelectors returns config value for selectors in JSON format
     *
     * @dataProvider getConfigSelectors_withVariousConfigsProvider
     *
     * @param string $selectors configuration value for selectors
     * @param string $expectedResult of the method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function getConfigSelectors_withVariousConfigs_returnsJsonSelectors($selectors, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/selectors', $selectors);
        $result = $this->currentMock->getConfigSelectors();
        $this->assertJson($result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see getConfigSelectors_withVariousConfigs_returnsJsonSelectors}
     *
     * @return string[][] containing configuration value for selectors and expected result of the method call
     */
    public function getConfigSelectors_withVariousConfigsProvider()
    {
        return array(
            'Empty config returns empty JSON array' => array(
                'selectors' => '',
                'expect'    => '[]',
            ),
            'Single selector returns JSON array'    => array(
                'selectors' => '.btn',
                'expect'    => '[".btn"]',
            ),
            'Multiple selectors returns JSON array' => array(
                'selectors' => '.btn, div.checkout',
                'expect'    => '[".btn"," div.checkout"]',
            ),
        );
    }

    /**
     * @test
     * that isBoltOnlyPayment returns config value for payment/boltpay/skip_payment config path
     *
     * @covers ::isBoltOnlyPayment
     *
     * @dataProvider isBoltOnlyPayment_withVariousConfigsProvider
     *
     * @param mixed $skipPayment value in configuration for skip payment
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function isBoltOnlyPayment_withVariousConfigs_termsIsBoltOnlyPayment($skipPayment)
    {
        TestHelper::stubConfigValue('payment/boltpay/skip_payment', $skipPayment);
        $this->assertSame($skipPayment, $this->currentMock->isBoltOnlyPayment());
    }

    /**
     * Data provider for {@see isBoltOnlyPayment_withVariousConfigs_termsIsBoltOnlyPayment}
     *
     * @return array[] containing various configuration values for skip_payment
     */
    public function isBoltOnlyPayment_withVariousConfigsProvider()
    {
        return array(
            array('skipPayment' => 1),
            array('skipPayment' => 0),
            array('skipPayment' => '1'),
            array('skipPayment' => '0'),
        );
    }

    /**
     * @test
     * that isCustomerGroupDisabled checks for presence for provided customer group id in disabled groups config
     *
     * @covers ::isCustomerGroupDisabled
     *
     * @dataProvider isCustomerGroupDisabled_withVariousConfigsProvider
     *
     * @param int    $customerGroupId dummy customer group id
     * @param string $disabledCustomerGroupIds configuration value for disabled customer groups
     * @param bool   $expectedResult of method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     * @throws ReflectionException if class tested doesn't have isCustomerGroupDisabled method
     */
    public function isCustomerGroupDisabled_withVariousConfigs_termsIsCustomerGroupIsDisabled($customerGroupId, $disabledCustomerGroupIds, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/bolt_disabled_customer_groups', $disabledCustomerGroupIds);
        $result = TestHelper::callNonPublicFunction(
            $this->currentMock,
            'isCustomerGroupDisabled',
            array($customerGroupId)
        );
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for (@see isCustomerGroupDisabled_withVariousConfigs_termsIsCustomerGroupIsDisabled}
     *
     * @return array[] containing dummy customer group, disabled customer group ids and expected result of method call
     */
    public function isCustomerGroupDisabled_withVariousConfigsProvider()
    {
        return array(
            'Empty disabled customer group ids config - should return false'                       => array(
                'customerGroupId'          => 0,
                'disabledCustomerGroupIds' => '',
                'expectedResult'           => false,
            ),
            'Zero for disabled customer group ids - should return false'                           => array(
                'customerGroupId'          => 0,
                'disabledCustomerGroupIds' => '0',
                'expectedResult'           => false,
            ),
            'General customer group with empty disabled customer groups - should return false'     => array(
                'customerGroupId'          => 1,
                'disabledCustomerGroupIds' => '',
                'expectedResult'           => false,
            ),
            'General customer group with group id in disabled ids - should return true'            => array(
                'customerGroupId'          => 1,
                'disabledCustomerGroupIds' => '1,2',
                'expectedResult'           => true,
            ),
            'Not logged in customer group with group id not in disabled ids - should return false' => array(
                'customerGroupId'          => 0,
                'disabledCustomerGroupIds' => '1,2',
                'expectedResult'           => false,
            ),
        );
    }

    /**
     * @test
     * that isBoltActive returns config flag value for payment/boltpay/active
     *
     * @covers ::isBoltActive
     *
     * @dataProvider isBoltActive_withVariousConfigsProvider
     *
     * @param mixed $activeConfig configuration value
     * @param book  $expectedResult returned from method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub configuration
     */
    public function isBoltActive_withVariousConfigs_termsIfBoltModuleShouldBeActive($activeConfig, $expectedResult)
    {
        TestHelper::stubConfigValue('payment/boltpay/active', $activeConfig);
        $result = $this->currentMock->isBoltActive();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Data provider for {@see isBoltActive_withVariousConfigs_termsIfBoltModuleShouldBeActive}
     *
     * @return mixed[][] containing configuration value for Bolt module and expected result
     */
    public function isBoltActive_withVariousConfigsProvider()
    {
        return array(
            'Empty value should return false'   => array('activeConfig' => '', 'expectedResult' => false),
            'Zero should return false'          => array('activeConfig' => '0', 'expectedResult' => false),
            'Null should return false'          => array('activeConfig' => null, 'expectedResult' => false),
            'Boolean false should return false' => array('activeConfig' => 'false', 'expectedResult' => false),
            'String false should return false'  => array('activeConfig' => false, 'expectedResult' => false),
            'String true should return true'    => array('activeConfig' => 'true', 'expectedResult' => true),
            'String one should return true'     => array('activeConfig' => '1', 'expectedResult' => true),
            'Integer one should return true'    => array('activeConfig' => 1, 'expectedResult' => true),
        );
    }

    /**
     * @test
     * that isEnableMerchantScopedAccount returns configuration value for payment/boltpay/enable_merchant_scoped_account
     *
     * @covers ::isEnableMerchantScopedAccount
     *
     * @dataProvider isEnableMerchantScopedAccount_withVariousConfigsProvider
     *
     * @param mixed $merchantEnabledConfig configuration value
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function isEnableMerchantScopedAccount_withVariousConfigs_termsIfMerchantScopedAccountIsEnabled($merchantEnabledConfig)
    {
        TestHelper::stubConfigValue('payment/boltpay/enable_merchant_scoped_account', $merchantEnabledConfig);
        $result = $this->currentMock->isEnableMerchantScopedAccount();
        $this->assertEquals($merchantEnabledConfig, $result);
    }

    /**
     * Data provider for {@see isEnableMerchantScopedAccount_withVariousConfigs_termsIfMerchantScopedAccountIsEnabled}
     *
     * @return mixed[][] containing configuration values for isEnableMerchantScopedAccount
     */
    public function isEnableMerchantScopedAccount_withVariousConfigsProvider()
    {
        return array(
            array('merchantEnabledConfig' => '1'),
            array('merchantEnabledConfig' => '0'),
            array('merchantEnabledConfig' => 'false'),
            array('merchantEnabledConfig' => 'true'),
            array('merchantEnabledConfig' => null),
            array('merchantEnabledConfig' => true),
            array('merchantEnabledConfig' => false),
        );
    }

    /**
     * @test
     * that getQuote returns quote from checkout/session singleton
     *
     * @coves ::getQuote
     */
    public function getQuote_always_returnsQuoteFromCheckoutSession()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $this->assertEquals($quote, $this->currentMock->getQuote());
    }

    /**
     * @test
     * that canUseBolt returns result of {@see Bolt_Boltpay_Helper_Data::canUseBolt} method call
     * provided with quote from session and false for checkCountry parameter
     *
     * @covers ::canUseBolt
     *
     * @dataProvider canUseBolt_always_termsIfBoltCanBeUsedProvider
     *
     * @param bool $canUseBolt stubbed result of Bolt helper method call
     *
     * @throws Mage_Core_Exception if unable to stub Bolt helper
     * @throws Mage_Core_Model_Store_Exception from method tested if store is undefined
     */
    public function canUseBolt_always_termsIfBoltCanBeUsed($canUseBolt)
    {
        TestHelper::stubHelper('boltpay', $this->boltHelperMock);
        $this->boltHelperMock->expects($this->once())->method('canUseBolt')
            ->with($this->currentMock->getQuote(), false)->willReturn($canUseBolt);
        $this->assertEquals($canUseBolt, $this->currentMock->canUseBolt());
    }

    /**
     * Data provider for {@see canUseBolt_always_termsIfBoltCanBeUsed}
     *
     * @return array containing possible results of helper method call
     */
    public function canUseBolt_always_termsIfBoltCanBeUsedProvider()
    {
        return array(
            'Bolt enabled'  => array('canUseBolt' => true),
            'Bolt disabled' => array('canUseBolt' => false),
        );
    }

    /**
     * @test
     * that Connect JS is allowed for current page only if current route is in custom routes configuration
     * or one of the following:
     * checkout/cart, firecheckout, catalog/product, adminhtml/sales_order_create and adminhtml/sales_order_edit
     *
     * @covers ::isAllowedOnCurrentPageByRoute
     *
     * @dataProvider isAllowedOnCurrentPageByRoute_withVariousConfigsProvider
     *
     * @param string $route current Magento route
     * @param string $controller current Magento controller
     * @param bool   $isEnabledPDP configuration if Bolt should be enabled on product details page
     * @param string $customRoutes configuration value containing custom routes for which Bolt should be enabled
     * @param bool   $expectedResult of the method call
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     * @throws ReflectionException if isAllowedOnCurrentPageByRoute method doesn't exist
     */
    public function isAllowedOnCurrentPageByRoute_withVariousConfigs_returnsExpectedResult($route, $controller, $isEnabledPDP, $customRoutes, $expectedResult)
    {
        Mage::app()->getRequest()->setRouteName($route);
        Mage::app()->getRequest()->setControllerName($controller);
        TestHelper::stubConfigValue('payment/boltpay/enable_product_page_checkout', $isEnabledPDP);
        TestHelper::stubConfigValue('payment/boltpay/allowed_button_by_custom_routes', $customRoutes);
        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'isAllowedOnCurrentPageByRoute'
            )
        );
    }

    /**
     * Data provider for {@see isAllowedOnCurrentPageByRoute_withVariousConfigs_returnsExpectedResult}
     *
     * @return array containing route, controller, isEnabledPDP, customRoutes and expectedResult
     */
    public function isAllowedOnCurrentPageByRoute_withVariousConfigsProvider()
    {
        return array(
            'Route in custom routes'                     => array(
                'route'          => 'custom_route',
                'controller'     => '',
                'isEnabledPDP'   => false,
                'customRoutes'   => ' custom_route ',
                'expectedResult' => true
            ),
            'Route in multiple custom routes'            => array(
                'route'          => 'another_custom_route',
                'controller'     => '',
                'isEnabledPDP'   => false,
                'customRoutes'   => ' custom_route ,another_custom_route ',
                'expectedResult' => true
            ),
            'Checkout cart'                              => array(
                'route'          => 'checkout',
                'controller'     => 'cart',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => true
            ),
            'Firecheckout'                               => array(
                'route'          => 'firecheckout',
                'controller'     => '',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => true
            ),
            'Catalog product with PDP checkout enabled'  => array(
                'route'          => 'catalog',
                'controller'     => 'product',
                'isEnabledPDP'   => true,
                'customRoutes'   => false,
                'expectedResult' => true
            ),
            'Catalog product with PDP checkout disabled' => array(
                'route'          => 'catalog',
                'controller'     => 'product',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => false
            ),
            'Admin sales order create'                   => array(
                'route'          => 'adminhtml',
                'controller'     => 'sales_order_create',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => true
            ),
            'Admin sales order edit'                     => array(
                'route'          => 'adminhtml',
                'controller'     => 'sales_order_edit',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => true
            ),
            'Admin customer edit'                        => array(
                'route'          => 'adminhtml',
                'controller'     => 'customer_edit',
                'isEnabledPDP'   => false,
                'customRoutes'   => false,
                'expectedResult' => false
            ),
        );
    }

    /**
     * @test
     * that isAllowedConnectJsOnCurrentPage returns true only if current customer group is not configured to be disabled
     * and current page is allowed by route, or everywhere config is enabled
     *
     * @dataProvider isAllowedConnectJsOnCurrentPage_withVariousConfigsProvider
     *
     * @covers ::isAllowedConnectJsOnCurrentPage
     * @covers ::isCustomerGroupDisabled
     * @covers ::isAllowedOnCurrentPageByRoute
     *
     * @param bool   $expected result of the method call
     * @param bool   $active configuration value for Bolt
     * @param int    $customerGroupId current customer group id
     * @param string $groups configuration value for disabled customer groups
     * @param string $route current Magento route
     * @param string $controller current Magento controller
     * @param bool   $everywhere configuration value for add_button_everywhere
     *
     * @throws Mage_Core_Model_Store_Exception if unable to stub config value
     */
    public function isAllowedConnectJsOnCurrentPage_withVariousConfigs_termsIfConnectJsIsAllowedOnCurrentPage($expected, $active, $customerGroupId, $groups, $route, $controller, $everywhere)
    {
        $quote = $this->currentMock->getQuote();
        $quote->setCustomerGroupId($customerGroupId);

        Mage::app()->getRequest()->setRouteName($route)->setControllerName($controller);

        TestHelper::stubConfigValue('payment/boltpay/bolt_disabled_customer_groups', $groups);
        TestHelper::stubConfigValue('payment/boltpay/active', $active);
        TestHelper::stubConfigValue('payment/boltpay/add_button_everywhere', $everywhere);

        $result = $this->currentMock->isAllowedConnectJsOnCurrentPage();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for {@see isAllowedConnectJsOnCurrentPage_withVariousConfigs_termsIfConnectJsIsAllowedOnCurrentPage}
     * Provides data sets to verify that {@see BoltpayCheckoutBlock::isAllowedConnectJsOnCurrentPage}} returns false
     * when current customer group id is in disabled customer groups configuration
     *
     * @return array containing ($expected, $active, $customerGroupId, $groups, $route, $controller, $everywhere)
     */
    public function isAllowedConnectJsOnCurrentPage_withVariousConfigsProvider()
    {
        return array(
            'Checkout cart when button everywhere enabled should return true'                         => array(
                'expected'        => true,
                'active'          => true,
                'customerGroupId' => 0,
                'groups'          => '',
                'route'           => 'checkout',
                'controller'      => 'cart',
                'everywhere'      => true
            ),
            'Checkout cart when button everywhere disabled should return true'                        => array(
                'expected'        => true,
                'active'          => true,
                'customerGroupId' => 0,
                'groups'          => '',
                'route'           => 'checkout',
                'controller'      => 'cart',
                'everywhere'      => false
            ),
            'Checkout cart when customer group is not in disabled customer groups should return true' => array(
                'expected'        => true,
                'active'          => true,
                'customerGroupId' => 0,
                'groups'          => '1,2,3',
                'route'           => 'checkout',
                'controller'      => 'cart',
                'everywhere'      => false
            ),
            'Checkout cart when customer group is in disabled customer groups should return false'    => array(
                'expected'        => false,
                'active'          => true,
                'customerGroupId' => 1,
                'groups'          => '1,2,3',
                'route'           => 'checkout',
                'controller'      => 'cart',
                'everywhere'      => false
            ),
        );
    }

    /**
     * @test
     * that isAllowedReplaceScriptOnCurrentPage returns expected output with provided route
     * and stubbed result of isAllowedConnectJsOnCurrentPage method call
     *
     * @covers ::isAllowedReplaceScriptOnCurrentPage
     *
     * @dataProvider isAllowedReplaceScriptOnCurrentPage_withVariousConfigsProvider
     *
     * @param string $route request route
     * @param bool   $isAllowedConnectJsOnCurrentPage stubbed result of isAllowedConnectJsOnCurrentPage method call
     * @param bool   $expectedResult of the method call
     *
     * @throws Exception if test class name is not defined
     */
    public function isAllowedReplaceScriptOnCurrentPage_withVariousConfigs_returnsExpectedOutput($route, $isAllowedConnectJsOnCurrentPage, $expectedResult)
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('isAllowedConnectJsOnCurrentPage'))->getMock();
        $currentMock->method('isAllowedConnectJsOnCurrentPage')->willReturn($isAllowedConnectJsOnCurrentPage);
        $currentMock->getRequest()->setRouteName($route);
        $this->assertEquals($expectedResult, $currentMock->isAllowedReplaceScriptOnCurrentPage());
    }

    /**
     * Data provider for {@see isAllowedReplaceScriptOnCurrentPage_withVariousConfigs_returnsExpectedOutput}
     *
     * @return array containing route, stubbed result of isAllowedConnectJsOnCurrentPage and expectedResult
     */
    public function isAllowedReplaceScriptOnCurrentPage_withVariousConfigsProvider()
    {
        return array(
            'Firecheckout and allowed on current page'       => array(
                'route'                           => 'firecheckout',
                'isAllowedConnectJsOnCurrentPage' => true,
                'expectedResult'                  => false
            ),
            'Checkout route and allowed on current page'     => array(
                'route'                           => 'checkout',
                'isAllowedConnectJsOnCurrentPage' => true,
                'expectedResult'                  => true
            ),
            'Checkout route and not allowed on current page' => array(
                'route'                           => 'checkout',
                'isAllowedConnectJsOnCurrentPage' => false,
                'expectedResult'                  => false
            ),
            'Firecheckout and not allowed on current page'   => array(
                'route'                           => 'firecheckout',
                'isAllowedConnectJsOnCurrentPage' => false,
                'expectedResult'                  => false
            ),
        );
    }

    /**
     * @test
     * that getSessionQuote returns quote from session object retrieved using checkout type
     *
     * @covers ::getSessionQuote
     *
     * @dataProvider getSessionQuote_withVariousCheckoutTypesProvider
     *
     * @param string $checkoutType to be used to retrieve session object
     *
     * @throws Exception if test class name is not defined
     */
    public function getSessionQuote_withVariousCheckoutTypes_returnsQuoteFromSessionObject($checkoutType)
    {
        $session = $this->getClassPrototype('Mage_Checkout_Model_Session')->getMock();
        $quote = Mage::getModel('sales/quote');
        $session->expects($this->once())->method('getQuote')->willReturn($quote);
        /** @var MockObject|BoltpayCheckoutBlock $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getSessionObject'))->getMock();
        $currentMock->expects($this->once())->method('getSessionObject')->willReturn($session);
        $this->assertSame($quote, $currentMock->getSessionQuote($checkoutType));
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_Block_Checkout_Boltpay::getSessionQuote}
     *
     * @return string[][] containing every checkout type
     */
    public function getSessionQuote_withVariousCheckoutTypesProvider()
    {
        return array(
            'Multi-page checkout' => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_MULTI_PAGE),
            'Firecheckout'        => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_FIRECHECKOUT),
            'One-page checkout'   => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ONE_PAGE),
            'Product page'        => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_PRODUCT_PAGE),
            'Admin'               => array('checkoutType' => BoltpayCheckoutBlock::CHECKOUT_TYPE_ADMIN),
        );
    }
}
