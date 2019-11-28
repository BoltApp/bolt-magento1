<?php

require_once 'Bolt/Boltpay/controllers/ShippingController.php';

/**
 * Class Bolt_Boltpay_ShippingControllerTest
 *
 * Test the shipping controller, particularly with shipping and tax estimates and caching
 */
class Bolt_Boltpay_ShippingControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Bolt_Boltpay_ShippingController|PHPUnit_Framework_MockObject_MockObject The stubbed shipping controller
     */
    private $_shippingController;

    /**
     * @var array ids of temporary products used for testing
     */
    private static $_productIds = array();

    /**
     * @var Bolt_Boltpay_TestHelper  Used for working with the shopping cart
     */
    private $testHelper;

    /**
     * @var string  Used for storing $cacheBoltHeader in the header of response
     */
    private $_cacheBoltHeader;

    /**
     * @var Mage_Customer_Model_Customer
     */
    private $_customer;


    /**
     * Sets up a shipping controller that mocks Bolt HMAC request validation with all helper
     * classes and and mocked states
     *
     * @throws ReflectionException                  on unexpected problems with reflection
     * @throws Zend_Controller_Request_Exception    on unexpected problem in creating the controller
     */
    public function setUp()
    {
        // Empty shopping cart
        Mage::getSingleton('checkout/cart')->truncate();

        $this->_shippingController = $this->getMockBuilder( "Bolt_Boltpay_ShippingController")
            ->setConstructorArgs( array( new Mage_Core_Controller_Request_Http(), new Mage_Core_Controller_Response_Http()) )
            ->setMethods(['boltHelper', 'getResponse', 'sendResponse'])
            ->getMock();

        $stubbedBoltHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('verify_hook', 'setResponseContextHeaders'))
            ->getMock();

        $stubbedResponse = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(['setHeader'])
            ->getMock();

        $stubbedResponse->method('setHeader')
            ->with(
                $this->anything(),
                $this->callback(function($headerValue){
                    if($headerValue === 'HIT' || $headerValue === 'MISS'){
                        $this->_cacheBoltHeader = $headerValue;
                    }
                    return $headerValue;
                })
            )
            ->willReturn($stubbedResponse);

        $stubbedBoltHelper->method('verify_hook')->willReturn(true);

        $stubbedBoltHelper->method('setResponseContextHeaders')->willReturn($stubbedResponse);

        $this->_shippingController->method('boltHelper')->willReturn($stubbedBoltHelper);

        $this->_shippingController->method('getResponse')->willReturn($stubbedResponse);

        $this->testHelper = new Bolt_Boltpay_TestHelper();

        $websiteId = Mage::app()->getWebsite()->getId();
        $store = Mage::app()->getStore();

        $this->_customer = Mage::getModel("customer/customer");
        $this->_customer   ->setWebsiteId($websiteId)
            ->setStore($store)
            ->setFirstname('Don')
            ->setLastname('Quijote')
            ->setEmail('test-shipping-cache@bolt.com')
            ->setPassword('somepassword');

    }

    public function tearDown() {
        Mage::register('isSecureArea', true);
        $this->_customer->delete();
        Mage::unregister('isSecureArea');

        Mage::getSingleton('checkout/session')->setQuoteId(null);
    }



    /**
     * Generate dummy products for testing purposes before test
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
     * Test to see if cache is in a valid state after prefetch data is sent.  Prior to
     * the prefetch, there should be no cache data.  After the prefetch, there should be
     * cached data.
     *
     * @throws ReflectionException      on unexpected problems with reflection
     * @throws Zend_Cache_Exception     on unexpected problems reading or writing to Magento cache
     */
    public function testIfEstimateIsCachedAfterPrefetch() {

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $store = Mage::app()->getStore();
        $quote->setStore($store);

        $quoteSubtotal = 0;
        foreach(self::$_productIds as $productId) {
            $product = Mage::getModel('catalog/product')->load($productId);
            $qty = rand(1,3);
            $quoteItem = Mage::getModel('sales/quote_item')->setProduct($product)->setQty($qty);
            $quote->addItem($quoteItem);
            $quoteSubtotal += ($qty * $product->getPrice());
        }

        $shipping_address = array(
            'firstname' => $this->_customer->getFirstname(),
            'lastname' => $this->_customer->getLastname(),
            'country_id' => 'US',
            'region' => '12',
            'city' => 'Beverly Hills',
            'postcode' => '90210',
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
            'region' => 'California',
            'postcode' => '90210'
        );

        $expectedCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $expectedAddressData);
        $estimatePreCall = unserialize(Mage::app()->getCache()->load($expectedCacheId));

        $geoIpAddressData = array(
            'city'       => 'Beverly Hills',
            'country_code' => 'US',
            'region_code'  => 'CA',
            'region_name' => 'California',
            'zip_code' => '90210'
        );

        $reflectedShippingController = new ReflectionClass($this->_shippingController);
        $reflectedPayload = $reflectedShippingController->getProperty('payload');
        $reflectedPayload->setAccessible(true);
        $reflectedPayload->setValue($this->_shippingController, json_encode($geoIpAddressData));

        $actualResponse = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function($response) use (&$actualResponse) {
                        return $actualResponse = $response;
                    }
                )
            )
        ;

        $this->_shippingController->prefetchEstimateAction();

        $actualAddressData =  json_decode($actualResponse, true)['address_data'];
        $actualCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $actualAddressData);
        $estimatePostCall = unserialize(Mage::app()->getCache()->load($actualCacheId));

        $this->assertEquals($expectedCacheId, $actualCacheId);
        $this->assertEmpty( $estimatePreCall, 'A value is cached but there should be no cached value for the id '.$expectedCacheId);
        $this->assertNotEmpty( $estimatePostCall, 'A value should be cached but it is empty for the id '.$actualCacheId.': '.var_export($estimatePostCall, true));
        $this->assertArrayHasKey('tax_result', $estimatePostCall);
        $this->assertArrayHasKey('shipping_options', $estimatePostCall );

    }


    /**
     * Test to see if cache is in a valid state call to get estimate.  Prior to
     * the call, there should be no cache data (i.e. MISS).  After the call, with the same
     * data, the response should come from the cache (i.e. HIT).  After the third call,
     * with address data changed, there should be a MISS.  A fourth call with the original address
     * data should yield a HIT.
     *
     * @throws ReflectionException      on unexpected problems with reflection
     * @throws Zend_Cache_Exception     on unexpected problems reading or writing to Magento cache
     */
    public function testIfEstimateIsCachedDirectCall() {

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $quote->setCustomer($this->_customer);

        $quote->setCustomerTaxClassId(2);

        foreach(self::$_productIds as $productId) {
            $this->testHelper->addProduct($productId, rand(1,3));
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();

        $reflectedShippingController = new ReflectionClass($this->_shippingController);

        $boltFormatShippingAddress = array(
            'email' => 'test-shipping-cache@bolt.com',
            'first_name' => 'Don',
            'last_name' => 'Quijote',
            'street_address1' => '1000 Golpes',
            'street_address2' => 'Windmill C',
            'locality' => 'San Francisco',
            'postal_code' => '94121',
            'phone' => '+1 867 888 338 3903',
            'country_code' => 'US',
            'company' => 'Bolt',
            'region' => 'California'
        );
        $originalMagentoFormatAddressData = array(
            'city'       => 'San Francisco',
            'country_id' => 'US',
            'region_id'  => '12',
            'region' => 'California',
            'postcode' => '94121'
        );

        $originalAddressExpectedCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $originalMagentoFormatAddressData);

        $mockBoltRequestData = $originalMockBoltRequestData = array(
            'cart' =>
                array(
                    'display_id' => 'mock quote id |'.$quote->getId()
                ),
            'shipping_address' => $boltFormatShippingAddress
        );

        ////////////////////////////////////////////////////////
        // Make first call that should not have a cache value
        ////////////////////////////////////////////////////////
        $firstCallEstimate = '';
        Bolt_Boltpay_TestHelper::setNonPublicProperty( $this->_shippingController, 'payload', json_encode($mockBoltRequestData));
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function($response) use (&$firstCallEstimate) {
                        return $firstCallEstimate = $response;
                    }
                )
            )
        ;
        $this->_shippingController->indexAction();
        $firstCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a second call that should be read from the cache
        ////////////////////////////////////////////////////////
        $this->reset();
        $secondCallEstimate = '';
        Bolt_Boltpay_TestHelper::setNonPublicProperty( $this->_shippingController, 'payload', json_encode($mockBoltRequestData));
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function($response) use (&$secondCallEstimate) {
                        return $secondCallEstimate = $response;
                    }
                )
            )
        ;
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
            'region' => 'Ohio',
            'postcode' => '43235'
        );
        $modifiedAddressExpectedCacheId = $this->_shippingController->getEstimateCacheIdentifier($quote, $modifiedMagentoFormatAddressData);

        Bolt_Boltpay_TestHelper::setNonPublicProperty( $this->_shippingController, 'payload', json_encode($mockBoltRequestData));

        $thirdCallEstimate = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function($response) use (&$thirdCallEstimate) {
                        return $thirdCallEstimate = $response;
                    }
                )
            )
        ;
        $this->_shippingController->indexAction();
        $thirdCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a fourth call with the original data
        // It should be read from the cache
        ////////////////////////////////////////////////////////
        $this->reset();
        Bolt_Boltpay_TestHelper::setNonPublicProperty( $this->_shippingController, 'payload', json_encode($originalMockBoltRequestData));

        $fourthCallEstimate = '';
        $this->_shippingController->expects($this->once())
            ->method('sendResponse')
            ->with(
                200,
                $this->callback(
                    function($response) use (&$fourthCallEstimate) {
                        return $fourthCallEstimate = $response;
                    }
                )
            )
        ;
        $this->_shippingController->indexAction();
        $fourthCallHitOrMiss = $this->_cacheBoltHeader;
        ////////////////////////////////////////////////////////

        $this->assertNotEmpty( $firstCallEstimate );
        $this->assertNotEmpty( $secondCallEstimate );
        $this->assertNotEmpty( $thirdCallEstimate) ;
        $this->assertEquals( $firstCallEstimate, $secondCallEstimate );
        $this->assertEquals( $firstCallEstimate, $fourthCallEstimate );
        $this->assertNotEquals( $originalAddressExpectedCacheId, $modifiedAddressExpectedCacheId );
        $this->assertEquals( $firstCallHitOrMiss, 'MISS' );
        $this->assertEquals( $secondCallHitOrMiss, 'HIT' );
        $this->assertEquals( $thirdCallHitOrMiss, 'MISS' );
        $this->assertEquals( $fourthCallHitOrMiss, 'HIT' );

    }

    /**
     * Resets the conditions for internal retest of the same method
     * @todo Replace this implementation with annotations using "@dataProvider"
     *
     * @throws ReflectionException on unexpected problems with reflection
     * @throws Zend_Controller_Request_Exception on unexpected problem in creating the controller
     */
    private function reset() {
        $this->tearDown();
        $this->setUp();
    }
}