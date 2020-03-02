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

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        /** @var Mage_Core_Model_Store $appStore */
        $appStore = $this->app->getStore();
        $appStore->resetConfig();

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_ConfigTrait')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(['getPaymentBoltpayConfig'])
            ->getMockForTrait();

        $appStore->setConfig('payment/boltpay/active', 1);
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes returns empty string if config is empty
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_returnsEmptyString_forEmptyConfig()
    {
        $this->currentMock->expects($this->once())
            ->method('getPaymentBoltpayConfig')
            ->with('allowed_button_by_custom_routes', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->will($this->returnValue(''));

        $this->assertEmpty($this->currentMock->getAllowedButtonByCustomRoutes());
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes returns array of values split by comma and trimmed with space only values excluded
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_returnsCorrectResult_forCommaSeparatedValues()
    {
        $this->currentMock->expects($this->once())
            ->method('getPaymentBoltpayConfig')
            ->with('allowed_button_by_custom_routes', Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE)
            ->will($this->returnValue("testroute, test-route, \r, \n, \r\n, 0, a"));

        $result = ['testroute', 'test-route', '0', 'a'];

        $this->assertEquals($result, $this->currentMock->getAllowedButtonByCustomRoutes());
    }

    /**
     * @test
     * getAllowedButtonByCustomRoutes does not split by space
     *
     * @covers Bolt_Boltpay_Helper_ConfigTrait::getAllowedButtonByCustomRoutes
     */
    public function getAllowedButtonByCustomRoutes_doesNotSplitBySpace_forValuesWithoutComma()
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
     * @param $active isBoltPayActive
     * @param $isEverywhere shouldAddButtonEverywhere
     * @param $expected expected result
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
     *
     * @return array[]
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
}
