<?php
require_once('TestHelper.php');
require_once('CouponHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_GeneralTrait
 */
class Bolt_Boltpay_Helper_GeneralTraitTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var int|null Dummy product id
     */
    private static $productId = null;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_GeneralTrait Mocked instance of trait tested
     */
    private $currentMock;
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Validator Mocked instance of sales rule validator
     */
    private $salesRuleValidatorMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Sales_Model_Quote Mocked instance of sales quote model
     */
    private $quoteMock;

    /**
     * @var Bolt_Boltpay_TestHelper Instance of test helper
     */
    private $testHelper;

    /**
     * @var MockObject|Bolt_Boltpay_Model_FeatureSwitch
     */
    private $featureSwitchMock;

    /**
     * Create dummy products and unregister objects we are going to mock
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
        Mage::unregister('_singleton/salesrule/validator');
    }

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_GeneralTrait')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMockForTrait();

        $this->testHelper = new Bolt_Boltpay_TestHelper();

        $this->salesRuleValidatorMock = $this->getMockBuilder('Bolt_Boltpay_Model_Validator')
            ->setMethods(array('resetRoundingDeltas'))
            ->getMock();

        $this->quoteMock = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(array('setTotalsCollectedFlag', 'getShippingAddress', 'unsetData', 'collectTotals'))
            ->getMock();
        $this->quoteMock->method('getShippingAddress')->willReturnSelf();

        Mage::register('_singleton/salesrule/validator', $this->salesRuleValidatorMock);
    }

    /**
     * Cleanup registry
     */
    protected function tearDown()
    {
        Mage::unregister('_singleton/salesrule/validator');
        Mage::unregister('_singleton/checkout/type_onepage');
        Mage::unregister('_singleton/checkout/cart');
        Mage::app()->getStore()->resetConfig();
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            Mage::app(),
            '_request',
            null
        );
    }

    /**
     * Delete dummy product
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
        Mage::getSingleton('checkout/cart')->truncate()->save();
        Mage::unregister('controller');
    }

    /**
     * @test
     * that canUseBolt returns false when module status is disabled in configuration
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenDisabledInConfiguration_returnsFalse()
    {
        Mage::app()->getStore()->setConfig('payment/boltpay/active', 0);
        $quote = $this->testHelper->getCheckoutQuote();

        $this->assertFalse($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * that canUseBolt returns true when enabled and Bolt is configured to be the only payment
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenSkipPaymentAndModuleEnabledInConfiguration_returnsTrue()
    {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/active', 1);
        $store->setConfig('payment/boltpay/skip_payment', 1);
        $this->testHelper->createCheckout('guest');
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $quote = $cart->getQuote();

        $this->assertTrue($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * that canUseBolt returns false when billing country is not whitelisted
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenBillingCountryIsNotWhitelisted_returnsFalse()
    {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/active', 1);
        $store->setConfig('payment/boltpay/allowspecific', 1);
        $store->setConfig('payment/boltpay/skip_payment', 0);
        $store->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $quote->getBillingAddress()->setCountryId('US');

        $this->assertFalse($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * Getting module status when enabled and billing country is whitelisted
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenBillingCountryIsWhitelisted_returnsTrue()
    {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/active', 1);
        $store->setConfig('payment/boltpay/allowspecific', 1);
        $store->setConfig('payment/boltpay/specificcountry', 'CA,US,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $quote->getBillingAddress()->setCountryId('US');

        $this->assertTrue($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * that canUseBolt returns true when skip payment is enabled and billing country is not whitelisted
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenSkipPaymentIsEnabledAndBillingCountryIsNotWhitelisted_returnsTrue()
    {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/active', 1);
        $store->setConfig('payment/boltpay/skip_payment', 1);
        $store->setConfig('payment/boltpay/allowspecific', 1);
        $store->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $quote->getBillingAddress()->setCountryId('US');

        $this->assertTrue($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * that canUseBolt returns true when billing country is not limited even though billing country is not whitelisted
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public function canUseBolt_whenFilteringSpecificCountriesIsDisabled_returnsTrue()
    {
        $store = Mage::app()->getStore();
        $store->setConfig('payment/boltpay/active', 1);
        $store->setConfig('payment/boltpay/allowspecific', 0);
        $store->setConfig('payment/boltpay/specificcountry', 'CA,UK');
        $this->testHelper->createCheckout('guest');
        $this->testHelper->addTestBillingAddress();
        $cart = $this->testHelper->addProduct(self::$productId, 2);
        $quote = $cart->getQuote();
        $quote->getBillingAddress()->setCountryId('US');

        $this->assertTrue($this->currentMock->canUseBolt($quote));
    }

    /**
     * @test
     * that canUseBolt returns true when request is from webhook
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::canUseBolt
     */
    public function canUseBolt_whenRequestIsComingFromWebhook_returnsTrue()
    {
        $quote = Mage::getModel('sales/quote');
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'fromHooks',
            true
        );

        $this->assertTrue($this->currentMock->canUseBolt($quote));
        $quote->delete();
    }

    /**
     * @test
     * that collectTotals triggers quote collect totals with clearing totals collected flag and shipping address cache
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::collectTotals
     */
    public function collectTotals_whenClearTotalsFlagIsTrue_collectTotalsWithClearingCache()
    {
        $this->salesRuleValidatorMock->expects($this->once())->method('resetRoundingDeltas');

        $this->quoteMock->expects($this->once())->method('setTotalsCollectedFlag')->with(false);
        $this->quoteMock->expects($this->exactly(3))->method('unsetData')
            ->withConsecutive(
                array('cached_items_all'),
                array('cached_items_nominal'),
                array('cached_items_nonnominal')
            );
        $this->quoteMock->expects($this->once())->method('collectTotals');

        $this->assertEquals(
            $this->quoteMock,
            $this->currentMock->collectTotals($this->quoteMock, true)
        );
    }

    /**
     * @test
     * that collectTotals triggers quote collect totals without clearing totals collected flag and shipping address cache
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::collectTotals
     */
    public function collectTotals_whenClearTotalsFlagIsFalse_collectTotalsWithoutClearingCache()
    {
        $this->quoteMock->expects($this->never())->method('setTotalsCollectedFlag');
        $this->quoteMock->expects($this->never())->method('unsetData');
        $this->quoteMock->expects($this->once())->method('collectTotals');
        $this->assertEquals(
            $this->quoteMock,
            $this->currentMock->collectTotals($this->quoteMock, false)
        );
    }

    /**
     * @test
     * that getItemImageUrl on order/quote item returns thumbnail image url
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::getItemImageUrl
     */
    public function getItemImageUrl_onQuoteItem_returnsThumbnailUrl()
    {
        $quoteItem = Mage::getModel('sales/quote_item');
        $quoteItem->setData('sku', 'test');
        $productMock = $this->getClassPrototype('Mage_Catalog_Model_Product')
            ->setMethods(array('load', 'getIdBySku', 'getThumbnail'))
            ->getMock();
        $productMock->method('load')->willReturnSelf();
        $productMock->method('getIdBySku')->willReturn(1);
        $productMock->method('getThumbnail')->willReturn('/t/e/test.jpg');
        TestHelper::stubModel('catalog/product', $productMock);
        $this->assertStringStartsWith('http', $this->currentMock->getItemImageUrl($quoteItem));
        TestHelper::restoreModel('catalog/product');
    }

    /**
     * @test
     * that getItemImageUrl on order/quote item returns empty string when image helper throws exception
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::getItemImageUrl
     *
     * @throws Mage_Core_Exception if registry key already exists
     * @throws ReflectionException if Mage class doesn't have _config property
     */
    public function getItemImageUrl_whenImageHelperThrowsException_returnsEmptyString()
    {
        $imageHelperMock = $this->getMockBuilder('Mage_Catalog_Helper_Image')
            ->setMethods(array('init'))
            ->getMock();
        $imageHelperMock->method('init')->willThrowException(new Exception());
        Mage::unregister('_helper/catalog/image');
        Mage::register('_helper/catalog/image', $imageHelperMock);

        $quoteItem = Mage::getModel('sales/quote_item');
        $productMock = $this->getClassPrototype('Mage_Catalog_Model_Product')
            ->setMethods(array('load', 'getIdBySku', 'getThumbnail'))
            ->getMock();
        $productMock->method('load')->willReturnSelf();
        $productMock->method('getIdBySku')->willReturn(1);
        $productMock->method('getThumbnail')->willReturn('/t/e/test.jpg');
        TestHelper::stubModel('catalog/product', $productMock);
        $this->assertEquals('', $this->currentMock->getItemImageUrl($quoteItem));
        TestHelper::restoreModel('catalog/product');
        Mage::unregister('_helper/catalog/image');
    }

    /**
     * @test
     * that setCustomerSessionByQuote id will login customer by id retrieved from quote
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::setCustomerSessionByQuoteId
     * @covers Bolt_Boltpay_Helper_GeneralTrait::setCustomerSessionById
     *
     * @throws Mage_Core_Exception if registry key already exists
     * @throws Zend_Db_Adapter_Exception if unable to delete dummy quote
     * @throws Exception if unable to create dummy quote
     */
    public function setCustomerSessionByQuoteId_whenQuoteContainsCustomerId_willLoginCustomerById()
    {
        $customerSessionMock = $this->getMockBuilder('Mage_Customer_Model_Session')
            ->setMethods(array('loginById'))
            ->getMock();
        Mage::unregister('_singleton/customer/session');
        Mage::register('_singleton/customer/session', $customerSessionMock);

        $customerId = 1;
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote(array('customer_id' => $customerId));

        $customerSessionMock->expects($this->once())->method('loginById')->with($customerId);

        $this->currentMock->setCustomerSessionByQuoteId($quoteId);

        Mage::unregister('_singleton/customer/session');
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * @test
     * Retrieving cart data js from checkout_boltpay block
     *
     * @dataProvider getCartDataJs_withVariousCheckoutTypes_returnsCartDataProvider
     * @covers       Bolt_Boltpay_Helper_GeneralTrait::getCartDataJs
     *
     * @param string $checkoutType Bolt parameter
     * @param string $configureCall expected Bolt configure call
     */
    public function getCartDataJs_withVariousCheckoutTypes_returnsCartData($checkoutType, $configureCall)
    {
        $cartDataJs = $this->currentMock->getCartDataJs($checkoutType);
        $this->assertContains('window.BoltModal', $cartDataJs);
        $this->assertContains($configureCall, $cartDataJs);
    }

    /**
     * Data provider for {@see getCartDataJs_withVariousCheckoutTypes_returnsCartData}
     *
     * @return array of checkout types
     */
    public function getCartDataJs_withVariousCheckoutTypes_returnsCartDataProvider()
    {
        return array(
            'Admin checkout type'        => array(
                'checkoutType'  => 'admin',
                'configureCall' => 'BoltCheckout.configure'
            ),
            'Multi-page checkout type'   => array(
                'checkoutType'  => 'multi-page',
                'configureCall' => 'BoltCheckout.configure'
            ),
            'One-page checkout type'     => array(
                'checkoutType'  => 'one-page',
                'configureCall' => 'BoltCheckout.configure'
            ),
            'Firecheckout type'          => array(
                'checkoutType'  => 'firecheckout',
                'configureCall' => 'BoltCheckout.configure'
            ),
            'Product page checkout type' => array(
                'checkoutType'  => 'product-page',
                'configureCall' => 'BoltCheckout.configureProductCheckout'
            ),
        );
    }

    /**
     * @test
     * that isShoppingCartPage correctly identifies shopping cart page based on route and controller name
     *
     * @dataProvider isShoppingCartPage_withVariousRoutes_returnsTrueOnlyForCartPageProvider
     * @covers       Bolt_Boltpay_Helper_GeneralTrait::isShoppingCartPage
     *
     * @param string $routeName to be set as current route name
     * @param string $controllerName to be set as current controller name
     * @param bool   $expectedResult of isShoppingCartPage method call
     */
    public function isShoppingCartPage_withVariousRoutes_returnsTrueOnlyForCartPage($routeName, $controllerName, $expectedResult)
    {
        $request = Mage::app()->getRequest();
        $request->setRouteName($routeName);
        $request->setControllerName($controllerName);

        $this->assertEquals(
            $expectedResult,
            $this->currentMock->isShoppingCartPage()
        );
    }

    /**
     * Data provider for {@see isShoppingCartPage_withVariousRoutes_returnsTrueOnlyForCartPage}
     *
     * @return array route name, controller name and expected result
     */
    public function isShoppingCartPage_withVariousRoutes_returnsTrueOnlyForCartPageProvider()
    {
        return array(
            'Checkout cart page'   => array(
                'routeName'      => 'checkout',
                'controllerName' => 'cart',
                'expectedResult' => true
            ),
            'Catalog product page' => array(
                'routeName'      => 'catalog',
                'controllerName' => 'product',
                'expectedResult' => false
            ),
            'Checkout page'        => array(
                'routeName'      => 'checkout',
                'controllerName' => 'index',
                'expectedResult' => false
            ),
        );
    }

    /**
     * @test
     * Dispatching filter event
     *
     * @dataProvider doFilterEvent_withVariousEvents_willDispatchEventProvider
     * @covers       Bolt_Boltpay_Helper_GeneralTrait::doFilterEvent
     * @covers       Bolt_Boltpay_Helper_GeneralTrait::dispatchFilterEvent
     *
     * @param string $eventName name of the dummy event
     * @param mixed  $valueToFilter of the dummy event
     * @param array  $additionalParameters of the dummy event
     * @throws ReflectionException if Mage doesn't have _app property
     */
    public function doFilterEvent_withVariousEvents_willDispatchEvent($eventName, $valueToFilter, $additionalParameters = array())
    {
        $appMock = $this->getMockBuilder('Mage_Core_Model_App')
            ->setMethods(array())
            ->getMock();

        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            'Mage',
            '_app',
            $appMock
        );

        $appMock->expects($this->once())->method('dispatchEvent')
            ->with(
                $eventName,
                array(
                    'value_wrapper' => (new Varien_Object())->setData(array('value' => $valueToFilter)),
                    'parameters'    => $additionalParameters
                )
            );

        $this->currentMock->doFilterEvent($eventName, $valueToFilter, $additionalParameters);

        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            'Mage',
            '_app',
            null
        );
    }

    /**
     * Data provider for {@see doFilterEvent_withVariousEvents_willDispatchEvent}
     *
     * @return array event name, value to filter and additional parameters
     */
    public function doFilterEvent_withVariousEvents_willDispatchEventProvider()
    {
        return array(
            array('bolt_boltpay_filter_shipping_label', 'Test Label', $rate = new stdClass()),
            array('bolt_boltpay_filter_success_url', 'https://bolt.com', array('order' => new stdClass(), 'quoteId' => -9999 )),
            array('new_parameterless_filter_1', 'value', array()),
            array('new_parameterless_filter_2', 'value', null),
            array('new_parameterless_filter_3', 'value')
        );
    }

    /**
     * @test
     * That when unserializeIntArray is provided with proper serialized data, then a populated array
     * is returned and that when the serialized data is in an improper format an empty array is returned
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::unserializeIntArray
     * @dataProvider unserializeIntArrayProvider
     *
     * @param string $serializedData        an array of data that is expected in the PHP serialize() format
     * @param array  $expectedReturnArray   an array represented the result of applying PHP unserialize to the passed string
     */
    public function unserializeIntArray_withVariousInputs_returnsAppropriateArray($serializedData, $expectedReturnArray) {
        $this->assertEquals(
            $expectedReturnArray,
            $this->currentMock->unserializeIntArray($serializedData)
        );
    }

    /**
     * Data provider for {@see unserializeIntArray_withVariousInputs_returnsAppropriateArray}
     *
     * @return array containing [$serializedData, $expectedReturnArray]
     */
    public function unserializeIntArrayProvider() {
        return array(
            array(
                'serializeData' => 'badFormat',
                'expectedReturnArray' => array()
            ),
            array(
                'serializeData' => 'a:3:{i:0;s:5:"larry";i:1;s:5:"curly";i:2;s:3:"moe";}', # string array
                'expectedReturnArray' => array()
            ),
            array(
                'serializeData' => 'a:5:{i:0;i:1;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:5;}', # int array
                'expectedReturnArray' => array(1,1,2,3,5)
            ),
        );
    }

    /**
     * @test
     * That when unserializeStringArray is provided with proper serialized data, then a populated array
     * is returned and that when the serialized data is in an improper format an empty array is returned
     *
     * @covers Bolt_Boltpay_Helper_GeneralTrait::unserializeStringArray
     * @dataProvider unserializeStringArrayProvider
     *
     * @param string $serializedData        an array of data that is expected in the PHP serialize() format
     * @param array  $expectedReturnArray   an array represented the result of applying PHP unserialize to the passed string
     */
    public function unserializeStringArray_withVariousInputs_returnsAppropriateArray($serializedData, $expectedReturnArray) {
        $this->assertEquals(
            $expectedReturnArray,
            $this->currentMock->unserializeStringArray($serializedData)
        );
    }

    /**
     * Data provider for {@see unserializeStringArray_withVariousInputs_returnsAppropriateArray}
     *
     * @return array containing [$serializedData, $expectedReturnArray]
     */
    public function unserializeStringArrayProvider() {
        return array(
            array(
                'serializeData' => 'badFormat',
                'expectedReturnArray' => array()
            ),
            array(
                'serializeData' => 'a:3:{i:0;s:5:"larry";i:1;s:5:"curly";i:2;s:3:"moe";}', # string array
                'expectedReturnArray' => array('larry','curly','moe')
            ),
            array(
                'serializeData' => 'a:5:{i:0;i:1;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:5;}', # int array
                'expectedReturnArray' => array()
            ),
        );
    }

    /**
     * SetUp for switch helper tests
     */
    private function isSwitchSetUp()
    {
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('isSwitchEnabled'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
    }

    /**
     * @test
     * When call isSwitchSampleSwitchEnabled Bolt_Boltpay_Model_FeatureSwitch::isSwitchEnables should be called with the right parameter
     *
     * @covers ::isSwitchSampleSwitchEnabled
     * @throws Exception
     */
    public function isSwitchSampleSwitchEnabled_shouldCallAppropriateMethodWithCorrectParameter()
    {
        $this->isSwitchSetUp();
        $this->featureSwitchMock->expects($this->once())->method('isSwitchEnabled')->with('M1_SAMPLE_SWITCH');
        $this->currentMock->isSwitchSampleSwitchEnabled();
        $this->isSwitchTearDown();
    }


    /**
     * When call isSwitchBoltEnabled Bolt_Boltpay_Model_FeatureSwitch::isSwitchEnables should be called with the right parameter
     *
     * @covers ::isSwitchSampleSwitchEnabled
     * @throws Exception
     */
    public function isSwitchBoltEnabled_shouldCallAppropriateMethodWithCorrectParameter()
    {
        $this->isSwitchSetUp();
        $this->featureSwitchMock->expects($this->once())->method('isSwitchEnabled')->with('M1_BOLT_ENABLED');
        $this->currentMock->isSwitchBoltEnabled();
        $this->isSwitchTearDown();
    }

    /**
     * TearDown for switch helper tests
     */
    private function isSwitchTearDown()
    {
        Bolt_Boltpay_TestHelper::restoreSingleton('boltpay/featureSwitch');
    }
}