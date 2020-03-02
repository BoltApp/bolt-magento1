<?php

require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

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

    /**
     * @test
     * Retrieving Bolt version when it is unavailable in Magento config
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion
     *
     * @throws ReflectionException if Mage::_config doesn't have _xml property
     */
    public function getBoltPluginVersion_withoutConfigValueSet_returnsNull()
    {
        $config = Mage::getConfig();

        $oldXml = TestHelper::getNonPublicProperty($config, '_xml');
        $newXml = new Varien_Simplexml_Element(/** @lang XML */ '<modules/>');
        $newXml->setNode('modules/Bolt_Boltpay', null);
        TestHelper::setNonPublicProperty($config, '_xml', $newXml);

        $this->assertNull(Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion());

        TestHelper::setNonPublicProperty($config, '_xml', $oldXml);
    }

    /**
     * @test
     * Retrieving Bolt version when it is set in Magento config
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion
     *
     * @throws ReflectionException if Mage::_config doesn't have _xml property
     */
    public function getBoltPluginVersion_withConfigValueSet_returnsSetValue()
    {
        $config = Mage::getConfig();
        $configXml = TestHelper::getNonPublicProperty($config, '_xml');
        $version = (string)$configXml->modules->Bolt_Boltpay->version;

        $this->assertNotEmpty($version);
        $this->assertEquals($version, Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion());
    }
}
