<?php

require_once 'Bolt/Boltpay/controllers/ShippingController.php';

require_once 'TestHelper.php';
require_once 'MockingTrait.php';

use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class Bolt_Boltpay_ShippingControllerTest
 *
 * Test the shipping controller, particularly with shipping and tax estimates and caching
 *
 * @coversDefaultClass Bolt_Boltpay_ShippingController
 */
class Bolt_Boltpay_ShippingControllerTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int Dummy store id */
    const STORE_ID = 1;

    /** @var int Dummy quote id */
    const QUOTE_ID = 123;

    /** @var string Tested class name */
    protected $testClassName = 'Bolt_Boltpay_ShippingController';

    /**
     * @var Bolt_Boltpay_ShippingController|PHPUnit_Framework_MockObject_MockObject The stubbed shipping controller
     */
    private $_shippingController;

    /**
     * @var array ids of temporary products used for testing
     */
    private static $_productIds = array();

    /**
     * @var string  Used for storing $cacheBoltHeader in the header of response
     */
    private $_cacheBoltHeader;

    /**
     * @var Mage_Customer_Model_Customer
     */
    private $_customer;

    /**
     * @var MockObject|Bolt_Boltpay_ShippingController
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Core_Controller_Request_Http
     */
    private $requestMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_ShippingAndTax
     */
    private $shippingAndTaxModelMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Mage_Sales_Model_Quote
     */
    private $quoteMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteShippingAddressMock;

    /**
     * @var MockObject|Mage_Checkout_Model_Session Mocked instance of Mage::getSingleton('checkout/session')
     */
    private $checkoutSessionMock;

    /**
     * @var MockObject|Mage_Core_Model_Cache Mocked instance of Mage::app()->getCache()
     */
    private $cacheMock;

    /**
     * @var MockObject|Mage_Core_Controller_Response_Http Mocked instance of Magento controller response object
     */
    private $responseMock;

    /**
     * Sets up a shipping controller that mocks Bolt HMAC request validation with all helper
     * classes and and mocked states
     *
     * @throws Mage_Core_Exception if website is undefined
     * @throws Mage_Core_Model_Store_Exception if store is undefined
     * @throws ReflectionException on unexpected problems with reflection
     * @throws Zend_Controller_Request_Exception on unexpected problem in creating the controller
     * @throws Exception if test class name is not defined
     */
    public function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->disableOriginalConstructor()
            ->setMethods(array('getRequestData', 'getRequest', 'boltHelper', 'getResponse', 'sendResponse'))->getMock();
        $this->requestMock = $this->getClassPrototype('Mage_Core_Controller_Request_Http')
            ->setMethods(array('getPathInfo'))->getMock();
        $this->shippingAndTaxModelMock = $this->getClassPrototype('Bolt_Boltpay_Model_ShippingAndTax')->getMock();
        $this->cacheMock = $this->getClassPrototype('Mage_Core_Model_Cache')->getMock();
        $this->boltHelperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(
                array('verify_hook', 'setResponseContextHeaders', 'notifyException', 'logException', 'logWarning')
            )
            ->getMock();

        $this->checkoutSessionMock = $this->getClassPrototype('Mage_Checkout_Model_Session')->getMock();

        TestHelper::setNonPublicProperty($this->currentMock, '_shippingAndTaxModel', $this->shippingAndTaxModelMock);
        TestHelper::setNonPublicProperty($this->currentMock, '_cache', $this->cacheMock);

        $this->quoteMock = $this->getClassPrototype('Mage_Sales_Model_Quote')
            ->setMethods(
                array(
                    'getItemsCount',
                    'getId',
                    'getShippingAddress',
                    'getStore',
                    'loadByIdWithoutStore',
                    'getAllVisibleItems',
                    'getAppliedRuleIds',
                    'getCustomerId',
                    'getCustomerTaxClassId',
                    'getBaseSubtotalWithDiscount',
                )
            )->getMock();
        $this->quoteShippingAddressMock = $this->getClassPrototype('Mage_Sales_Model_Quote_Address')->getMock();
        $this->quoteMock->method('getShippingAddress')->willReturn($this->quoteShippingAddressMock);
        $this->quoteMock->method('getStore')->willReturn(self::STORE_ID);

        // Empty shopping cart
        Mage::getSingleton('checkout/cart')->truncate();

        $this->_shippingController = $this->getMockBuilder("Bolt_Boltpay_ShippingController")
            ->setConstructorArgs(
                array(new Mage_Core_Controller_Request_Http(), new Mage_Core_Controller_Response_Http())
            )
            ->setMethods(array('boltHelper', 'getResponse', 'sendResponse'))
            ->getMock();

        $this->responseMock = $this->getClassPrototype('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader'))
            ->getMock();

        $this->responseMock->method('setHeader')
            ->with(
                $this->anything(),
                $this->callback(
                    function ($headerValue) {
                        if ($headerValue === 'HIT' || $headerValue === 'MISS') {
                            $this->_cacheBoltHeader = $headerValue;
                        }

                        return true;
                    }
                )
            )
            ->willReturnSelf();

        $this->currentMock->method('getRequest')->willReturn($this->requestMock);
        $this->currentMock->method('getResponse')->willReturn($this->responseMock);
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);

        $this->boltHelperMock->method('verify_hook')->willReturn(true);
        $this->boltHelperMock->method('setResponseContextHeaders')->willReturn($this->responseMock);

        $this->_shippingController->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->_shippingController->method('getResponse')->willReturn($this->responseMock);

        $websiteId = Mage::app()->getWebsite()->getId();
        $store = Mage::app()->getStore();

        $this->_customer = Mage::getModel("customer/customer");
        $this->_customer->setWebsiteId($websiteId)
            ->setStore($store)
            ->setFirstname('Don')
            ->setLastname('Quijote')
            ->setEmail('test-shipping-cache@bolt.com')
            ->setPassword('somepassword');

    }

    /**
     * Restore values affected by tests
     *
     * @throws Mage_Core_Exception if unable to register isSecureAreaValue
     * @throws Exception if unable to restore original values
     */
    public function tearDown()
    {
        Mage::register('isSecureArea', true);
        $this->_customer->delete();
        Mage::unregister('isSecureArea');

        TestHelper::restoreOriginals();
        Mage::getSingleton('checkout/session')->setQuoteId(null);
    }

    /**
     * Generate dummy products for testing purposes before test
     *
     * @throws Exception if unable to delete dummy products
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$_productIds = array
        (
            Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_')),
            Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_')),
            Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'))
        );
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        foreach (self::$_productIds as $productId) {
            Bolt_Boltpay_ProductProvider::deleteDummyProduct($productId);
        }

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));
    }

    /**
     * @test
     * that if cache is in a valid state after prefetch data is sent.  Prior to
     * the prefetch, there should be no cache data.  After the prefetch, there should be
     * cached data.
     *
     * @covers ::prefetchEstimateAction
     *
     * @throws ReflectionException      on unexpected problems with reflection
     * @throws Zend_Cache_Exception     on unexpected problems reading or writing to Magento cache
     * @throws Mage_Core_Model_Store_Exception if Magento store is not defined
     */
    public function prefetchEstimateAction_withEmptyCache_estimateIsCachedAfterPrefetch()
    {

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $store = Mage::app()->getStore();
        $quote->setStore($store);

        $quoteSubtotal = 0;
        foreach (self::$_productIds as $productId) {
            $product = Mage::getModel('catalog/product')->load($productId);
            $qty = rand(1, 3);
            $quoteItem = Mage::getModel('sales/quote_item')->setProduct($product)->setQty($qty);
            $quote->addItem($quoteItem);
            $quoteSubtotal += ($qty * $product->getPrice());
        }

        $shipping_address = array(
            'firstname'            => $this->_customer->getFirstname(),
            'lastname'             => $this->_customer->getLastname(),
            'country_id'           => 'US',
            'region'               => '12',
            'city'                 => 'Beverly Hills',
            'postcode'             => '90210',
            'save_in_address_book' => 1
        );
        $quote->getShippingAddress()->addData($shipping_address);

        $quote->setCustomer($this->_customer);
        $quote->setCustomerTaxClassId(3);

        $quote->setParentQuoteId($quote->getId());  # stub the parent quote by assigning the ID to the cloned quote
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->setBaseSubtotalWithDiscount($quoteSubtotal)->save();

        $expectedAddressData = array(
            'city'       => 'Beverly Hills',
            'country_id' => 'US',
            'region_id'  => '12',
            'region'     => 'California',
            'postcode'   => '90210'
        );

        $expectedCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $expectedAddressData);
        $estimatePreCall = json_decode(Mage::app()->getCache()->load($expectedCacheId));

        $geoIpAddressData = array(
            'city'         => 'Beverly Hills',
            'country_code' => 'US',
            'region_code'  => 'CA',
            'region_name'  => 'California',
            'zip_code'     => '90210'
        );

        TestHelper::setNonPublicProperty($this->_shippingController, 'payload', json_encode($geoIpAddressData));

        $actualResponse = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function ($response) use (&$actualResponse) {
                        return $actualResponse = $response;
                    }
                )
            );

        $this->_shippingController->prefetchEstimateAction();

        $actualAddressData = json_decode($actualResponse, true)['address_data'];
        $actualCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $actualAddressData);
        $estimatePostCall = json_decode(Mage::app()->getCache()->load($actualCacheId), true);

        $this->assertEquals($expectedCacheId, $actualCacheId);
        $this->assertEmpty(
            $estimatePreCall,
            'A value is cached but there should be no cached value for the id ' . $expectedCacheId
        );
        $this->assertNotEmpty(
            $estimatePostCall,
            'A value should be cached but it is empty for the id ' . $actualCacheId . ': ' . var_export(
                $estimatePostCall,
                true
            )
        );
        $this->assertArrayHasKey('tax_result', $estimatePostCall);
        $this->assertArrayHasKey('shipping_options', $estimatePostCall);

    }


    /**
     * @test
     * that if cache is in a valid state call to get estimate.  Prior to
     * the call, there should be no cache data (i.e. MISS).  After the call, with the same
     * data, the response should come from the cache (i.e. HIT).  After the third call,
     * with address data changed, there should be a MISS.  A fourth call with the original address
     * data should yield a HIT.
     *
     * @covers ::indexAction
     *
     * @throws Mage_Core_Exception from setup if website is not defined
     * @throws Mage_Core_Model_Store_Exception from setup if store is not defined
     * @throws ReflectionException on unexpected problems with reflection
     * @throws Zend_Cache_Exception on unexpected problems reading or writing to Magento cache
     * @throws Zend_Controller_Request_Exception if unable to create controller
     */
    public function indexAction_withMultipleConsecutiveCallsWithDifferentAddresses_estimatesOncePerAddressAndReturnsFromCache()
    {

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $quote->setCustomer($this->_customer);

        $quote->setCustomerTaxClassId(2);

        foreach (self::$_productIds as $productId) {
            TestHelper::addProduct($productId, rand(1, 3));
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();

        $boltFormatShippingAddress = array(
            'email'           => 'test-shipping-cache@bolt.com',
            'first_name'      => 'Don',
            'last_name'       => 'Quijote',
            'street_address1' => '1000 Golpes',
            'street_address2' => 'Windmill C',
            'locality'        => 'San Francisco',
            'postal_code'     => '94121',
            'phone'           => '+1 867 888 338 3903',
            'country_code'    => 'US',
            'company'         => 'Bolt',
            'region'          => 'California'
        );
        $originalMagentoFormatAddressData = array(
            'city'       => 'San Francisco',
            'country_id' => 'US',
            'region_id'  => '12',
            'region'     => 'California',
            'postcode'   => '94121'
        );

        $originalAddressExpectedCacheId = $this->_shippingController->getEstimateCacheIdentifier(
            $quote,
            $originalMagentoFormatAddressData
        );

        $mockBoltRequestData = $originalMockBoltRequestData = array(
            'cart'             =>
                array(
                    'display_id' => 'mock quote id |' . $quote->getId()
                ),
            'shipping_address' => $boltFormatShippingAddress
        );

        ////////////////////////////////////////////////////////
        // Make first call that should not have a cache value
        ////////////////////////////////////////////////////////
        $firstCallEstimate = '';
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->_shippingController,
            'payload',
            json_encode($mockBoltRequestData)
        );
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function ($response) use (&$firstCallEstimate) {
                        return $firstCallEstimate = $response;
                    }
                )
            );
        $this->_shippingController->indexAction();
        $firstCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a second call that should be read from the cache
        ////////////////////////////////////////////////////////
        $this->reset();
        $secondCallEstimate = '';
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->_shippingController,
            'payload',
            json_encode($mockBoltRequestData)
        );
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function ($response) use (&$secondCallEstimate) {
                        return $secondCallEstimate = $response;
                    }
                )
            );
        $this->_shippingController->indexAction();
        $secondCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a third call with a different address.
        // It should not be read from the cache
        ////////////////////////////////////////////////////////
        $this->reset();
        $boltFormatShippingAddress['locality'] = 'Columbus';
        $boltFormatShippingAddress['region'] = 'Ohio';
        $boltFormatShippingAddress['postal_code'] = '43235';
        $mockBoltRequestData['shipping_address'] = $boltFormatShippingAddress;

        $modifiedMagentoFormatAddressData = array(
            'city'       => 'Columbus',
            'country_id' => 'US',
            'region_id'  => '47',
            'region'     => 'Ohio',
            'postcode'   => '43235'
        );
        $modifiedAddressExpectedCacheId = $this->_shippingController->getEstimateCacheIdentifier(
            $quote,
            $modifiedMagentoFormatAddressData
        );

        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->_shippingController,
            'payload',
            json_encode($mockBoltRequestData)
        );

        $thirdCallEstimate = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function ($response) use (&$thirdCallEstimate) {
                        return $thirdCallEstimate = $response;
                    }
                )
            );
        $this->_shippingController->indexAction();
        $thirdCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a fourth call with the original data
        // It should be read from the cache
        ////////////////////////////////////////////////////////
        $this->reset();
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->_shippingController,
            'payload',
            json_encode($originalMockBoltRequestData)
        );

        $fourthCallEstimate = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function ($response) use (&$fourthCallEstimate) {
                        return $fourthCallEstimate = $response;
                    }
                )
            );
        $this->_shippingController->indexAction();
        $fourthCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////

        $this->assertNotEmpty($firstCallEstimate);
        $this->assertNotEmpty($secondCallEstimate);
        $this->assertNotEmpty($thirdCallEstimate);
        $this->assertEquals($firstCallEstimate, $secondCallEstimate);
        $this->assertEquals($firstCallEstimate, $fourthCallEstimate);
        $this->assertNotEquals($originalAddressExpectedCacheId, $modifiedAddressExpectedCacheId);
        $this->assertEquals($firstCallHitOrMiss, 'MISS');
        $this->assertEquals($secondCallHitOrMiss, 'HIT');
        $this->assertEquals($thirdCallHitOrMiss, 'MISS');
        $this->assertEquals($fourthCallHitOrMiss, 'HIT');

    }

    /**
     * Resets the conditions for internal retest of the same method
     * @todo Replace this implementation with annotations using "@dataProvider"
     *
     * @throws Mage_Core_Exception from setup if website is not defined
     * @throws Mage_Core_Model_Store_Exception from setup if store is not defined
     * @throws ReflectionException on unexpected problems with reflection
     * @throws Zend_Controller_Request_Exception on unexpected problem in creating the controller
     */
    private function reset()
    {
        $this->tearDown();
        $this->setUp();
    }

    /**
     * @test
     * that Magento internal constructor sets requestMustBeSigned property of the object to false
     * if request path contains prefetchEstimate
     *
     * @covers ::_construct
     *
     * @throws ReflectionException if _construct method is not defined
     */
    public function _construct_ifPathInfoContainsPrefetchEstimate_requestDoesntHaveToBeSigned()
    {
        $this->requestMock->expects($this->once())->method('getPathInfo')->willReturn('shipping/prefetchEstimate');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, '_construct');
        $this->assertAttributeEquals(false, 'requestMustBeSigned', $this->currentMock);
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_ShippingController::indexAction}
     *
     * @param mixed $requestData to be returned from getRequestData
     */
    private function indexActionSetUp($requestData)
    {
        $this->currentMock->method('getRequestData')->willReturn($requestData);
    }

    /**
     * @test
     * that indexAction will return failure response stating that PO Boxes are not allowed
     * if PO box addresses are not allowed and one is provided
     *
     * @covers ::indexAction
     *
     * @expectedException Exception
     * @expectedExceptionMessage Expected exception that simulates exit in sendResponse
     */
    public function indexAction_ifPOBoxIsNotAllowedAndAddressContainsPOBox_returnsFailureResponse()
    {
        $requestData = new stdClass();
        $requestData->shipping_address->street_address1 = 'Sample Street 10';
        $requestData->shipping_address->street_address2 = 'Apt 123';

        $this->indexActionSetUp($requestData);
        $this->shippingAndTaxModelMock->expects($this->once())->method('isPOBoxAllowed')->willReturn(false);
        $this->shippingAndTaxModelMock->expects($this->once())->method('doesAddressContainPOBox')
            ->with('Sample Street 10', 'Apt 123')->willReturn(true);

        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                422,
                array(
                    'status' => 'failure',
                    'error'  => array(
                        'code'    => 6101,
                        'message' => 'Address with P.O. Box is not allowed.'
                    )
                )
            )
            ->willThrowException(new Exception('Expected exception that simulates exit in sendResponse'));

        $this->boltHelperMock->expects($this->atLeastOnce())->method('logWarning')
            ->with('Address with P.O. Box is not allowed.');
        $this->currentMock->indexAction();
    }

    /**
     * @test
     * that indexAction returns failure response containing first quote validation message if quote validation fails
     *
     * @covers ::indexAction
     *
     * @expectedException Exception
     * @expectedExceptionMessage Expected exception that simulates exit in sendResponse
     */
    public function indexAction_whenQuoteValidationFails_returnsFailureResponseWithFirstQuoteValidationError()
    {
        $requestData = new stdClass();

        $this->indexActionSetUp($requestData);

        $this->shippingAndTaxModelMock->expects($this->once())->method('isPOBoxAllowed')->willReturn(true);

        TestHelper::stubModel('sales/quote', $this->quoteMock);
        $this->quoteMock->method('loadByIdWithoutStore')->willReturnSelf();
        $this->quoteShippingAddressMock->expects($this->once())->method('validate')
            ->willReturn(array('Please enter the first name.', 'Please enter the last name.'));

        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                422,
                array(
                    'status' => 'failure',
                    'error'  => array(
                        'code'    => 6103,
                        'message' => 'Please enter the first name.'
                    )
                )
            )
            ->willThrowException(new Exception('Expected exception that simulates exit in sendResponse'));
        $this->currentMock->indexAction();
    }

    /**
     * Setup method for tests covering {@see Bolt_Boltpay_ShippingController::indexAction}
     *
     * @param Mage_Sales_Model_Quote $quote to be returned from checkout session
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    private function prefetchEstimateActionSetUp($quote)
    {
        TestHelper::stubSingleton('checkout/session', $this->checkoutSessionMock);
        $this->checkoutSessionMock->method('getQuote')->willReturn($quote);
    }

    /**
     * @test
     * that prefetchEstimateAction doesn't proceed with reading from cache or creating new estimate
     * if quote doesn't have id (is not saved in database)
     *
     * @covers ::prefetchEstimateAction
     *
     * @expectedException Exception
     * @expectedExceptionMessage Expected exception that simulates exit from sendResponse
     *
     * @throws Mage_Core_Exception from setup if unable to stub singleton
     */
    public function prefetchEstimateAction_whenSessionQuoteHasNoId_doesNotPrefetchEstimate()
    {
        $this->prefetchEstimateActionSetUp($this->quoteMock);
        $this->quoteMock->expects($this->once())->method('getId')->willReturn(null);
        $this->currentMock->expects($this->once())->method('sendResponse')->with(200)
            ->willThrowException(new Exception('Expected exception that simulates exit from sendResponse'));
        $this->cacheMock->expects($this->never())->method('load');
        $this->shippingAndTaxModelMock->expects($this->never())->method('getShippingAndTaxEstimate');
        $this->currentMock->prefetchEstimateAction();
    }

    /**
     * @test
     * that prefetchEstimateAction doesn't proceed with reading from cache or creating new estimate
     * if quote doesn't have any items
     *
     * @covers ::prefetchEstimateAction
     *
     * @expectedException Exception
     * @expectedExceptionMessage Expected exception that simulates exit from sendResponse
     *
     * @throws Mage_Core_Exception from setup if unable to stub singleton
     */
    public function prefetchEstimateAction_whenSessionQuoteHasNoItems_doesNotPrefetchEstimate()
    {
        $this->prefetchEstimateActionSetUp($this->quoteMock);
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn(self::QUOTE_ID);
        $this->quoteMock->expects($this->atLeastOnce())->method('getItemsCount')->willReturn(0);
        $this->currentMock->expects($this->once())->method('sendResponse')->with(200)
            ->willThrowException(new Exception('Expected exception that simulates exit from sendResponse'));
        $this->cacheMock->expects($this->never())->method('load');
        $this->shippingAndTaxModelMock->expects($this->never())->method('getShippingAndTaxEstimate');
        $this->currentMock->prefetchEstimateAction();
    }

    /**
     * @test
     * that prefetchEstimateAction doesn't perform estimation if it is already cached for provided address data
     *
     * @covers ::prefetchEstimateAction
     *
     * @throws Mage_Core_Exception from setup if unable to stub singleton
     */
    public function prefetchEstimateAction_ifEstimateAlreadyInCache_returnsSuccessResponseWithoutPreFetching()
    {
        $this->prefetchEstimateActionSetUp($this->quoteMock);
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn(self::QUOTE_ID);
        $this->quoteMock->expects($this->atLeastOnce())->method('getItemsCount')->willReturn(1);

        $addressData = array(
            'city'       => 'Beverly Hills',
            'country_id' => 'US',
            'region_id'  => 'CA',
            'region'     => 'California',
            'postcode'   => '90210'
        );
        $this->quoteShippingAddressMock->method('getData')->willReturn($addressData);

        $this->cacheMock->expects($this->once())->method('load')
            ->with($this->currentMock->getEstimateCacheIdentifier($this->quoteMock, $addressData))
            ->willReturn(true);

        $this->currentMock->expects($this->once())->method('sendResponse')->with(
            200,
            Mage::helper('core')->jsonEncode(
                array('address_data' => $addressData)
            )
        );

        $this->shippingAndTaxModelMock->expects($this->never())->method('getShippingAndTaxEstimate');

        $this->currentMock->prefetchEstimateAction();
    }

    /**
     * @test
     * that if an exception is thrown during estimation process, it is logged and a success response is returned
     *
     * @covers ::prefetchEstimateAction
     *
     * @throws Mage_Core_Exception from setup if unable to stub singleton
     */
    public function prefetchEstimateAction_estimationThrowsException_returnsSuccessResponseAndLogsTheException()
    {
        $this->prefetchEstimateActionSetUp($this->quoteMock);
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn(self::QUOTE_ID);
        $this->quoteMock->expects($this->atLeastOnce())->method('getItemsCount')->willReturn(1);

        $addressData = array(
            'city'       => 'Beverly Hills',
            'country_id' => 'US',
            'region_id'  => 'CA',
            'region'     => 'California',
            'postcode'   => '90210'
        );
        $this->quoteShippingAddressMock->method('getData')->willReturn(
            $addressData
        );

        $this->currentMock->expects($this->once())->method('sendResponse')->with(
            200,
            Mage::helper('core')->jsonEncode(
                array('address_data' => $addressData)
            )
        );

        $exception = new Exception();
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with(
            $exception,
            $this->logicalAnd(
                $this->arrayHasKey('quote'),
                $this->arrayHasKey('address_data'),
                $this->arrayHasKey('cache_key'),
                $this->arrayHasKey('estimate')
            ),
            'info'
        );
        $this->shippingAndTaxModelMock->expects($this->once())->method('getShippingAndTaxEstimate')
            ->willThrowException($exception);

        $this->currentMock->prefetchEstimateAction();
    }

    /**
     * @test
     * that geoIpAddress returns address data in Magento format containing region_id if country supports it
     *
     * @covers ::getGeoIpAddress
     *
     * @throws ReflectionException if getGeoIpAddress method doesn't exist
     */
    public function getGeoIpAddress_addressCountryHasRegions_returnsAddressDataInMagentoFormat()
    {
        $geoIpAddressData = (object)array(
            'city'         => 'Beverly Hills',
            'country_code' => 'US',
            'region_code'  => 'CA',
            'region_name'  => 'California',
            'zip_code'     => '90210'
        );
        $this->currentMock->method('getRequestData')->willReturn($geoIpAddressData);

        $this->assertSame(
            array(
                'city'        => 'Beverly Hills',
                'region'      => 'California',
                'region_name' => 'California',
                'postcode'    => '90210',
                'country_id'  => 'US',
                'region_id'   => '12',
            ),
            TestHelper::callNonPublicFunction($this->currentMock, 'getGeoIpAddress')
        );
    }

    /**
     * @test
     * that geoIpAddress returns address data in Magento format without region_id if country doesn't support it
     *
     * @covers ::getGeoIpAddress
     *
     * @throws ReflectionException if getGeoIpAddress method doesn't exist
     */
    public function getGeoIpAddress_addressCountryDoesntHaveRegions_returnsAddressDataInMagentoFormat()
    {
        $geoIpAddressData = (object)array(
            'city'         => 'Banchor',
            'country_code' => 'GB',
            'region_code'  => 'Banchor',
            'region_name'  => 'Banchor',
            'zip_code'     => 'IV12 8QP'
        );
        $this->currentMock->method('getRequestData')->willReturn($geoIpAddressData);

        $this->assertSame(
            array(
                'city'        => 'Banchor',
                'region'      => 'Banchor',
                'region_name' => 'Banchor',
                'postcode'    => 'IV12 8QP',
                'country_id'  => 'GB'
            ),
            TestHelper::callNonPublicFunction($this->currentMock, 'getGeoIpAddress')
        );
    }

    /**
     * @test
     * that isApplePayRequest asserts if request is Apple Pay by checking that shipping address name is equal to n/a
     *
     * @covers ::isApplePayRequest
     *
     * @throws ReflectionException if isApplePayRequest method doesn't exist
     */
    public function isApplePayRequest_withAppleRedactedAddressName_returnsTrue()
    {
        $requestData = new stdClass();
        $requestData->shipping_address->name = 'n/a';
        $this->currentMock->expects($this->once())->method('getRequestData')->willReturn($requestData);
        $this->assertTrue(TestHelper::callNonPublicFunction($this->currentMock, 'isApplePayRequest'));
    }

    /**
     * @test
     * that isApplePayRequest asserts if request is Apple Pay by checking that shipping address name is equal to n/a
     *
     * @covers ::isApplePayRequest
     *
     * @throws ReflectionException if isApplePayRequest method is not defined
     */
    public function isApplePayRequest_withNameEqualToNull_returnsFalse()
    {
        $requestData = new stdClass();
        $requestData->shipping_address->name = null;
        $this->currentMock->expects($this->once())->method('getRequestData')->willReturn($requestData);
        $this->assertFalse(TestHelper::callNonPublicFunction($this->currentMock, 'isApplePayRequest'));
    }

    /**
     * @test
     * that shouldDoAddressValidation determines if address validation should be performed
     * based on provided request data
     *
     * @covers ::shouldDoAddressValidation
     *
     * @dataProvider shouldDoAddressValidation_withVariousResultsOfInternalCalls_determinesIfAddressValidationShouldBeDoneProvider
     *
     * @param object $requestData to be returned from {@see Bolt_Boltpay_Controller_Traits_WebHookTrait::getRequestData}
     * @param bool   $expectedResult of the method call
     *
     * @throws ReflectionException if shouldDoAddressValidation method is not defined
     */
    public function shouldDoAddressValidation_withVariousResultsOfInternalCalls_determinesIfAddressValidationShouldBeDone($requestData, $expectedResult)
    {
        $this->currentMock->expects($this->once())->method('getRequestData')->willReturn($requestData);
        $this->assertSame(
            $expectedResult,
            TestHelper::callNonPublicFunction($this->currentMock, 'shouldDoAddressValidation')
        );
    }

    /**
     * Data provider for {@see shouldDoAddressValidation_withVariousResultsOfInternalCalls_determinesIfAddressValidationShouldBeDone}
     *
     * @return array containing request data and expected result of the method call
     */
    public function shouldDoAddressValidation_withVariousResultsOfInternalCalls_determinesIfAddressValidationShouldBeDoneProvider()
    {
        return array(
            'Not Apple pay request - should validate' => array(
                'requestData'    => (object)array('shipping_address' => (object)array('name' => null)),
                'expectedResult' => true
            ),
            'Apple pay request - should not validate' => array(
                'requestData'    => (object)array('shipping_address' => (object)array('name' => 'n/a')),
                'expectedResult' => false
            ),
        );
    }

    /**
     * @test
     * that getEstimateCacheIdentifier returns hash of provided data concatenated in a expected format
     *
     * @covers ::getEstimateCacheIdentifier
     */
    public function getEstimateCacheIdentifier_withProvidedData_returnsEstimateCacheIdentifier()
    {
        $baseSubtotalWithDiscount = 123;
        $customerId = 456;
        $taxClassId = 758;
        $addressData = array(
            'city'       => 'Los Angeles',
            'postcode'   => '90014',
            'country_id' => 'US',
            'region_id'  => '12',
            'region'     => 'California'
        );
        $itemsData = array(
            array('product_id' => 789, 'qty' => 12),
            array('product_id' => 987, 'qty' => 21),
        );
        $appliedRuleIds = array(178, 212);
        $this->quoteMock->method('getId')->willReturn(self::QUOTE_ID);
        $this->quoteMock->method('getBaseSubtotalWithDiscount')->willReturn($baseSubtotalWithDiscount);
        $this->quoteMock->method('getCustomerId')->willReturn($customerId);
        $this->quoteMock->method('getCustomerTaxClassId')->willReturn($taxClassId);
        $this->quoteMock->method('getAllVisibleItems')->willReturn(
            array_map(
                function ($itemData) {
                    return Mage::getModel('sales/quote_item', $itemData);
                },
                $itemsData
            )
        );
        $this->quoteMock->method('getAppliedRuleIds')->willReturn($appliedRuleIds);

        $expectedCacheIdentifierString = self::QUOTE_ID . "_subtotal-" . $baseSubtotalWithDiscount * 100;
        $expectedCacheIdentifierString .= "_customer-" . $customerId;
        $expectedCacheIdentifierString .= "_tax-class-" . $taxClassId;
        $expectedCacheIdentifierString .= "_country-id-" . $addressData['country_id'];
        $expectedCacheIdentifierString .= "_postcode-" . $addressData['postcode'];
        $expectedCacheIdentifierString .= "_city-" . $addressData['city'];
        $expectedCacheIdentifierString .= "_region-" . $addressData['region'];
        $expectedCacheIdentifierString .= "_region-id-" . $addressData['region_id'];
        foreach ($itemsData as $key => $itemData) {
            $expectedCacheIdentifierString .= '_item-' . $itemData['product_id'] . '-quantity-' . $itemData['qty'];
        }

        $expectedCacheIdentifierString .= "_applied-rules-" . json_encode($appliedRuleIds);

        $this->assertSame(
            md5($expectedCacheIdentifierString),
            $this->currentMock->getEstimateCacheIdentifier($this->quoteMock, $addressData)
        );
    }

    /**
     * @test
     * that cacheShippingAndTaxEstimate saves estimation to cache under the provided key in json_encode format
     *
     * @covers ::cacheShippingAndTaxEstimate
     *
     * @throws ReflectionException if cacheShippingAndTaxEstimate method doesn't exist
     */
    public function cacheShippingAndTaxEstimate_always_savesJsonizedEstimateToCache()
    {
        $dummyEstimate = array(
            'shipping_options' => array(),
            'tax_result' => array(
                "amount" => 0
            ),
        );
        $quoteCacheKey = md5('bolt');
        $lifetime = 600;
        $this->cacheMock->expects($this->once())->method('save')->with(
            json_encode($dummyEstimate, JSON_PRETTY_PRINT),
            $quoteCacheKey,
            array('BOLT_QUOTE_PREFETCH'),
            $lifetime
        );
        TestHelper::callNonPublicFunction(
            $this->currentMock,
            'cacheShippingAndTaxEstimate',
            array(
                $dummyEstimate,
                $quoteCacheKey,
                $lifetime
            )
        );
    }
}