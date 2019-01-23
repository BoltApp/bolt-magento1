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

        $stubbedBoltApiHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(array('verify_hook'))
            ->getMock();

        $stubbedBoltApiHelper->method('verify_hook')->willReturn(true);

        $reflectedShippingController = new ReflectionClass($this->_shippingController);

        $reflectedBoltApiHelper = $reflectedShippingController->getProperty('_boltApiHelper');
        $reflectedBoltApiHelper->setAccessible(true);
        $reflectedBoltApiHelper->setValue()

    }
}