<?php

require_once 'Bolt/Boltpay/controllers/Adminhtml/Sales/Order/CreateController.php';

/**
 * Class Bolt_Boltpay_Adminhtml_Sales_Order_CreateControllerTest
 */
class Bolt_Boltpay_Adminhtml_Sales_Order_CreateControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var
     */
    private $currentMock;

    public function setUp()
    {
    }

    public function testLoadBlockAction()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Adminhtml_Sales_Order_CreateController')
            ->setMethods(['getRequest', 'getLayout', 'getResponse', 'prepareAddressData'])
            ->enableOriginalConstructor()
            ->getMock();

        $request = new Mage_Core_Controller_Request_Http();
        $request->setPost('order', ['some' => 'value1']);

        $layout =  new Mage_Core_Model_Layout();
        $layout->createBlock('Mage_Core_Block_Template', 'content');

        $response = new Mage_Core_Controller_Response_Http();

        $this->currentMock->method('getRequest')
            ->willReturn($request);
        $this->currentMock->method('getLayout')
            ->willReturn($layout);
        $this->currentMock->method('getResponse')
            ->willReturn($response);

        $expected = [
            'street_address1' => 'Test street 123',
            'street_address2' => 'Additional street 007',
            'street_address3' => null,
            'street_address4' => null,
            'first_name'      => 'TesterFn',
            'last_name'       => 'TesterLn',
            'locality'        => 'Los Angeles',
            'region'          => 'CA',
            'postal_code'     => '10011',
            'country_code'    => 'US',
            'phone'           => '+1 123 111 3333',
            'phone_number'    => '+1 123 111 3333',
        ];

        $this->currentMock->method('prepareAddressData')
            ->willReturn($expected);


        $this->currentMock->loadBlockAction();
        $result = Mage::getSingleton('admin/session')->getOrderShippingAddress();

        $this->assertEquals($expected, $result);
    }

    public function testPrepareAddressData()
    {
        $actual = [
            'street_address1' => 'Test street 123',
            'street_address2' => 'Additional street 007',
            'street_address3' => null,
            'street_address4' => null,
            'first_name'      => 'TesterFn',
            'last_name'       => 'TesterLn',
            'locality'        => 'Los Angeles',
            'region'          => 'CA',
            'postal_code'     => '10011',
            'country_code'    => 'US',
            'phone'           => '+1 123 111 3333',
            'phone_number'    => '+1 123 111 3333',
        ];

        $postData = [
            'shipping_address' => [
                'street' => [
                    'Test street 123',
                    'Additional street 007',
                ],
                'firstname'     => 'TesterFn',
                'lastname'      => 'TesterLn',
                'city'          => 'Los Angeles',
                'region_id'     => 12, // Region: CA
                'postcode'      => '10011',
                'country_id'    => 'US',
                'telephone'     => '+1 123 111 3333',
            ]
        ];

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Adminhtml_Sales_Order_CreateController')
            ->setMethods(['getRequest'])
            ->enableOriginalConstructor()
            ->getMock();

        $result = $this->currentMock->prepareAddressData($postData);

        $this->assertEquals($actual, $result);
    }

    public function testSaveActionWhereBoltReferenceIsFalse()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Adminhtml_Sales_Order_CreateController')
            ->setMethods(['getRequest', 'getResponse', '_redirect'])
            ->enableOriginalConstructor()
            ->getMock();

        $request = new Mage_Core_Controller_Request_Http();

        $response = new Mage_Core_Controller_Response_Http();

        $this->currentMock->method('getRequest')
            ->willReturn($request);

        $this->currentMock->method('getResponse')
            ->willReturn($response);

        $this->currentMock->method('_redirect')
            ->willReturn(false);

        $result = $this->currentMock->saveAction();

        $this->assertNull($result);
    }

    public function testSaveAction()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Adminhtml_Sales_Order_CreateController')
            ->setMethods([
                'getRequest', '_redirect', '_processActionData', '_getSession',
                'getImmutableQuoteIdFromTransaction', '_normalizeOrderData',
                '_getOrderCreateModel', '_postOrderCreateProcessing'
            ])
            ->enableOriginalConstructor()
            ->getMock();

        $session = new Mage_Adminhtml_Model_Session_Quote();
        $request = new Mage_Core_Controller_Request_Http();
        $request->setPost('bolt_reference', 'AAAA-BBBB-1234');
        $request->setPost('payment', false);

        $this->currentMock->method('getRequest')
            ->willReturn($request);

        $this->currentMock->method('_normalizeOrderData')
            ->willReturn(false);
        $this->currentMock->method('_processActionData')
            ->willReturn(true);

        $this->currentMock->method('_redirect')
            ->willReturn(false);

        $this->currentMock->method('_getSession')
            ->willReturn($session);

        $orderCreateMock = $this->getMockBuilder('Mage_Adminhtml_Model_Sales_Order_Create')
            ->setMethods(['importPostData', 'setIsValidate', 'createOrder'])
            ->getMock();

        $orderCreateMock->method('setIsValidate')
            ->willReturnSelf();

        $orderCreateMock->method('importPostData')
            ->willReturnSelf();

        $orderCreateMock->method('createOrder')
            ->willReturnSelf();

        $this->currentMock->method('_getOrderCreateModel')
            ->willReturn($orderCreateMock);

        $this->currentMock->method('getImmutableQuoteIdFromTransaction')
            ->willReturn(100);

        $this->currentMock->method('_postOrderCreateProcessing')
            ->willReturn(true);

        $this->currentMock->saveAction();

        /** @var Mage_Core_Model_Session_Abstract $msg */
        $adminSession = Mage::getSingleton('adminhtml/session');

        $result = $adminSession->getMessages()->getItems();

        $this->assertEquals('The order has been created.', $result[0]->getCode());
    }
}
