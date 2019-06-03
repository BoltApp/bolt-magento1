<?php
require_once('TestHelper.php');

class Bolt_Boltpay_Helper_ConfigTraitTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Bolt_Boltpay_Helper_ConfigTrait
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        /** @var Mage_Core_Model_Store $appStore */
        $appStore = $this->app->getStore();
        $appStore->resetConfig();

        $this->currentMock = $this->getMockForTrait('Bolt_Boltpay_Helper_ConfigTrait',
            [],
            '',
            false,
            false,
            false,
            ['getPaymentBoltpayConfig'],
            false
        );

        $appStore->setConfig('payment/boltpay/active', 1);
    }

    public function testGetAllowedButtonByCustomRoutes_EmptyConfig()
    {
        $this->currentMock->expects($this->once())
            ->method('getPaymentBoltpayConfig')
            ->with('allowed_button_by_custom_routes', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->will($this->returnValue(''));

        $this->assertEmpty($this->currentMock->getAllowedButtonByCustomRoutes());
    }

    public function testGetAllowedButtonByCustomRoutes_WithValuesCommaSeparated()
    {
        $this->currentMock->expects($this->once())
            ->method('getPaymentBoltpayConfig')
            ->with('allowed_button_by_custom_routes', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->will($this->returnValue("testroute, test-route, \r, \n, \r\n, 0, a"));

        $result = ['testroute', 'test-route', '0', 'a'];

        $this->assertEquals($result, $this->currentMock->getAllowedButtonByCustomRoutes());
    }

    public function testGetAllowedButtonByCustomRoutes_WithValuesWithoutComma()
    {
        $this->currentMock->expects($this->once())
            ->method('getPaymentBoltpayConfig')
            ->with('allowed_button_by_custom_routes', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->will($this->returnValue('testroute test-route'));

        $result = ['testroute test-route'];

        $this->assertEquals($result, $this->currentMock->getAllowedButtonByCustomRoutes());
    }
}
