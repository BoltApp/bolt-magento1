<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Admin_ApiKey
 */
class Bolt_Boltpay_Model_Admin_ApiKeyTest extends PHPUnit_Framework_TestCase
{

    /** @var string Dummy new API key */
    const NEW_API_KEY = 'NEWfJWFUoDu7LW4k9EgbkWYbP9GaAqx50nviPxj3hZYwtAml8T4dzVN3yOvP7UtD';

    /** @var string Dummy old API key */
    const OLD_API_KEY = 'OLDUkQwNtDZ1eV2l299MmOhA7GRUMO1F36z7X55AxMMWLW7YZpa4RbwiwNghqWeX';

    /** @var string API key config path */
    const API_KEY_CONFIG_PATH = 'payment/boltpay/api_key';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_FeatureSwitch mocked instance of feature switch
     */
    private $featureSwitchMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Session mocked instance of the session model
     */
    private $sessionMock;

    /**
     * @var string original API key value before test
     */
    private $originalApiKey;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    protected function setUp()
    {
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('updateFeatureSwitches'))
            ->getMock();
        $this->sessionMock = $this->getMockBuilder('Mage_Core_Model_Session')
            ->setMethods(array('addError'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $this->sessionMock);
        $this->originalApiKey = $this->getCurrentAPIKey();
    }

    /**
     * Restore original values that were substituted after each test
     *
     * @throws ReflectionException from TestHelper if Mage doesn't have _config property
     * @throws Mage_Core_Model_Store_Exception from TestHelper if store doesn't  exist
     * @throws Mage_Core_Exception from TestHelper if registry key already exists
     */
    protected function tearDown()
    {
        Mage::getModel('core/config')->saveConfig(self::API_KEY_CONFIG_PATH, $this->originalApiKey);
        Mage::getConfig()->reinit();
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that save executes {@see \Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches} with correct api key set
     *
     * @covers ::save
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withEmptyOldValueAndValidNewValue_updatesFeatureSwitches()
    {
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')->willReturnCallback(
            function () {
                $this->assertEquals(
                    self::NEW_API_KEY,
                    Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/api_key')),
                    'The API key used for updating feature switches does not match the provided value'
                );
            }
        );
        $this->getCurrentInstance(self::NEW_API_KEY)->save();
        $this->assertEquals(self::NEW_API_KEY, Mage::helper('core')->decrypt($this->getCurrentAPIKey()));
    }

    /**
     * @test
     * that save restores old API key and adds error message to session
     * if an exception occurs during updating feature switches
     *
     * @covers ::save
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_whenExceptionIsThrownWhenUpdatingFeatureSwitches_savesOldValueAndAddsErrorMessage()
    {
        $exception = new GuzzleHttp\Exception\RequestException('Invalid API key', null);
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willThrowException($exception);
        $this->sessionMock->expects($this->once())->method('addError')->with(
            'Error updating API Key: ' . $exception->getMessage()
        );
        $this->getCurrentInstance(self::NEW_API_KEY, self::OLD_API_KEY)->save();
        $this->assertEquals(self::OLD_API_KEY, Mage::helper('core')->decrypt($this->getCurrentAPIKey()));
    }

    /**
     * @test
     * that save doesn't execute {@see \Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * if new API key provided is identical to the original
     *
     * @covers ::save
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withSameOldAndNewValue_doesNotUpdateFeatureSwitches()
    {
        $this->featureSwitchMock->expects($this->never())->method('updateFeatureSwitches');
        $this->getCurrentInstance(self::OLD_API_KEY, self::OLD_API_KEY)->save();
        $this->assertEquals(self::OLD_API_KEY, Mage::helper('core')->decrypt($this->getCurrentAPIKey()));
    }

    /**
     * @test
     * that save doesn't execute {@see \Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches}
     * when API key is not updated in the config form
     * @see Varien_Data_Form_Element_Obscure
     *
     * @covers ::save
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withUnchangedFormValue_doesNotUpdateFeatureSwitches()
    {
        $this->featureSwitchMock->expects($this->never())->method('updateFeatureSwitches');
        $this->getCurrentInstance('******', self::OLD_API_KEY)->save();
        $this->assertEquals(self::OLD_API_KEY, Mage::helper('core')->decrypt($this->getCurrentAPIKey()));
    }

    /**
     * Returns instance of {@see Bolt_Boltpay_Model_Admin_ApiKey} configured with provided new value
     * and sets old value in configuration
     *
     * @param string      $newValue to be configured as value for instance returned
     * @param string|null $oldValue to be set as original API key
     *
     * @return Bolt_Boltpay_Model_Admin_ApiKey
     */
    private function getCurrentInstance($newValue, $oldValue = null)
    {
        Mage::getConfig()->setNode('default/payment/boltpay/api_key', Mage::helper('core')->encrypt($oldValue));
        return Mage::getModel('boltpay/admin_apiKey')->setData(
            array(
                'field'         => 'api_key',
                'groups'        => array(
                    'boltpay' => array(
                        'fields' => array(
                            'api_key' => array('value' => $newValue),
                        ),
                    ),
                ),
                'group_id'      => 'boltpay',
                'store_code'    => '',
                'website_code'  => '',
                'scope'         => 'default',
                'scope_id'      => 0,
                'field_config'  => Mage::getModel('adminhtml/config')->getSections()->descend(
                    'payment/groups/boltpay/fields/api_key'
                ),
                'fieldset_data' => array(
                    'api_key' => $newValue,
                ),
                'path'          => self::API_KEY_CONFIG_PATH,
                'value'         => $newValue,
                'config_id'     => Mage::getModel('core/config_data')->getCollection()
                    ->addFieldToFilter('path', self::API_KEY_CONFIG_PATH)->getFirstItem()->getId(),
            )
        );
    }

    /**
     * Returns currently configured API key from the database
     *
     * @return string
     */
    private function getCurrentAPIKey()
    {
        Mage::getConfig()->reinit();
        $apiKeyConfigNode = Mage::getConfig()->getNode('default/' . self::API_KEY_CONFIG_PATH);
        if (!$apiKeyConfigNode) {
            return null;
        }
        return $apiKeyConfigNode->asArray();
    }
}