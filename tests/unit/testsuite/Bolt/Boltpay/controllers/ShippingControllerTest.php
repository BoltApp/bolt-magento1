<?php

require_once 'Bolt/Boltpay/controllers/ShippingController.php';

class Bolt_Boltpay_ShippingControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var Bolt_Boltpay_ShippingController $_shippingController */
    protected $_shippingController;

    /**
     * Sets up a shipping controller that mocks Bolt HMAC request validation.
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
        $reflectedBoltApiHelper->setValue($stubbedBoltApiHelper);

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

        $quote->setCustomerId(25)
            ->setCustomerTaxClassId(3)
            ->setGrandTotal(63.48);


        $reflectedShippingController = new ReflectionClass($this->_shippingController);

        $reflectedRequestJson = $reflectedShippingController->getProperty('_requestJSON');
        $reflectedRequestJson->setAccessible(true);

        $addressData = array(
            'email' => 'test_shipping_and_tax_cache@bolt.com',
            'firstname'  => 'Post',
            'lastname'   => 'Man',
            'street'     => 'Blues Street 10' . "\n" . '65th Floor' . "\n" . 'Apt 657' . "\n" . 'Attention: Tax Man',
            'city'       => 'Beverly Hills',
            'telephone'  => '+1 877 345 123 5681',
            'country_id' => 'US',
            'company' => 'Bolt',
            'region_id'  => '12',
            'region' => 'California'
        );

        $reflectedRequestJson->setValue(json_encode($addressData));

        $reflectedGetEstimateCacheIdentifier = $reflectedShippingController->getMethod('getEstimateCacheIdentifier');
        $reflectedGetEstimateCacheIdentifier->setAccessible(true);

        $preCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $addressData);

        $estimatePreCall = unserialize(Mage::app()->getCache()->load($preCacheId));

        try {
            $this->_shippingController->prefetchEstimateAction();
        } catch (Zend_Controller_Response_Exception $e ) {
            // we are not interested in server responses in this context, so we can ignore
            // test environment HTTP Response errors.
        }

        $postCacheId = $reflectedGetEstimateCacheIdentifier->invoke($this->_shippingController, $quote, $addressData);

        $estimatePostCall = unserialize(Mage::app()->getCache()->load($postCacheId));

        Mage::app()->getCache()->clean('matchingAnyTag', array('BOLT_QUOTE_PREFETCH'));

        $this->assertEquals($preCacheId, $postCacheId);

        $this->assertEmpty( $estimatePreCall, 'A value is cached but there should be no cached value for the id '.$preCacheId);

        $this->assertNotEmpty( $estimatePostCall, 'A value should be cached but it is empty for the id '.$postCacheId.': '.var_export($estimatePostCall, true));

    }
}