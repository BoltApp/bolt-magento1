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
     * @group        OrderControllerTrait
     * @group        Trait
     * @dataProvider createActionCases
     * @param array $case
     */
    public function createAction(array $case)
    {
        // Additional mocks
        $requestMock = $this->getMockBuilder(Mage_Core_Controller_Request_Http::class)
            ->setMethods(array('getParam', 'isAjax'))
            ->getMock();
        $responseMock = $this->getMockBuilder(Mage_Core_Controller_Response_Http::class)
            ->setMethods(null)
            ->getMock();
        $layoutMock = $this->getMockBuilder(Mage_Core_Model_Layout::class)
            ->disableOriginalConstructor()
            ->setMethods(array('createBlock'))
            ->getMock();
        $quoteMock = $this->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->getMock();
        $blockMock = $this->getMockBuilder(Bolt_Boltpay_Block_Checkout_Boltpay::class)
            ->setMethods(array('getSessionQuote'))
            ->getMock();
        $orderCreateModelMock = $this->getMockBuilder(Mage_Adminhtml_Model_Sales_Order_Create::class)
            ->setMethods(array('initRuleData'))
            ->getMock();

        // Mocks behaviour
        $requestMock->method('isAjax')->willReturn($case['is_ajax']);
        $requestMock->method('getParam')->will($this->returnValue($case['checkout_type']));
        $blockMock->method('getSessionQuote')->with($case['checkout_type'])->willReturn($quoteMock);
        $layoutMock->method('createBlock')->with('boltpay/checkout_boltpay')->willReturn($blockMock);
        $orderCreateModelMock->method('initRuleData')->willReturnSelf();
        // Main mock
        $controller = $this->getMockForTrait(Bolt_Boltpay_Controller_Traits_OrderControllerTrait::class,
            array(),
            '',
            false,
            false,
            false,
            array(
                'getRequest', 'getLayout', '_getOrderCreateModel', 'getCartData',
                'boltHelper', 'getResponse'
            ),
            false
        );
        // Controller behaviour
        $controller->method('getLayout')->will($this->returnValue($layoutMock));
        $controller->method('getRequest')->will($this->returnValue($requestMock));
        $controller->method('getCartData')->will($this->returnValue($case['cart_data']));
        $controller->method('getResponse')->will($this->returnValue($responseMock));
        $controller->method('_getOrderCreateModel')->will($this->returnValue($orderCreateModelMock));

        // Start test
        $controller->createAction();
        // Assertions
        $headers = $responseMock->getHeaders();
        $this->assertInternalType('array', $headers);
        $this->assertEquals($case['expect']['headers'], $headers);
        $body = $responseMock->getBody();
        $this->assertInternalType('string', $body);
        $this->assertEquals($case['expect']['body'], $body);
    }

    /**
     * Test cases.
     *
     * @return array
     */
    public function createActionCases()
    {
        return array(
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => '',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'multi-page',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'admin',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'one-page',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'firecheckout',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'product-page',
                    'cart_data' => array('orderToken' => 'bolt'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"orderToken":"bolt"}}'
                    )
                )
            ),
            // Dummy error response from getCartData method
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => '',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'multi-page',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'admin',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'one-page',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'firecheckout',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
            array(
                'case' => array(
                    'is_ajax' => true,
                    'checkout_type' => 'product-page',
                    'cart_data' => array('error' => 'dummy error message'),
                    'expect' => array(
                        'headers' => array(
                            array(
                                'name' => "Content-Type",
                                'value' => "application/json",
                                'replace' => true
                            )
                        ),
                        'body' => '{"cart_data":{"error":"dummy error message"},"success":false,"error":true,"error_messages":"dummy error message"}'
                    )
                )
            ),
        );
    }
}
