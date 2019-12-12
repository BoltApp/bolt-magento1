<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Controller_Traits_OrderControllerTrait
 */
class Bolt_Boltpay_Controller_Traits_OrderControllerTraitAlternativeTest extends PHPUnit_Framework_TestCase
{
    private $app;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }

    /**
     * @test
     * @group inProgress
     * @dataProvider createActionCases
     */
    public function createAction(array $case)
    {
        $methods = array(
            'getRequest',
            'getLayout',
            'prepareAddressData',
            '_getOrderCreateModel',
            'getCartData',
            'boltHelper',
            'getResponse'
        );
        // Additional mocks
        $requestMock = $this->getMockBuilder(Mage_Core_Controller_Request_Http::class)->setMethods(array('getParam', 'isAjax'))->getMock();
        $responseMock = $this->getMockBuilder(Mage_Core_Controller_Response_Http::class)->setMethods(null)->getMock();
        $layoutMock = $this->getMockBuilder(Mage_Core_Model_Layout::class)->disableOriginalConstructor()->setMethods(array('createBlock'))->getMock();
        $quoteMock = $this->getMockBuilder(Mage_Sales_Model_Quote::class)->getMock();
        $blockMock = $this->getMockBuilder(Bolt_Boltpay_Block_Checkout_Boltpay::class)->setMethods(array('getSessionQuote'))->getMock();
        
        // Mocks behaviour
        $requestMock->method('isAjax')->willReturn($case['is_ajax']);
        $requestMock->method('getParam')->will($this->returnValue($case['checkout_type']));
        $blockMock->method('getSessionQuote')->with($case['checkout_type'])->willReturn($quoteMock);
        $layoutMock->method('createBlock')->with('boltpay/checkout_boltpay')->willReturn($blockMock);
        // Main mock
        $controller = $this->getMockForTrait(Bolt_Boltpay_Controller_Traits_OrderControllerTrait::class,
            array(),
            '',
            false,
            false,
            false,
            $methods,
            false
        );
        // Controller behaviour
        $controller->method('getLayout')->will($this->returnValue($layoutMock));
        $controller->method('getRequest')->will($this->returnValue($requestMock));
        $controller->method('getCartData')->will($this->returnValue($case['cart_data']));
        $controller->method('getResponse')->will($this->returnValue($responseMock));
        
        // Start test
        $controller->createAction();
        // Assertions
        $headers = $responseMock->getHeaders();
        $this->assertInternalType('array', $headers);
        $this->assertEquals($case['headers'], $headers);
        $body = $responseMock->getBody();
        $this->assertInternalType('string', $body);
        $this->assertEquals($case['body'], $body);
    }

    /**
     * Test cases
     * @return array
     */
    public function createActionCases()
    {
        return array(
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'multi-page',
                    'cart_data' => array('success' => true),
                    'headers' => array(
                        array(
                            'name' => "Content-Type",
                            'value' => "application/json",
                            'replace' => true
                        )
                    ),
                    'body' => '{"cart_data":{"success":true}}'
                )
            ),
//             array(
//                 'case' => array(
//                     'is_ajax' => true,
//                     'checkout_type' => 'admin',
//                     'cart_data' => array('success' => true),
//                     'headers' => array(
//                         array(
//                             'name' => "Content-Type",
//                             'value' => "application/json",
//                             'replace' => true
//                         )
//                     ),
//                     'body' => '{"cart_data":{"success":true}}'
//                 )
//             ),
            
        );
    }
}
