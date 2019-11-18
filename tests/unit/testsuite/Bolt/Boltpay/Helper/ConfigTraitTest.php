<?php
/**
 * Unit tests for Bolt_Boltpay_Helper_ConfigTrait class
 * @author aymelyanov <ayemelyanov@bolt.com>
 *
 */
class Bolt_Boltpay_Helper_ConfigTraitTest extends PHPUnit_Framework_TestCase
{
    private $app;

    private $mock;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->mock = $this->getMockForTrait(Bolt_Boltpay_Helper_ConfigTrait::class);
    }

    /**
     * @test
     * @group Trait
     * @dataProvider isBoltPayActiveCases
     * @param array $case
     */
    public function isBoltPayActive(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', $case['active']);
        $result = $this->mock->isBoltPayActive();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test Cases
     * @return array
     */
    public function isBoltPayActiveCases()
    {
        return array(
            array(
                'case' => array(
                    'active' => true,
                    'expect' => true
                )
            ),
            array(
                'case' => array(
                    'active' => false,
                    'expect' => false
                )
            ),
            array(
                'case' => array(
                    'active' => null,
                    'expect' => false
                )
            ),
            array(
                'case' => array(
                    'active' => '',
                    'expect' => false
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getPublishableKeyMultiPageCases
     * @param array $case
     */
    public function getPublishableKeyMultiPage(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_multipage', $case['key']);
        $result = $this->mock->getPublishableKeyMultiPage();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['key'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getPublishableKeyMultiPageCases()
    {
        return array(
            array(
                'case' => array(
                    'key' => 'this.is.test.key'
                )
            ),
            array(
                'case' => array(
                    'key' => ''
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getPublishableKeyOnePageCases
     * @param array $case
     */
    public function getPublishableKeyOnePage(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_onepage', $case['key']);
        $result = $this->mock->getPublishableKeyOnePage();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['key'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getPublishableKeyOnePageCases()
    {
        return array(
            array(
                'case' => array(
                    'key' => 'this.is.test-key'
                )
            ),
            array(
                'case' => array(
                    'key' => ''
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getPublishableKeyBackOfficeCases
     * @param array $case
     */
    public function getPublishableKeyBackOffice(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_admin', $case['key']);
        $result = $this->mock->getPublishableKeyBackOffice();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['key'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getPublishableKeyBackOfficeCases()
    {
        return array(
            array(
                'case' => array(
                    'key' => 'this-is.test.key'
                )
            ),
            array(
                'case' => array(
                    'key' => ''
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider shouldAddButtonEverywhereCases
     * @param array $case
     */
    public function shouldAddButtonEverywhere(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/add_button_everywhere', $case['value']);
        $result = $this->mock->shouldAddButtonEverywhere();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function shouldAddButtonEverywhereCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => true,
                    'value' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'value' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => 0
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => ''
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getBoltPrimaryColorCases
     * @param array $case
     */
    public function getBoltPrimaryColor(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/extra_options', $case['extra_options']);
        $result = $this->mock->getBoltPrimaryColor();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getBoltPrimaryColorCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'extra_options' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => '123456',
                    'extra_options' => '{"boltPrimaryColor": "123456"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => '#123456',
                    'extra_options' => '{"boltPrimaryColor": "#123456"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => '#FFFFFF',
                    'extra_options' => '{"boltPrimaryColor": "#ffffff"}'
                )
            ),
            
            array(
                'case' => array(
                    'expect' => '1234567',
                    'extra_options' => '{"boltPrimaryColor": "1234567"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => '12345678',
                    'extra_options' => '{"boltPrimaryColor": "12345678"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => '123456789',
                    'extra_options' => '{"boltPrimaryColor": "123456789"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'ZZZ',
                    'extra_options' => '{"boltPrimaryColor": "zzz"}'
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'extra_options' => "{'boltPrimaryColor': ''}"
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'extra_options' => "{'boltPrimaryColor': '123456'}"
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getAdditionalButtonClassesCases
     * @param array $case
     */
    public function getAdditionalButtonClasses(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/button_classes', $case['button_classes']);
        $result = $this->mock->getAdditionalButtonClasses();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getAdditionalButtonClassesCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'button_classes' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => '.btn-checkout',
                    'button_classes' => '.btn-checkout'
                )
            ),
            array(
                'case' => array(
                    'expect' => '.btn-checkout, .bolt-checkout',
                    'button_classes' => '.btn-checkout, .bolt-checkout'
                )
            ),
            array(
                'case' => array(
                    'expect' => '.btn-checkout,.bolt-checkout',
                    'button_classes' => '.btn-checkout,.bolt-checkout'
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider isEnabledProductPageCheckoutCases
     * @param array $case
     */
    public function isEnabledProductPageCheckout(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/enable_product_page_checkout', $case['value']);
        $result = $this->mock->isEnabledProductPageCheckout();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function isEnabledProductPageCheckoutCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'value' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => 0
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => null
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'value' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'value' => true
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'value' => 1
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'value' => '1'
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getProductPageCheckoutSelectorCases
     * @param array $case
     */
    public function getProductPageCheckoutSelector(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/product_page_checkout_selector', $case['value']);
        $result = $this->mock->getProductPageCheckoutSelector();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getProductPageCheckoutSelectorCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'value' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'value' => null
                )
            ),
            array(
                'case' => array(
                    'expect' => '',
                    'value' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => 'div',
                    'value' => 'div'
                )
            ),
            array(
                'case' => array(
                    'expect' => 'div, .btn',
                    'value' => 'div, .btn'
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getPaymentBoltpayConfigCases
     * @param array $case
     */
    public function getPaymentBoltpayConfig(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/use_javascript_in_admin', $case['js_in_admin']);
        $this->app->getStore()->setConfig('payment/boltpay/'.$case['config_path'], $case['value']);
        $result = $this->mock->getPaymentBoltpayConfig($case['config_path'], $case['checkout_type']);
        $this->assertInternalType($case['result_type'], $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getPaymentBoltpayConfigCases()
    {
        return array(
            array(
                'case' => array(
                    'result_type' => 'null',
                    'expect' => null,
                    'config_path' => '',
                    'value' => '',
                    'checkout_type' => '',
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '1',
                    'config_path' => 'active',
                    'value' => true,
                    'checkout_type' => '',
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '',
                    'config_path' => 'active',
                    'value' => false,
                    'checkout_type' => '',
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '',
                    'config_path' => 'active',
                    'value' => false,
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '',
                    'config_path' => 'active',
                    'value' => false,
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'js_in_admin' => true
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '1',
                    'config_path' => 'active',
                    'value' => true,
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ONE_PAGE,
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => '',
                    'config_path' => 'publishable_key_onepage',
                    'value' => 'result.must.be-empty',
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => 'result.must.be-expect',
                    'config_path' => 'publishable_key_onepage',
                    'value' => 'result.must.be-expect',
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE,
                    'js_in_admin' => false
                )
            ),
            array(
                'case' => array(
                    'result_type' => 'string',
                    'expect' => 'result.must.be-expect',
                    'config_path' => 'publishable_key_onepage',
                    'value' => 'result.must.be-expect',
                    'checkout_type' => Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN,
                    'js_in_admin' => true
                )
            ),
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getAllowedButtonByCustomRoutesCases
     * @param array $cases
     */
    public function getAllowedButtonByCustomRoutes(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/allowed_button_by_custom_routes', $case['allowed_button_by_custom_routes']);
        $result = $this->mock->getAllowedButtonByCustomRoutes();
        $this->assertInternalType('array', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getAllowedButtonByCustomRoutesCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => array(),
                    'allowed_button_by_custom_routes' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => array('/product/page'),
                    'allowed_button_by_custom_routes' => '/product/page'
                )
            ),
            array(
                'case' => array(
                    'expect' => array('/product/page', '/cart'),
                    'allowed_button_by_custom_routes' => '/product/page,/cart'
                )
            ),
            array(
                'case' => array(
                    'expect' => array('/product/page', '/cart'),
                    'allowed_button_by_custom_routes' => '           /product/page,            /cart  '
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider canUseForCountryCases
     * @param array $case
     */
    public function canUseForCountry(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', $case['active']);
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', $case['skip_payment']);
        $this->app->getStore()->setConfig('payment/boltpay/allowspecific', $case['allow_specific']);
        $this->app->getStore()->setConfig('payment/boltpay/specificcountry', $case['countries']);
        $result = $this->mock->canUseForCountry($case['country']);
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function canUseForCountryCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'active' => false,
                    'skip_payment' => false,
                    'allow_specific' => false,
                    'countries' => '',
                    'country' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'skip_payment' => false,
                    'allow_specific' => false,
                    'countries' => '',
                    'country' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'skip_payment' => true,
                    'allow_specific' => false,
                    'countries' => '',
                    'country' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => false,
                    'skip_payment' => true,
                    'allow_specific' => false,
                    'countries' => '',
                    'country' => ''
                )
            ),
            // strange
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'skip_payment' => false,
                    'allow_specific' => true,
                    'countries' => '',
                    'country' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => true,
                    'skip_payment' => false,
                    'allow_specific' => true,
                    'countries' => '',
                    'country' => 'US'
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'skip_payment' => false,
                    'allow_specific' => true,
                    'countries' => 'US,UA',
                    'country' => 'US'
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getExtraConfigCases
     * @param array $case
     */
    public function getExtraConfig(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/extra_options', $case['options']);
        $result = $this->mock->getExtraConfig($case['name'], $case['filter']);
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getExtraConfigCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => '',
                    'options' => '',
                    'name' => '',
                    'filter' => array()
                )
            ),
            array(
                'case' => array(
                    'expect' => '12345',
                    'options' => '{"name": "12345"}',
                    'name' => 'name',
                    'filter' => array()
                )
            ),
            array(
                'case' => array(
                    'expect' => '12345',
                    'options' => '{"name": "12345", "last": "789"}',
                    'name' => 'name',
                    'filter' => array()
                )
            ),
            array(
                'case' => array(
                    'expect' => 'fftryy',
                    'options' => '{"boltPrimaryColor": "FfTrYY", "last": "789"}',
                    'name' => 'boltPrimaryColor',
                    'filter' => array('case' => 'lower')
                )
            ),
            array(
                'case' => array(
                    'expect' => 'FFTRYY',
                    'options' => '{"boltPrimaryColor": "FfTrYY", "last": "789"}',
                    'name' => 'boltPrimaryColor',
                    'filter' => array('case' => 'UPPER')
                )
            ),
            array(
                'case' => array(
                    'expect' => 'FFTRYY',
                    'options' => '{"boltPrimaryColor": "FfTrYY", "last": "789"}',
                    'name' => 'boltPrimaryColor',
                    'filter' => array('parameter' => 'dummy')
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider canUseEverywhereCases
     * @param array $case
     */
    public function canUseEverywhere(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/active', $case['active']);
        $this->app->getStore()->setConfig('payment/boltpay/add_button_everywhere', $case['everywhere']);
        $this->app->getStore()->setConfig('payment/boltpay/enable_product_page_checkout', $case['ppc']);
        $result = $this->mock->canUseEverywhere();
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function canUseEverywhereCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'active' => '',
                    'everywhere' => '',
                    'ppc' => '',
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => true,
                    'everywhere' => false,
                    'ppc' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'active' => false,
                    'everywhere' => true,
                    'ppc' => false
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'active' => true,
                    'everywhere' => true,
                    'ppc' => true
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getApiKeyConfigCases
     * @param array $case
     */
    public function getApiKeyConfig(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/extra_options', $case['extra_options']);
        $result = $this->mock->getApiKeyConfig();
        $this->assertInternalType('string', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getApiKeyConfigCases()
    {
        return array(
            array(
                'case' => array(
                    // Looks like default value
                    'expect' => '66d80ae8d0278e3ee2d23e65649b7256',
                    'extra_options' => ''
                )
            ),
            array(
                'case' => array(
                    'expect' => 'test.key',
                    'extra_options' => '{"datadogKey": "test.key"}'
                )
            ),
            
        );
    }

    /**
     * @test
     * @group Trait
     * @dataProvider getSeverityConfigCases
     * @param array $case
     */
    public function getSeverityConfig(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/extra_options', $case['extra_options']);
        $result = $this->mock->getSeverityConfig();
        $this->assertInternalType('array', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     * @return array
     */
    public function getSeverityConfigCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => array(''),
                    'extra_options' => '{"datadogKeySeverity": ""}'
                )
            ),
            array(
                'case' => array(
                    'expect' => array('1', '2', '5', '6'),
                    'extra_options' => '{"datadogKeySeverity": "1,2,              5                , 6"}'
                )
            ),
        );
    }
}
