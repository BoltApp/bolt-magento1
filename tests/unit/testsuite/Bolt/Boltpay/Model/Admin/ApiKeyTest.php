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

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Admin_ApiKey Mock of the model tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_FeatureSwitch mocked instance of feature switch
     */
    private $featureSwitchMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Model_Session mocked instance of the session model
     */
    private $sessionMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Admin_ApiKey')
            ->setMethods(array('getValue', 'getOldValue', 'setValue'))
            ->getMock();
        $this->featureSwitchMock = $this->getMockBuilder('Bolt_Boltpay_Model_FeatureSwitch')
            ->setMethods(array('updateFeatureSwitches'))
            ->getMock();
        $this->sessionMock = $this->getMockBuilder('Mage_Core_Model_Session')
            ->setMethods(array('addError'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $this->sessionMock);
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
        Bolt_Boltpay_TestHelper::stubConfigValue(
            'payment/boltpay/api_key',
            ''
        );
        $this->currentMock->method('getOldValue')->willReturn('');
        $this->currentMock->method('getValue')->willReturn(Mage::helper('core')->encrypt(self::NEW_API_KEY));

        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')->willReturnCallback(
            function () {
                $this->assertEquals(
                    self::NEW_API_KEY,
                    Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/api_key')),
                    'The current decrypted API key does not match the provided value'
                );
            }
        );
        $this->assertEquals($this->currentMock, $this->currentMock->save());
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
        Bolt_Boltpay_TestHelper::stubConfigValue(
            'payment/boltpay/api_key',
            Mage::helper('core')->encrypt(self::OLD_API_KEY)
        );
        $this->currentMock->method('getOldValue')->willReturn(self::OLD_API_KEY);
        $this->currentMock->method('getValue')->willReturn(Mage::helper('core')->encrypt(self::NEW_API_KEY));
        $exception = new GuzzleHttp\Exception\RequestException('Invalid API key', null);
        $this->featureSwitchMock->expects($this->once())->method('updateFeatureSwitches')
            ->willThrowException($exception);
        $this->currentMock->method('setValue')->willReturn(self::OLD_API_KEY);
        $this->sessionMock->expects($this->once())->method('addError')->with(
            'Error updating API Key: ' . $exception->getMessage()
        );
        $this->assertEquals($this->currentMock, $this->currentMock->save());
    }

    /**
     * @test
     * that save doesn't execute {@see \Bolt_Boltpay_Model_FeatureSwitch::updateFeatureSwitches} if API key is unchanged
     *
     * @covers ::save
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withSameOldAndNewValue_doesNotUpdateFeatureSwitches()
    {
        Bolt_Boltpay_TestHelper::stubConfigValue(
            'payment/boltpay/api_key',
            Mage::helper('core')->encrypt(self::OLD_API_KEY)
        );
        $this->currentMock->method('getOldValue')->willReturn(self::OLD_API_KEY);
        $this->currentMock->method('getValue')->willReturn(Mage::helper('core')->encrypt(self::OLD_API_KEY));
        $this->featureSwitchMock->expects($this->never())->method('updateFeatureSwitches');
        $this->assertEquals($this->currentMock, $this->currentMock->save());
    }
}