<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Admin_ExtraConfig
 */
class Bolt_Boltpay_Model_Admin_ExtraConfigTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Admin_ExtraConfig Mock of the model tested
     */
    private $currentMock;

    /**
     * @var string The original Extra config JSON prior to running the test
     */
    private static $originalExtraConfigValues;

    /**
     * Captures the extra config values before test in order to restore them after all test have complete
     */
    public static function setUpBeforeClass()
    {
        static::$originalExtraConfigValues = Mage::getStoreConfig('payment/boltpay/extra_options');
    }

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Admin_ExtraConfig')
            ->setMethods(array('_hasModelChanged', 'getValue', 'getOldValue', 'setValue'))
            ->getMock();
        $this->currentMock->method('_hasModelChanged')->willReturn(false);
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
     * Restores the extra config JSON to what it was prior to the test
     */
    public static function tearDownAfterClass()
    {
        Mage::getModel('core/config')->saveConfig(
            'payment/boltpay/extra_options',
            static::$originalExtraConfigValues
        );
    }

    /**
     * @test
     * that getExtraConfig will return expected result for various configurations
     *
     * @covers ::getExtraConfig
     * @covers ::normalizeJSON
     * @covers ::filterBoltPrimaryColor
     * @covers ::filterHintsTransform
     * @covers ::filterDatadogKeySeverity
     * @covers ::filterDatadogKey
     * @covers ::filterShippingTimeout
     * @covers ::filterPriceFaultTolerance
     * @covers ::filterDisplayPreAuthOrders
     * @covers ::filterKeepPreAuthOrderTimeStamps
     * @covers ::filterKeepPreAuthOrders
     * @covers ::filterEnableBenchmarkProfiling
     * @covers ::filterAllowedReceptionStatuses
     *
     * @dataProvider getExtraConfig_throughVariousFilters_returnsExpectedResultProvider
     *
     * @param string $configName The name of the config as defined the configuration JSON
     * @param string $extraConfigJSON Extra config value stored in the database in JSON format
     * @param array  $filterParameters Parameters to be passed to filter method related to config being retrieved
     * @param mixed  $expectedResult of the method call
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't exist
     */
    public function getExtraConfig_throughVariousFilters_returnsExpectedResult($configName, $extraConfigJSON, $filterParameters, $expectedResult)
    {
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/extra_options', $extraConfigJSON);

        if (is_array($expectedResult) && key_exists('filterMethod', $expectedResult)) {
            $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Admin_ExtraConfig')
                ->setMethods(array($expectedResult['filterMethod']))
                ->getMock();
            $this->currentMock->expects($this->once())
                ->method($expectedResult['filterMethod'])
                ->with($expectedResult['parameter'])
                ->willReturnCallback(
                    function($rawConfigValue) use ($expectedResult) {
                        $proxyHelper = new Bolt_Boltpay_Model_Admin_ExtraConfig();
                        return $proxyHelper->$expectedResult['filterMethod']($rawConfigValue);
                    }
                )
            ;
            $this->assertTrue(is_bool($this->currentMock->getExtraConfig($configName, $filterParameters)));
        } else {
            $this->assertEquals(
                $expectedResult,
                $this->currentMock->getExtraConfig($configName, $filterParameters)
            );
        }
    }

    /**
     * Data provider for {@see getExtraConfig_withVariousConfigurations_returnsExpectedResult}
     * Returns cases to cover all existing filters
     *
     * @return array ($configName, $extraConfigJSON, $filterParameters, $expectedResult)
     */
    public function getExtraConfig_throughVariousFilters_returnsExpectedResultProvider()
    {
        return array(
            'An unsaved config, (i.e. not in DB), will return null' =>
                array(
                    'configName'       => 'unsavedConfig',
                    'jsonFromDb'       => '{"boltPrimaryColor":"#FFFFFF"}',
                    'filterParameters' => array(),
                    'expectedResult'   => null
                )
            ,
            'Saved config with no filter functions will return raw db value' =>
                array(
                    'configName'       => $configName = 'configWithNoFilter',
                    'jsonFromDb'       => json_encode(array($configName => $rawValue = "tHis \n IS RaW!! \r  ")),
                    'filterParameters' => array(),
                    'expectedResult'   => $rawValue
                )
            ,
            'DB JSON corrupted with newlines added by textarea will still return value stripped of those chars' =>
                array(
                    'configName'       => 'nameHadCarriageReturn',
                    'jsonFromDb'       => '{"' . "\n" . 'nameHadCarriage' . "\r" . 'Return"' . "\r\n" . ':"end of line char was in ' . PHP_EOL . 'value"}',
                    'filterParameters' => array(),
                    'expectedResult'   => 'end of line char was in value'
                )
            ,
            'Bolt Primary color with uppercase filter returns value with any lowercase letters changed to uppercase' =>
                array(
                    'configName'       => 'boltPrimaryColor',
                    'jsonFromDb'       => '{"boltPrimaryColor":"#fEa21B"}',
                    'filterParameters' => array('case' => 'upper'),
                    'expectedResult'   => '#FEA21B'
                )
            ,
            'Bolt Primary color with lowercase filter returns value with any uppercase letters changed to lowercase' =>
                array(
                    'configName'       => 'boltPrimaryColor',
                    'jsonFromDb'       => '{"boltPrimaryColor":"#fEa21B"}',
                    'filterParameters' => array('case' => 'lower'),
                    'expectedResult'   => '#fea21b'
                )
            ,
            'Bolt hints transform with specified override returns override code with unescaped quotes' =>
                array(
                    'configName'       => 'hintsTransform',
                    'jsonFromDb'       => '{"hintsTransform":"function(hints){ hints = { \"prefill\": {\"email\": \"dev@bolt.com\"} };}"}',
                    'filterParameters' => array(),
                    'expectedResult'   => 'function(hints){ hints = { "prefill": {"email": "dev@bolt.com"} };}'
                )
            ,
            'Bolt hints, when not configured, will return the default' =>
                array(
                    'configName'       => 'hintsTransform',
                    'jsonFromDb'       => '',   # intentionally testing JSON as empty string for empty
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Model_Admin_ExtraConfig::DEFAULT_HINTS_TRANSFORM_FUNCTION
                )
            ,
            'Data dog key severity, when configured, will return set db value' =>
                array(
                    'configName'       => $configName = 'datadogKeySeverity',
                    'jsonFromDb'       => json_encode(array($configName => $configuredValue = Boltpay_DataDog_ErrorTypes::TYPE_INFO)),
                    'filterParameters' => array(),
                    'expectedResult'   => $configuredValue
                )
            ,
            'Data dog key severity, when not configured, will return default error type of error' =>
                array(
                    'configName'       => 'datadogKeySeverity',
                    'jsonFromDb'       => null, # intentionally testing JSON as null for empty
                    'filterParameters' => array(),
                    'expectedResult'   => Boltpay_DataDog_ErrorTypes::TYPE_ERROR
                )
            ,
            'Data dog key severity, when configured with empty string, will return an empty string and not the default' =>
                array(
                    'configName'       => 'datadogKeySeverity',
                    'jsonFromDb'       => '{"datadogKeySeverity":""}',
                    'filterParameters' => array(),
                    'expectedResult'   => ''
                )
            ,
            'Data dog key, when configured, will return the configured value' =>
                array(
                    'configName'       => 'datadogKey',
                    'jsonFromDb'       => '{"datadogKey":"'.md5('bolt').'"}',
                    'filterParameters' => array(),
                    'expectedResult'   => md5('bolt')
                )
            ,
            'Data dog key, when not configured, will return the default Bolt key' =>
                array(
                    'configName'       => 'datadogKey',
                    'jsonFromDb'       => '{}', # intentionally testing JSON as empty object for empty
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Helper_DataDogTrait::$defaultDataDogKey
                )
            ,
            'Shipping timeout, if configured with a positive integer, will return that same integer' =>
                array(
                    'configName'       => 'shippingTimeout',
                    'jsonFromDb'       => '{"shippingTimeout": 300}',
                    'filterParameters' => array(),
                    'expectedResult'   => 300
                )
            ,
            'Shipping timeout, if configured with a negative integer, will return the absolute value of that integer' =>
                array(
                    'configName'       => 'shippingTimeout',
                    'jsonFromDb'       => '{"shippingTimeout": -300}',
                    'filterParameters' => array(),
                    'expectedResult'   => 300
                )
            ,
            'Shipping timeout, if configured with a non-integer, will return the default time' =>
                array(
                    'configName'       => 'shippingTimeout',
                    'jsonFromDb'       => '{"shippingTimeout": "this non-integer should force the default time"}',
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Model_Admin_ExtraConfig::DEFAULT_SHIPPING_TIMEOUT
                )
            ,
            'Shipping timeout, if not configured, will return the default time' =>
                array(
                    'configName'       => 'shippingTimeout',
                    'jsonFromDb'       => '{"boltPrimaryColor":"#fEa21B"}',
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Model_Admin_ExtraConfig::DEFAULT_SHIPPING_TIMEOUT
                )
            ,
            'Price fault tolerance, if not configured, will return the default' =>
                array(
                    'configName'       => 'priceFaultTolerance',
                    'jsonFromDb'       => '{"boltPrimaryColor":"#fEa21B"}',
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Model_Admin_ExtraConfig::DEFAULT_PRICE_FAULT_TOLERANCE
                )
            ,
            'Price fault tolerance, if configured with non-integer, will return the default' =>
                array(
                    'configName'       => 'priceFaultTolerance',
                    'jsonFromDb'       => '{"priceFaultTolerance": 2.393}',
                    'filterParameters' => array(),
                    'expectedResult'   => Bolt_Boltpay_Model_Admin_ExtraConfig::DEFAULT_PRICE_FAULT_TOLERANCE
                )
            ,
            'Price fault tolerance, if configured with a negative integer, will return the absolute value of that integer' =>
                array(
                    'configName'       => 'priceFaultTolerance',
                    'jsonFromDb'       => '{"priceFaultTolerance": '.($priceFaultTolerance = mt_rand(-10,-1)).'}',
                    'filterParameters' => array(),
                    'expectedResult'   => abs($priceFaultTolerance)
                )
            ,
            'Price fault tolerance, if configured with a positive integer, will return that integer' =>
                array(
                    'configName'       => 'priceFaultTolerance',
                    'jsonFromDb'       => '{"priceFaultTolerance": '.($priceFaultTolerance = mt_rand(1,10)).'}',
                    'filterParameters' => array(),
                    'expectedResult'   => $priceFaultTolerance
                )
            ,
            'Price fault tolerance, if configured with 0, then 0 is returned' =>
                array(
                    'configName'       => 'priceFaultTolerance',
                    'jsonFromDb'       => '{"priceFaultTolerance": 0}',
                    'filterParameters' => array(),
                    'expectedResult'   => 0
                )
            ,
            'Display pre-auth orders, if not configured, will return false' =>
                array(
                    'configName'       => 'displayPreAuthOrders',
                    'jsonFromDb'       => '',
                    'filterParameters' => array(),
                    'expectedResult'   => false
                )
            ,
            'Display pre-auth orders will always return a boolean' =>
                array(
                    'configName'       => 'displayPreAuthOrders',
                    'jsonFromDb'       => '{"displayPreAuthOrders": true}',
                    'filterParameters' => array(),
                    'expectedResult'   => array('filterMethod' => 'filterDisplayPreAuthOrders', 'parameter' => true)
                )
            ,
            'Keep pre-auth order timestamp will always return a boolean' =>
                array(
                    'configName'       => 'keepPreAuthOrderTimeStamps',
                    'jsonFromDb'       => '{"keepPreAuthOrderTimeStamps": "no"}',
                    'filterParameters' => array(),
                    'expectedResult'   => array('filterMethod' => 'filterKeepPreAuthOrderTimeStamps', 'parameter' => 'no')
                )
            ,
            'Keep pre-auth order timestamp, if not configured, will return false' =>
                array(
                    'configName'       => 'keepPreAuthOrderTimeStamps',
                    'jsonFromDb'       => null,
                    'filterParameters' => array(),
                    'expectedResult'   => false
                )
            ,
            'Keep pre-auth orders will always return a boolean' =>
                array(
                    'configName'       => 'keepPreAuthOrders',
                    'jsonFromDb'       => '{"keepPreAuthOrders": "Y"}',
                    'filterParameters' => array(),
                    'expectedResult'   => array('filterMethod' => 'filterKeepPreAuthOrders', 'parameter' => 'Y')
                )
            ,
            'Keep pre-auth orders, if not configured, will return false' =>
                array(
                    'configName'       => 'keepPreAuthOrders',
                    'jsonFromDb'       => '{}',
                    'filterParameters' => array(),
                    'expectedResult'   => false
                )
            ,
            'Enable benchmark profiling will always return a boolean' =>
                array(
                    'configName'       => 'enableBenchmarkProfiling',
                    'jsonFromDb'       => '{"enableBenchmarkProfiling": "off"}',
                    'filterParameters' => array(),
                    'expectedResult'   => array('filterMethod' => 'filterEnableBenchmarkProfiling', 'parameter' => 'off')
                )
            ,
            'Enable benchmark profiling, if not configured, will return false' =>
                array(
                    'configName'       => 'enableBenchmarkProfiling',
                    'jsonFromDb'       => '{"keepPreAuthOrders": "Y"}',
                    'filterParameters' => array(),
                    'expectedResult'   => false
                )
            ,
            'Allow reception statuses, if not configured, will return Bolt pending payment and pending' =>
                array(
                    'configName'       => 'allowedReceptionStatuses',
                    'jsonFromDb'       => '',
                    'filterParameters' => array(),
                    'expectedResult'   => array(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING)
                )
            ,
            'Allow reception statuses, when configured, will return array of configured strings' =>
                array(
                    'configName'       => 'allowedReceptionStatuses',
                    'jsonFromDb'       => '{"keepPreAuthOrders": "Y", "allowedReceptionStatuses": "pending, pending_bolt   ,new"}',
                    'filterParameters' => array(),
                    'expectedResult'   => array('pending','pending_bolt','new')
                )
            ,
        );
    }

    /**
     * @test
     * that save method doesn't change value when all parameters are valid
     *
     * @covers ::save
     * @covers ::hasValidBoltPrimaryColor
     * @covers ::hasValidDatadogKeySeverity
     * @covers ::hasValidPriceFaultTolerance
     *
     * @dataProvider save_withValidParameters_savesNewValueProvider
     *
     * @param string $configName        Unique key of the configuration being saved
     * @param mixed  $validConfigValue  A valid value for the particular config
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withValidParameters_savesNewValue($configName, $validConfigValue)
    {
        $validationMethod = 'hasValid'.ucfirst($configName);

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Admin_ExtraConfig')
            ->setMethods(array('_hasModelChanged', 'getValue', 'getOldValue', 'setValue', $validationMethod))
            ->getMock();
        $this->currentMock->method('_hasModelChanged')->willReturn(false);

        $this->currentMock->expects($this->once())
            ->method($validationMethod)
            ->with($validConfigValue)
            ->willReturnCallback(
                function($rawConfigValue) use ($validationMethod) {
                    $proxyHelper = new Bolt_Boltpay_Model_Admin_ExtraConfig();
                    return $proxyHelper->$validationMethod($rawConfigValue);
                }
            )
        ;

        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $extraConfigJson = json_encode( array($configName => $validConfigValue) );

        $this->currentMock->expects($this->once())->method('getValue')->willReturn($extraConfigJson);
        $this->currentMock->expects($this->never())->method('setValue'); # calling setValue indicates validation failed
        $sessionMock->expects($this->never())->method('addError');

        $this->currentMock->save();

    }

    /**
     * Provides {@see Bolt_Boltpay_Model_Admin_ExtraConfigTest::save_withValidParameters_savesNewValue()}
     * with valid config values to be validated and saved.
     *
     * @return array    ($configName, $validConfigValue)
     */
    public function save_withValidParameters_savesNewValueProvider() {
        return array(
            'priceFaultTolerance with positive integer will pass validation and save' =>
                array(
                    'configName' => 'priceFaultTolerance',
                    'validConfigValue' => 2
                )
            ,
            'boltPrimaryColor with six character hex will pass validation and save' =>
                array(
                    'configName' => 'boltPrimaryColor',
                    'validConfigValue' => '#Fd37a0'
                )
            ,
            'boltPrimaryColor with eight character hex will pass validation and save' =>
                array(
                    'configName' => 'boltPrimaryColor',
                    'validConfigValue' => '#30bc9d6A'
                )
            ,
            'datadogKeySeverity set to "error" will pass validation and save' =>
                array(
                    'configName' => 'datadogKeySeverity',
                    'validConfigValue' => Boltpay_DataDog_ErrorTypes::TYPE_ERROR
                )
            ,
            'datadogKeySeverity set to "warning" will pass validation and save' =>
                array(
                    'configName' => 'datadogKeySeverity',
                    'validConfigValue' => Boltpay_DataDog_ErrorTypes::TYPE_WARNING
                )
            ,
            'datadogKeySeverity set to "info" will pass validation and save' =>
                array(
                    'configName' => 'datadogKeySeverity',
                    'validConfigValue' => Boltpay_DataDog_ErrorTypes::TYPE_INFO
                )
            ,
            'datadogKeySeverity set to "error, warning" will pass validation and save' =>
                array(
                    'configName' => 'datadogKeySeverity',
                    'validConfigValue' =>
                        Boltpay_DataDog_ErrorTypes::TYPE_ERROR.', '
                        . Boltpay_DataDog_ErrorTypes::TYPE_WARNING
                )
            ,
            'datadogKeySeverity set to "info, error ,    warning  " will pass validation and save' =>
                array(
                    'configName' => 'datadogKeySeverity',
                    'validConfigValue' =>
                        Boltpay_DataDog_ErrorTypes::TYPE_INFO.', '
                        . Boltpay_DataDog_ErrorTypes::TYPE_ERROR.' ,    '
                        . Boltpay_DataDog_ErrorTypes::TYPE_WARNING."  "
                )
            ,
        );
    }

    /**
     * @test
     * that save method will save previous value if current is not valid JSON
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withInvalidJSONAsValue_savesOldValue()
    {
        $oldValue = '{}';
        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $this->currentMock->expects($this->once())->method('getValue')->willReturn('{invalid} json');
        $this->currentMock->expects($this->once())->method('getOldValue')->willReturn($oldValue);
        $this->currentMock->expects($this->once())->method('setValue')->with($oldValue);

        $sessionMock->expects($this->once())->method('addError')
            ->with($this->stringStartsWith('Invalid JSON for Bolt Extra Options.'));

        $this->currentMock->save();
    }

    /**
     * @test
     * that save method will save previous value if any of the options is invalid
     *
     * @covers ::save
     * @covers ::hasValidPriceFaultTolerance
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save_withInvalidOptions_savesOldValue()
    {
        $invalidPriceFaultTolerance = -1;
        $value = json_encode(
            array(
                'priceFaultTolerance' => $invalidPriceFaultTolerance,
                'boltPrimaryColor'    => '##',
                'datadogKeySeverity'  => '##'
            )
        );
        $oldValue = '{}';
        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $this->currentMock->expects($this->once())->method('getValue')->willReturn($value);
        $this->currentMock->expects($this->once())->method('getOldValue')->willReturn($oldValue);
        $this->currentMock->expects($this->once())->method('setValue')->with($oldValue);


        $sessionMock->expects($this->once())->method('addError')
            ->with(
                "Invalid value for extra option `priceFaultTolerance`.[" . $invalidPriceFaultTolerance . "]
                         A valid value must be a positive integer."
            );

        $this->currentMock->save();
    }

    /**
     * @test
     * that hasValidBoltPrimaryColor returns false for invalid color hex and adds error message to session
     *
     * @covers ::hasValidBoltPrimaryColor
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function hasValidBoltPrimaryColor_withInvalidColorHex_returnsFalseAndAddsErrorMessageToSession()
    {
        $invalidBoltPrimaryColorHex = "##";
        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $sessionMock->expects($this->once())->method('addError')
            ->with(
                "Invalid hex color value for extra option `boltPrimaryColor`. [" . $invalidBoltPrimaryColorHex . "] It must be in 6 or 8 character hex format.  (e.g. #f00000 or #3af508a2)"
            );

        $this->assertFalse($this->currentMock->hasValidBoltPrimaryColor($invalidBoltPrimaryColorHex));
    }

    /**
     * @test
     * that hasValidDatadogKeySeverity returns false for invalid severity keys and adds error message to session
     *
     * @covers ::hasValidDatadogKeySeverity
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function hasValidDatadogKeySeverity_withInvalidSeverityKey_returnsFalseAndAddsErrorMessageToSession()
    {
        $invalidDatadogKeySeverity = '##';

        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $sessionMock->expects($this->once())->method('addError')
            ->with(
                $this->logicalAnd(
                    $this->stringStartsWith("Invalid datadog key severity value for extra option `datadogKeySeverity`.[$invalidDatadogKeySeverity]"),
                    $this->stringEndsWith("The valid values must be error or warning or info ")
                )
            );

        $this->assertFalse($this->currentMock->hasValidDatadogKeySeverity($invalidDatadogKeySeverity));
    }

    /**
     * @test
     * that hasValidPriceFaultTolerance returns false for negative integers and adds error message to session
     *
     * @covers ::hasValidPriceFaultTolerance
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function hasValidPriceFaultTolerance_withNegativeInteger_returnsFalseAndAddsErrorMessageToSession()
    {
        $invalidPriceFaultTolerance = -1;

        $sessionMock = $this->getMockBuilder('Mage_Core_Model_Session_Abstract')
            ->setMethods(array('addError'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubSingleton('core/session', $sessionMock);

        $sessionMock->expects($this->once())->method('addError')
            ->with(
                $this->logicalAnd(
                    $this->stringStartsWith("Invalid value for extra option `priceFaultTolerance`.[$invalidPriceFaultTolerance]"),
                    $this->stringEndsWith("A valid value must be a positive integer.")
                )
            );

        $this->assertFalse($this->currentMock->hasValidPriceFaultTolerance($invalidPriceFaultTolerance));
    }

    /**
     * @test
     * that this method correctly converts an input value to the logical boolean equivalent
     *
     * @covers ::normalizeBoolean
     * @dataProvider normalizeBoolean_givenAnInput_willReturnCorrespondingBooleanValueProvider
     *
     * @param string $rawConfigValue    The value that is to be converted to a boolean
     * @param bool   $expectedBoolean   The boolean value that is expected to be returned for the given input
     *
     * @throws ReflectionException if a specified object, class or method does not exist.
     */
    public function normalizeBoolean_givenAnInput_willReturnCorrespondingBooleanValue($rawConfigValue, $expectedBoolean) {

        $this->assertEquals(
            $expectedBoolean,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'normalizeBoolean',
                array($rawConfigValue)
            )
        );
    }

    /**
     * Provides {@see Bolt_Boltpay_Model_Admin_ExtraConfigTest::normalizeBoolean_givenAnInput_willReturnCorrespondingBooleanValue()}
     * with several common configurations for switching on or off a binary state config
     *
     * @return array    ($rawConfigValue, $expectedBoolean)
     */
    public function normalizeBoolean_givenAnInput_willReturnCorrespondingBooleanValueProvider() {
        return array(
            'The string "No" will yield false'        => array('rawConfigValue' => 'No',    'expectedBoolean' => false),
            'The string "no" will yield false'        => array('rawConfigValue' => 'no',    'expectedBoolean' => false),
            'The string "n" will yield false'         => array('rawConfigValue' => 'n',     'expectedBoolean' => false),
            'The string "Off" will yield false'       => array('rawConfigValue' => 'Off',   'expectedBoolean' => false),
            'The string "off" will yield false'       => array('rawConfigValue' => 'off',   'expectedBoolean' => false),
            'The integer 0 will yield false'          => array('rawConfigValue' => 0,       'expectedBoolean' => false),
            'The string "0" will yield false'         => array('rawConfigValue' => '0',     'expectedBoolean' => false),
            'The boolean false will yield false'      => array('rawConfigValue' => false,   'expectedBoolean' => false),
            'The string "false" will yield false'     => array('rawConfigValue' => 'false', 'expectedBoolean' => false),
            'The string "False" will yield false'     => array('rawConfigValue' => 'False', 'expectedBoolean' => false),
            'The boolean true will yield true'        => array('rawConfigValue' => true,    'expectedBoolean' => true),
            'The string "true" will yield true'       => array('rawConfigValue' => 'true',  'expectedBoolean' => true),
            'The string "On" will yield true'         => array('rawConfigValue' => 'On',    'expectedBoolean' => true),
            'Any unrecognized string will yield true' => array('rawConfigValue' => 'Bolt!', 'expectedBoolean' => true),
            'The integer 1 will yield true'           => array('rawConfigValue' => 1,       'expectedBoolean' => true),
            'Any object will yield true'              => array('rawConfigValue' => $this,   'expectedBoolean' => true),
        );
    }
}