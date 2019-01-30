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
     * @var Bolt_Boltpay_ShippingController The stubbed shipping controller
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
     * Sets up a shipping controller that mocks Bolt HMAC request validation with all helper
     * classes and and mocked states
     *
     * @throws ReflectionException                  on unexpected problems with reflection
     * @throws Zend_Controller_Request_Exception    on unexpected problem in creating the controller
     */
    public function setUp()
    {
        $this->_shippingController = new Bolt_Boltpay_ShippingController(
            new Mage_Core_Controller_Request_Http(),
            new Mage_Core_Controller_Response_Http()
        );

        $stubbedBoltApiHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(array('verify_hook'))
            ->getMock();

        $stubbedBoltApiHelper->method('verify_hook')->willReturn(true);

        $reflectedShippingController = new ReflectionClass($this->_shippingController);

        $reflectedBoltApiHelper = $reflectedShippingController->getProperty('_boltApiHelper');
        $reflectedBoltApiHelper->setAccessible(true);
        $reflectedBoltApiHelper->setValue($this->_shippingController, $stubbedBoltApiHelper);

        $this->testHelper = new Bolt_Boltpay_TestHelper();

    }



    /**
     * Generate dummy products for testing purposes before test
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$_productIds = array
        (
            Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_SC_TEST_PROD_01'),
            Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_SC_TEST_PROD_02'),
            Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_SC_TEST_PROD_03'),
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

        $quote
            ->removeAllAddresses()
            ->removeAllItems()
            ->setCustomerId(25)
            ->setCustomerTaxClassId(3);

        foreach(self::$_productIds as $productId) {
            $this->testHelper->addProduct($productId, rand(1,3));
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();

        $reflectedShippingController = new ReflectionClass($this->_shippingController);
        $reflectedGetEstimateCacheIdentifier = $reflectedShippingController->getMethod('getEstimateCacheIdentifier');
        $reflectedGetEstimateCacheIdentifier->setAccessible(true);

        $expectedAddressData = array(
            'city'       => 'Beverly Hills',
            'country_id' => 'US',
            'region_id'  => '12',
            'region' => 'California',
            'postcode' => '90210'
        );
        $expectedCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $expectedAddressData);
        $estimatePreCall = unserialize(Mage::app()->getCache()->load($expectedCacheId));


        $geoIpAddressData = array(
            'city'       => 'Beverly Hills',
            'country_code' => 'US',
            'region_code'  => 'CA',
            'region_name' => 'California',
            'zip_code' => '90210'
        );
        $reflectedRequestJson = $reflectedShippingController->getProperty('_requestJSON');
        $reflectedRequestJson->setAccessible(true);
        $reflectedRequestJson->setValue($this->_shippingController, json_encode($geoIpAddressData));

        $this->_shippingController->prefetchEstimateAction();

        $actualAddressData = json_decode($this->_shippingController->getResponse()->getBody(), true)['address_data'];

        $actualCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $actualAddressData);
        $estimatePostCall = unserialize(Mage::app()->getCache()->load($actualCacheId));

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        $this->assertEquals($expectedCacheId, $actualCacheId);
        $this->assertEmpty( $estimatePreCall, 'A value is cached but there should be no cached value for the id '.$expectedCacheId);
        $this->assertNotEmpty( $estimatePostCall, 'A value should be cached but it is empty for the id '.$actualCacheId.': '.var_export($estimatePostCall, true));

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

        $quote
            ->removeAllAddresses()
            ->removeAllItems()
            ->setCustomerId(32)
            ->setCustomerTaxClassId(2);

        foreach(self::$_productIds as $productId) {
            $this->testHelper->addProduct($productId, rand(1,3));
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals()->save();

        $reflectedShippingController = new ReflectionClass($this->_shippingController);
        $reflectedGetEstimateCacheIdentifier = $reflectedShippingController->getMethod('getEstimateCacheIdentifier');
        $reflectedGetEstimateCacheIdentifier->setAccessible(true);


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

        $originalAddressExpectedCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $originalMagentoFormatAddressData);

        $mockBoltRequestData = $originalMockBoltRequestData = array(
            'cart' =>
                array(
                    'display_id' => 'mock quote id |'.$quote->getId()
                ),
            'shipping_address' => $boltFormatShippingAddress
        );


        $reflectedRequestJson = $reflectedShippingController->getProperty('_requestJSON');
        $reflectedRequestJson->setAccessible(true);
        $reflectedRequestJson->setValue($this->_shippingController, json_encode($mockBoltRequestData));

        ////////////////////////////////////////////////////////
        // Make first call that should not have a cache value
        ////////////////////////////////////////////////////////
        $this->_shippingController->indexAction();
        $firstCallEstimate = json_decode($this->_shippingController->getResponse()->getBody(), true);
        $firstCallHeaders = $this->_shippingController->getResponse()->getHeaders();
        foreach($firstCallHeaders as $callHeader) {
            if ($callHeader['name'] === 'X-Bolt-Cache-Hit') {
                $firstCallHitOrMiss = $callHeader['value'];
            }
        }
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a second call that should be read from the cache
        ////////////////////////////////////////////////////////
        $this->_shippingController->indexAction();
        $secondCallEstimate = json_decode($this->_shippingController->getResponse()->getBody(), true);
        $secondCallHeaders = $this->_shippingController->getResponse()->getHeaders();
        foreach($secondCallHeaders as $callHeader) {
            if ($callHeader['name'] === 'X-Bolt-Cache-Hit') {
                $secondCallHitOrMiss = $callHeader['value'];
            }
        }
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a third call with a different address.
        // It should not be read from the cache
        ////////////////////////////////////////////////////////
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
        $modifiedAddressExpectedCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $modifiedMagentoFormatAddressData);


        $reflectedRequestJson->setValue($this->_shippingController, json_encode($mockBoltRequestData));

        $this->_shippingController->indexAction();
        $thirdCallEstimate = json_decode($this->_shippingController->getResponse()->getBody(), true);
        $thirdCallHeaders = $this->_shippingController->getResponse()->getHeaders();
        foreach($thirdCallHeaders as $callHeader) {
            if ($callHeader['name'] === 'X-Bolt-Cache-Hit') {
                $thirdCallHitOrMiss = $callHeader['value'];
            }
        }
        ////////////////////////////////////////////////////////


        ////////////////////////////////////////////////////////
        // Make a fourth call with the original data
        // It should be read from the cache
        ////////////////////////////////////////////////////////
        $reflectedRequestJson->setValue($this->_shippingController, json_encode($originalMockBoltRequestData));

        $this->_shippingController->indexAction();
        $fourthCallEstimate = json_decode($this->_shippingController->getResponse()->getBody(), true);
        $fourthCallHeaders = $this->_shippingController->getResponse()->getHeaders();
        foreach($fourthCallHeaders as $callHeader) {
            if ($callHeader['name'] === 'X-Bolt-Cache-Hit') {
                $fourthCallHitOrMiss = $callHeader['value'];
            }
        }
        ////////////////////////////////////////////////////////


        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

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
}