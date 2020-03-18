<?php

require_once('TestHelper.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_ConfigTrait
 */
class Bolt_Boltpay_Helper_ConfigTraitTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Bolt_Boltpay_Helper_ConfigTrait
     */
    private $currentMock;

    public function setUp()
    {
        Mage::app('default')->getStore()->resetConfig();
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_ConfigTrait')->getMockForTrait();
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes returns empty string if config is empty
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_returnsEmptyString_forEmptyConfig()
    {
        TestHelper::stubConfigValue('payment/boltpay/allowed_button_by_custom_routes', '');
        $this->assertEmpty($this->currentMock->getAllowedButtonByCustomRoutes());
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes returns array of values split by comma and trimmed with space only values excluded
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_returnsCorrectResult_forCommaSeparatedValues()
    {
        TestHelper::stubConfigValue(
            'payment/boltpay/allowed_button_by_custom_routes',
            "testroute, test-route, \r, \n, \r\n, 0, a"
        );
        $result = ['testroute', 'test-route', '0', 'a'];
        $this->assertEquals($result, $this->currentMock->getAllowedButtonByCustomRoutes());
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes does not split by space
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_doesNotSplitBySpace_forValuesWithoutComma()
    {
        TestHelper::stubConfigValue('payment/boltpay/allowed_button_by_custom_routes', 'testroute test-route');
        $result = ['testroute test-route'];
        $this->assertEquals($result, $this->currentMock->getAllowedButtonByCustomRoutes());
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * getBoltPluginVersion when version is not set in config
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
     * getBoltPluginVersion when version is set in config
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

    /**
     * @test
     * getSeverityConfig returns config with spaces removed and split by comma
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getSeverityConfig
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function getSeverityConfig_returnsCorrectResult()
    {
        $datadogConfig = json_encode(array('datadogKeySeverity' => '  error , info  , warning   '));
        TestHelper::stubConfigValue('payment/boltpay/extra_options', $datadogConfig);
        $this->assertEquals(['error', 'info', 'warning'], $this->currentMock->getSeverityConfig());
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * canUseEverywhere returns the correct result for various configs
     *
     * @covers       Bolt_Boltpay_Helper_ConfigTrait::canUseEverywhere
     *
     * @dataProvider canUseEverywhere_withVariousConfigs_returnsCorrectResultProvider
     *
     * @param $active
     * @param $isEverywhere
     * @param $expected
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function canUseEverywhere_withVariousConfigs_returnsCorrectResult($active, $isEverywhere, $expected)
    {
        TestHelper::stubConfigValue('payment/boltpay/active', $active);
        TestHelper::stubConfigValue('payment/boltpay/add_button_everywhere', $isEverywhere);
        $this->assertEquals($expected, $this->currentMock->canUseEverywhere());
        TestHelper::restoreOriginals();
    }

    /**
     * Data provider for {@see canUseEverywhere_withVariousConfigs_returnsCorrectResult}
     */
    public function canUseEverywhere_withVariousConfigs_returnsCorrectResultProvider()
    {
        return array(
            array('true', 'true', true),
            array('true', 'false', false),
            array('false', 'true', false),
            array('false', 'false', false),
        );
    }

    /**
     * @test
     * canUseForCountry returns false if Bolt is not active
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::canUseForCountry
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function canUseForCountry_ifBoltIsNotActive_returnsFalse()
    {
        TestHelper::stubConfigValue('payment/boltpay/active', 'false');
        $this->assertFalse($this->currentMock->canUseForCountry('country'));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * canUseForCountry returns true if skip payment is true
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::canUseForCountry
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function canUseForCountry_ifSkipPaymentIsTrue_returnsTrue()
    {
        TestHelper::stubConfigValue('payment/boltpay/active', 'true');
        TestHelper::stubConfigValue('payment/boltpay/skip_payment', 1);
        $this->assertTrue($this->currentMock->canUseForCountry('country'));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * canUseForCountry returns true if allow specific is false
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::canUseForCountry
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function canUseForCountry_ifAllowSpecificIsFalse_returnsTrue()
    {
        TestHelper::stubConfigValue('payment/boltpay/active', 'true');
        TestHelper::stubConfigValue('payment/boltpay/skip_payment', 0);
        TestHelper::stubConfigValue('payment/boltpay/allowspecific', 0);
        $this->assertTrue($this->currentMock->canUseForCountry('country'));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * canUseForCountry returns whether country is found if allow specific is true
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::canUseForCountry
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function canUseForCountry_ifAllowSpecificIsTrue_returnsWhetherCountryIsFound()
    {
        TestHelper::stubConfigValue('payment/boltpay/active', 'true');
        TestHelper::stubConfigValue('payment/boltpay/skip_payment', 0);
        TestHelper::stubConfigValue('payment/boltpay/allowspecific', 1);
        TestHelper::stubConfigValue('payment/boltpay/specificcountry', 'canada,usa,china');
        $this->assertTrue($this->currentMock->canUseForCountry('canada'));
        TestHelper::stubConfigValue('payment/boltpay/specificcountry', 'mexico,australia');
        $this->assertFalse($this->currentMock->canUseForCountry('canada'));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * getPaymentBoltpayConfig returns the correct result for various configs
     *
     * @covers       Bolt_Boltpay_Helper_ConfigTrait::getPaymentBoltpayConfig
     *
     * @dataProvider getPaymentBoltpayConfig_withVariousParams_returnsCorrectResultProvider
     *
     * @param $checkoutType
     * @param $useJsInAdmin
     * @param $expected
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function getPaymentBoltpayConfig_withVariousParams_returnsCorrectResult($checkoutType, $useJsInAdmin, $expected)
    {
        TestHelper::stubConfigValue('payment/boltpay/use_javascript_in_admin', $useJsInAdmin);
        TestHelper::stubConfigValue('payment/boltpay/specificcountry', 'canada,usa,china');
        $this->assertEquals($expected, $this->currentMock->getPaymentBoltpayConfig('specificcountry', $checkoutType));
        TestHelper::restoreOriginals();
    }

    /**
     * Data provider for {@see getPaymentBoltpayConfig_withVariousParams_returnsCorrectResult}
     */
    public function getPaymentBoltpayConfig_withVariousParams_returnsCorrectResultProvider()
    {
        return array(
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN, true, 'canada,usa,china'),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN, false, ''),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE, true, 'canada,usa,china'),
            array(Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE, false, 'canada,usa,china')
        );
    }

    /**
     * @test
     * various one line methods return the correct config values
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getPublishableKeyMultiPage
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getPublishableKeyOnePage
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getPublishableKeyBackOffice
     * @covers Bolt_Boltpay_Helper_ConfigTrait::shouldAddButtonEverywhere
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getBoltPrimaryColor
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getApiKeyConfig
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAdditionalButtonClasses
     * @covers Bolt_Boltpay_Helper_ConfigTrait::isEnabledProductPageCheckout
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getProductPageCheckoutSelector
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAutoCreateInvoiceAfterCreatingShipment
     *
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     */
    public function simpleConfigMethods_returnsCorrectConfigValue()
    {
        TestHelper::stubConfigValue('payment/boltpay/publishable_key_multipage', 'multipage key');
        TestHelper::stubConfigValue('payment/boltpay/publishable_key_onepage', 'onepage key');
        TestHelper::stubConfigValue('payment/boltpay/publishable_key_admin', 'backoffice key');
        TestHelper::stubConfigValue('payment/boltpay/add_button_everywhere', 'true');
        TestHelper::stubConfigValue('payment/boltpay/extra_options', json_encode(array(
            'boltPrimaryColor' => '#000000',
            'datadogKey' => 'datadog key'
        )));
        TestHelper::stubConfigValue('payment/boltpay/button_classes', 'btnClass');
        TestHelper::stubConfigValue('payment/boltpay/enable_product_page_checkout', 'true');
        TestHelper::stubConfigValue('payment/boltpay/product_page_checkout_selector', '.ppcBtn');
        TestHelper::stubConfigValue('payment/boltpay/auto_create_invoice_after_creating_shipment', true);

        $this->assertEquals('multipage key', $this->currentMock->getPublishableKeyMultiPage());
        $this->assertEquals('onepage key', $this->currentMock->getPublishableKeyOnePage());
        $this->assertEquals('backoffice key', $this->currentMock->getPublishableKeyBackOffice());
        $this->assertTrue($this->currentMock->shouldAddButtonEverywhere());
        $this->assertEquals('#000000', $this->currentMock->getBoltPrimaryColor());
        $this->assertEquals('datadog key', $this->currentMock->getApiKeyConfig());
        $this->assertEquals('btnClass', $this->currentMock->getAdditionalButtonClasses());
        $this->assertTrue($this->currentMock->isEnabledProductPageCheckout());
        $this->assertEquals('.ppcBtn', $this->currentMock->getProductPageCheckoutSelector());
        $this->assertTrue($this->currentMock->getAutoCreateInvoiceAfterCreatingShipment());

        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * various one line methods return the correct config values
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::isBoltPayActive
     *
     * @dataProvider isBoltPayActiveProvider
     *
     * @param bool $isBoltActiveLocally      true if the Bolt plugin is activated by the local config, otherwise false
     * @param bool $isBoltEnabledServerSide  true if the Bolt plugin is enabled at the Bolt server, otherwise false
     * @param bool $expectedValue            true if Bolt is enabled both locally and via remote switch, otherwise false
     *
     * @throws Mage_Core_Exception              if there is an issue stubbing singleton Bolt_Boltpay_Model_FeatureSwitch
     * @throws Mage_Core_Model_Store_Exception  if there is an issue stubbing config `payment/boltpay/active`
     */
    public function isBoltPayActive_withVariousLocalAndBoltDefinedValues_returnsCorrectValue($isBoltActiveLocally, $isBoltEnabledServerSide, $expectedValue)
    {
        TestHelper::stubConfigValue('payment/boltpay/active', $isBoltActiveLocally);

        $featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('isSwitchEnabled'))->getMock();
        $featureSwitchMock->expects($this->once())
            ->method('isSwitchEnabled')
            ->with(Bolt_Boltpay_Model_FeatureSwitch::BOLT_ENABLED_SWITCH_NAME)
            ->willReturn($isBoltEnabledServerSide);
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $featureSwitchMock);

        $this->assertEquals($expectedValue, $this->currentMock->isBoltPayActive());
        TestHelper::restoreOriginals();
    }

    /**
     * Data provider for {@see isBoltPayActive_withVariousLocalAndBoltDefinedValues_returnsCorrectValue}
     *
     * @return array containing $isBoltActiveLocally, $isBoltEnabledServerSide, $expectedValue
     */
    public function isBoltPayActiveProvider() {
        return array(
            array(
                'isBoltActiveLocally' => true,
                'isBoltEnabledServerSide' => true,
                'expectedValue' => true
            ),
            array(
                'isBoltActiveLocally' => true,
                'isBoltEnabledServerSide' => false,
                'expectedValue' => false
            ),
            array(
                'isBoltActiveLocally' => false,
                'isBoltEnabledServerSide' => true,
                'expectedValue' => false
            ),
            array(
                'isBoltActiveLocally' => false,
                'isBoltEnabledServerSide' => false,
                'expectedValue' => false
            )
        );
    }
}
