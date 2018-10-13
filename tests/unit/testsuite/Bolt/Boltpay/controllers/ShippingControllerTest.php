<?php

require_once 'Bolt/Boltpay/controllers/ShippingController.php';

class Bolt_Boltpay_ShippingControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var Bolt_Boltpay_ShippingController $_shippingController */
    protected $_shippingController;

    public function setUp()
    {
        $this->_shippingController = new Bolt_Boltpay_ShippingController(
            new Mage_Core_Controller_Request_Http(),
            new Mage_Core_Controller_Response_Http()
        );
    }

    public function testPOBoxMatchesCases()
    {
        $doesAddressContainPOBoxMethod = new ReflectionMethod($this->_shippingController, 'doesAddressContainPOBox');
        $doesAddressContainPOBoxMethod->setAccessible(true);

        $address1 = 'P.O. Box 66';
        $address2 = 'Post Box 123';
        $address3 = 'Post Office Box 456';
        $address4 = 'PO Box 456';
        $address5 = 'PO Box #456';
        $address6 = 'Post Office Box #456';
        $check1 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address1);
        $check2 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address2);
        $check3 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address3);
        $check4 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address4);
        $check5 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address5);
        $check6 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address6);
        $additionalCheck1 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address1, $address2);
        $additionalCheck2 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address1, $address3);
        $additionalCheck3 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address2, $address3);

        $this->assertTrue($check1);
        $this->assertTrue($check2);
        $this->assertTrue($check3);
        $this->assertTrue($check4);
        $this->assertTrue($check5);
        $this->assertTrue($check6);
        $this->assertTrue($additionalCheck1);
        $this->assertTrue($additionalCheck2);
        $this->assertTrue($additionalCheck3);
    }

    public function testPOBoxDoesNotMatchCases()
    {
        $doesAddressContainPOBoxMethod = new ReflectionMethod($this->_shippingController, 'doesAddressContainPOBox');
        $doesAddressContainPOBoxMethod->setAccessible(true);
        
        $address1 = 'Post street';
        $address2 = '2 Box';
        $address3 = '425 Sesame St';
        $check1 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address1);
        $check2 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address2);
        $check3 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address3);
        $additionalCheck1 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address1, $address2);
        $additionalCheck2 = $doesAddressContainPOBoxMethod->invoke($this->_shippingController, $address2, $address3);

        $this->assertNotTrue($check1);
        $this->assertNotTrue($check2);
        $this->assertNotTrue($check3);
        $this->assertNotTrue($additionalCheck1);
        $this->assertNotTrue($additionalCheck2);
    }
}