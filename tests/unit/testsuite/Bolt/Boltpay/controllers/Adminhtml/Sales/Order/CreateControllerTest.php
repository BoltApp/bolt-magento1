<?php

require_once 'Bolt/Boltpay/controllers/Adminhtml/Sales/Order/CreateController.php';

class Bolt_Boltpay_Adminhtml_Sales_Order_CreateControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var Bolt_Boltpay_Adminhtml_Sales_Order_CreateController $_createController */
    protected $_createController;

    public function setUp()
    {
        $request = new Mage_Core_Controller_Request_Http();
        $response = new Mage_Core_Controller_Response_Http();
        $invokeArgs = [];
        $this->_createController = new Bolt_Boltpay_Adminhtml_Sales_Order_CreateController(
            $request,
            $response,
            $invokeArgs
        );
    }

    public function testLoadBlockAction()
    {

    }
}
