<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Updater
 */
class Bolt_Boltpay_Model_UpdaterTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of tested class */
    protected $testClassName = 'Bolt_Boltpay_Model_Updater';

    /**
     * @var MockObject|Bolt_Boltpay_Model_Updater
     */
    private $currentMock;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of the Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Boltpay_Guzzle_ApiClient mocked instance of the Bolt API client
     */
    private $apiClientMock;

    /**
     * @var MockObject|Response mocked instance of the Bolt API request response
     */
    private $responseMock;

    /**
     * @var string original Bolt module version
     */
    private $originalVersion;

    /**
     * Setup test dependencies
     *
     * @throws Exception from mocking trait if test class name is not specified
     */
    protected function setUp()
    {
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('getApiClient', 'notifyException', 'isModuleEnabled', 'getShouldDisableNotificationsForNonCriticalUpdates'))
            ->getMock();
        $this->apiClientMock = $this->getClassPrototype('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('get'))->getMock();
        $this->responseMock = $this->getClassPrototype('GuzzleHttp\Psr7\Response')
            ->setMethods(array('getStatusCode', 'getBody'))->getMock();
        $this->boltHelperMock->method('getApiClient')->willReturn($this->apiClientMock);
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getPatchVersion', 'getLatestVersion', 'boltHelper'))
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->originalVersion = Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
    }

    /**
     * Cleanup changes made by tests
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $this->originalVersion);
        Mage::unregister('_singleton/adminhtml/session');
    }

    /**
     * @test
     * that _construct populates _ioFile property
     *
     * @covers ::_construct
     *
     * @throws ReflectionException if class tested doesn't have _ioFile property
     */
    public function _construct_always_populatesIoFileProperty()
    {
        $instance = Mage::getModel('boltpay/updater');
        $this->assertAttributeInstanceOf('Varien_Io_File', '_ioFile', $instance);
        /** @var Varien_Io_File $ioFile */
        $ioFile = Bolt_Boltpay_TestHelper::getNonPublicProperty($instance, '_ioFile');
        $this->assertEquals(BP, $ioFile->pwd());
    }

    /**
     * @test
     * that getLatestMarketplaceVersion returns only the latest version out of the list retrieved from Magento Connect
     *
     * @covers ::getLatestMarketplaceVersion
     * @dataProvider getLatestMarketplaceVersion_withVariousMarketplaceReleasesProvider
     *
     * @param bool        $marketplaceReleases stubbed result of {@see Bolt_Boltpay_Model_Updater::getBoltMarketplaceReleases}
     * @param string|bool $latestVersion expected result of the method call
     *
     * @throws ReflectionException if getLatestMarketplaceVersion method is not defined
     * @throws Exception if test class name is not defined
     */
    public function getLatestMarketplaceVersion_withVariousMarketplaceReleases_returnsLatestVersionNumber($marketplaceReleases, $latestVersion)
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getBoltMarketplaceReleases'))
            ->getMock();
        $currentMock->expects($this->once())->method('getBoltMarketplaceReleases')->willReturn($marketplaceReleases);
        $this->assertEquals(
            $latestVersion,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'getLatestMarketplaceVersion')
        );
    }

    /**
     * Data provider for {@see getLatestMarketplaceVersion_withVariousMarketplaceReleases_returnsLatestVersionNumber}
     *
     * @return array[] containing array of marketplace releases and expected latest version
     */
    public function getLatestMarketplaceVersion_withVariousMarketplaceReleasesProvider()
    {
        return array(
            array(
                'marketplaceReleases' => array(
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '16.22.0'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '16.21.99'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '0.33.66'),
                ),
                'latestVersion'       => '16.22.0'
            ),
            array(
                'marketplaceReleases' => array(
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '321.645.123'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '234.345.432'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '423.645.345'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '543.21.99'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '534.345.234'),
                    array(Bolt_Boltpay_Model_Updater::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD => '345.33.66'),
                ),
                'latestVersion'       => '543.21.99'
            ),
            array(
                'marketplaceReleases' => array(),
                'latestVersion'       => false
            ),
        );
    }

    /**
     * @test
     * that getLatestGithubVersion returns latest Boltpay version available by retrieving latest Github release
     * and reading its tag name
     *
     * @covers ::getLatestGithubVersion
     *
     * @throws ReflectionException if getLatestGithubVersion method is not defined
     */
    public function getLatestGithubVersion_withValidGithubAPIResponse_returnsLatestGithubReleaseVersion()
    {
        $this->apiClientMock->expects($this->once())->method('get')
            ->with(Bolt_Boltpay_Model_Updater::GITHUB_API_BOLT_REPOSITORY_URL . 'releases/latest')
            ->willReturn($this->responseMock);
        $this->responseMock->expects($this->once())->method('getBody')->willReturn(
            /** @lang JSON */ '{
  "id": 24651101,
  "node_id": "MDc6UmVsZWFzZTI0NjUxMTAx",
  "tag_name": "2.5.0",
  "target_commitish": "master",
  "name": "2.5.0",
  "draft": false
}'
        );
        $this->assertEquals(
            "2.5.0",
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getLatestGithubVersion')
        );
    }

    /**
     * @test
     * that getLatestGithubVersion returns empty string if an exception occurs
     * while retrieving latest release from Github API
     *
     * @covers ::getLatestGithubVersion
     *
     * @throws ReflectionException if getLatestGithubVersion method is not defined
     */
    public function getLatestGithubVersion_withRequestException_returnsFalse()
    {
        $this->apiClientMock->expects($this->once())->method('get')
            ->with(Bolt_Boltpay_Model_Updater::GITHUB_API_BOLT_REPOSITORY_URL . 'releases/latest')
            ->willThrowException(new RequestException('', null));
        $this->assertEquals(
            '',
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getLatestGithubVersion')
        );
    }

    /**
     * @test
     * that shouldUseGithubVersion determines if Github version should be used instead of Marketplace based on
     * {@see Bolt_Boltpay_Model_FeatureSwitch::BOLT_UPDATE_USE_GIT_SWITCH_NAME} feature switch
     *
     * @covers ::shouldUseGithubVersion
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     * @throws ReflectionException if shouldUseGithubVersion method is not defined
     */
    public function shouldUseGithubVersion_always_returnsFeatureSwitchStatus()
    {
        $featureSwitchMock = $this->getClassPrototype('boltpay/featureSwitch')
            ->setMethods(array('isSwitchEnabled'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $featureSwitchMock);
        $featureSwitchMock->expects($this->once())->method('isSwitchEnabled')
            ->with(Bolt_Boltpay_Model_FeatureSwitch::BOLT_UPDATE_USE_GIT_SWITCH_NAME)
            ->willReturn(false);
        $this->assertEquals(
            false,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'shouldUseGithubVersion')
        );
    }

    /**
     * @test
     * that shouldUseGithubVersion returns false if an exception occurs while retrieving feature switch status
     *
     * @covers ::shouldUseGithubVersion
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     * @throws ReflectionException if shouldUseGithubVersion method is not defined
     */
    public function shouldUseGithubVersion_withExceptionDuringFeatureSwitchCheck_returnsFalse()
    {
        $featureSwitchMock = $this->getClassPrototype('boltpay/featureSwitch')
            ->setMethods(array('isSwitchEnabled'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $featureSwitchMock);
        $featureSwitchMock->expects($this->once())->method('isSwitchEnabled')
            ->with(Bolt_Boltpay_Model_FeatureSwitch::BOLT_UPDATE_USE_GIT_SWITCH_NAME)
            ->willThrowException(new Exception('Unknown feature switch'));
        $this->assertEquals(
            false,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'shouldUseGithubVersion')
        );
    }

    /**
     * @test
     * that isUpdateAvailable returns true only when latest version is greater than installed version
     *
     * @covers ::isUpdateAvailable
     * @dataProvider isUpdateAvailable_withVariousVersionCombinationProvider
     *
     * @param mixed $installedVersion of the Bolt module
     * @param mixed $latestVersion of the Bolt module
     * @param mixed $patchVersion available for current version
     * @param bool  $expectedResult of the method call
     */
    public function isUpdateAvailable_withVariousVersionCombination_determinesIfUpdateIsAvailable(
        $installedVersion,
        $latestVersion,
        $patchVersion,
        $expectedResult
    ) {
        $originalVersion = Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $installedVersion);
        $this->currentMock->expects($this->once())->method('getPatchVersion')->with($installedVersion)
            ->willReturn($patchVersion);
        $this->currentMock->expects($this->any())->method('getLatestVersion')->willReturn($latestVersion);
        $this->assertEquals($expectedResult, $this->currentMock->isUpdateAvailable());
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $originalVersion);
    }

    /**
     * Data provider for {@see isUpdateAvailable_withVariousVersionCombinationProvider}
     *
     * @return array containing installed version, latest version and expected result of the method call
     */
    public function isUpdateAvailable_withVariousVersionCombinationProvider()
    {
        return array(
            array(
                'installedVersion' => false,
                'latestVersion'    => false,
                'patchVersion'     => null,
                'expectedResult'   => false
            ),
            array(
                'installedVersion' => '1.0.0',
                'latestVersion'    => false,
                'patchVersion'     => null,
                'expectedResult'   => false
            ),
            array(
                'installedVersion' => '1.0.0',
                'latestVersion'    => null,
                'patchVersion'     => null,
                'expectedResult'   => false
            ),
            array(
                'installedVersion' => '1.0.0',
                'latestVersion'    => '1.0.0',
                'patchVersion'     => null,
                'expectedResult'   => false
            ),
            array(
                'installedVersion' => '1.0.0',
                'latestVersion'    => '1.0.1',
                'patchVersion'     => null,
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.0.1',
                'patchVersion'     => null,
                'expectedResult'   => false
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.2.2',
                'patchVersion'     => null,
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '2.0.2',
                'patchVersion'     => null,
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.0.21',
                'patchVersion'     => null,
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.0.2',
                'patchVersion'     => '1.0.3',
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.0.2',
                'patchVersion'     => '1.0.4',
                'expectedResult'   => true
            ),
            array(
                'installedVersion' => '1.0.2',
                'latestVersion'    => '1.0.2',
                'patchVersion'     => '1.0.5',
                'expectedResult'   => true
            ),
        );
    }

    /**
     * @test
     * that getLatestVersion returns latest version from {@see Bolt_Boltpay_Model_Updater::getLatestMarketplaceVersion}
     * if version is not cached in session and {@see Bolt_Boltpay_Model_Updater::shouldUseGithubVersion} returns false
     *
     * @covers ::getLatestVersion
     *
     * @throws Exception
     */
    public function getLatestVersion_withEmptySessionAndUseGithubFalse_returnsVersionFromMarketplace()
    {
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('shouldUseGithubVersion', 'getLatestGithubVersion', 'getLatestMarketplaceVersion'))
            ->getMock();
        Mage::getSingleton('adminhtml/session')->unsetData(Bolt_Boltpay_Model_Updater::LATEST_BOLT_VERSION_SESSION_KEY);
        $currentMock->expects($this->once())->method('shouldUseGithubVersion')->willReturn(false);
        $currentMock->expects($this->never())->method('getLatestGithubVersion');
        $currentMock->expects($this->once())->method('getLatestMarketplaceVersion')->willReturn('1.0.0');
        $this->assertEquals('1.0.0', $currentMock->getLatestVersion());
    }

    /**
     * @test
     * that getLatestVersion returns latest version from {@see Bolt_Boltpay_Model_Updater::getLatestGithubVersion}
     * if version is not cached in session and {@see Bolt_Boltpay_Model_Updater::shouldUseGithubVersion} returns true
     *
     * @covers ::getLatestVersion
     *
     * @throws Exception
     */
    public function getLatestVersion_withEmptySessionAndUseGithubTrue_returnsVersionFromGithub()
    {
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('shouldUseGithubVersion', 'getLatestGithubVersion', 'getLatestMarketplaceVersion'))
            ->getMock();
        Mage::getSingleton('adminhtml/session')->unsetData(Bolt_Boltpay_Model_Updater::LATEST_BOLT_VERSION_SESSION_KEY);
        $currentMock->expects($this->once())->method('shouldUseGithubVersion')->willReturn(true);
        $currentMock->expects($this->never())->method('getLatestMarketplaceVersion');
        $currentMock->expects($this->once())->method('getLatestGithubVersion')->willReturn('1.0.0');
        $this->assertEquals('1.0.0', $currentMock->getLatestVersion());
    }

    /**
     * @test
     * that getUpdateSeverity returns update severity based on difference between latest and installed versions
     * difference in first number yields major, in second - minor and in third - patch
     *
     * @dataProvider getUpdateSeverityDataProvider
     * @covers ::getUpdateSeverity
     *
     * @param string $installedVersion of the Bolt module
     * @param string $latestVersion of the Bolt module
     * @param string $patchVersion available for the current version
     * @param string $expectedResult of the method call
     *
     * @throws Exception if test class name is not defined
     */
    public function getUpdateSeverity_withVariousVersionCombinations_returnsUpdateSeverity(
        $installedVersion,
        $latestVersion,
        $patchVersion,
        $expectedResult
    ) {
        $originalVersion = Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $installedVersion);
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getPatchVersion', 'getLatestVersion'))
            ->getMock();
        $currentMock->method('getPatchVersion')->willReturn($patchVersion);
        $currentMock->method('getLatestVersion')->willReturn($latestVersion);
        $this->assertEquals($expectedResult, $currentMock->getUpdateSeverity());
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $originalVersion);
    }

    /**
     * Data provider for {@see getUpdateSeverity_withVariousVersionCombinations_returnsUpdateSeverity}
     *
     * @return array containing installed, latest and patch version and expected notification severity
     */
    public function getUpdateSeverityDataProvider()
    {
        return array(
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.0.1',
                'patchVersion'                 => null,
                'expectedNotificationSeverity' => 'patch'
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.1.1',
                'patchVersion'                 => null,
                'expectedNotificationSeverity' => 'minor'
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '2.1.1',
                'patchVersion'                 => null,
                'expectedNotificationSeverity' => 'major'
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.0.0',
                'patchVersion'                 => null,
                'expectedNotificationSeverity' => null
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.0.0',
                'patchVersion'                 => '1.0.1',
                'expectedNotificationSeverity' => 'patch'
            ),
        );
    }

    /**
     * @test
     * that addUpdateMessage adds admin notification message with specific severity
     * based on latest and installed module versions
     *
     * @dataProvider addUpdateMessage_withVariousVersionCombinationsProvider
     * @covers ::addUpdateMessage
     *
     * @param string $installedVersion of the Bolt module
     * @param string $latestVersion of the Bolt module
     * @param string $patchVersion for current version
     * @param string $expectedNotificationTitle of the admin notification message
     * @param string $expectedNotificationSeverity of the admin notification message
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function addUpdateMessage_withVariousVersionCombinations_addsInboxNotification(
        $installedVersion,
        $latestVersion,
        $patchVersion,
        $expectedNotificationTitle,
        $expectedNotificationSeverity
    ) {
        Mage::getConfig()->setNode('modules/Bolt_Boltpay/version', $installedVersion);
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getPatchVersion', 'getLatestVersion', 'boltHelper'))
            ->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->boltHelperMock->expects($this->once())->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn(true);
        $inboxMock = $this->getClassPrototype('adminnotification/inbox')->setMethods(array('add'))->getMock();
        $inboxMock->expects($this->once())->method('add')->with(
            $expectedNotificationSeverity,
            $expectedNotificationTitle,
            sprintf(
                'Installed version: %s. Latest version: %s',
                $installedVersion,
                $patchVersion ?: $latestVersion
            )
        );
        Bolt_Boltpay_TestHelper::stubSingleton('adminnotification/inbox', $inboxMock);
        $currentMock->method('getPatchVersion')->willReturn($patchVersion);
        $currentMock->method('getLatestVersion')->willReturn($latestVersion);
        $currentMock->addUpdateMessage();
    }

    /**
     * Data provider for {@see addUpdateMessage_withVariousVersionCombinations_addsInboxNotification}
     *
     * @return array[]
     */
    public function addUpdateMessage_withVariousVersionCombinationsProvider()
    {
        return array(
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.0.1',
                'patchVersion'                 => null,
                'expectedNotificationTitle'    => 'Bolt version 1.0.1 is available to address a CRITICAL issue.',
                'expectedNotificationSeverity' => Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL,
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.0.0',
                'patchVersion'                 => '1.0.1',
                'expectedNotificationTitle'    => 'Bolt version 1.0.1 is available to address a CRITICAL issue.',
                'expectedNotificationSeverity' => Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL,
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '1.1.1',
                'patchVersion'                 => null,
                'expectedNotificationTitle'    => 'Bolt version 1.1.1 is now available!',
                'expectedNotificationSeverity' => Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE,
            ),
            array(
                'installedVersion'             => '1.0.0',
                'latestVersion'                => '2.1.1',
                'patchVersion'                 => null,
                'expectedNotificationTitle'    => 'Bolt version 2.1.1 is now available!',
                'expectedNotificationSeverity' => Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR,
            ),
        );
    }

    /**
     * @test
     * that addUpdateMessage doesn't add update message if Mage_AdminNotification module is disabled
     *
     * @covers ::addUpdateMessage
     *
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function addUpdateMessage_ifAdminNotificationModuleIsDisabled_doesNotAddUpdateMessage()
    {
        $this->boltHelperMock->expects($this->once())->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn(false);
        $inboxMock = $this->getClassPrototype('adminnotification/inbox')->setMethods(array('add'))->getMock();
        $inboxMock->expects($this->never())->method('add');
        Bolt_Boltpay_TestHelper::stubSingleton('adminnotification/inbox', $inboxMock);
        $this->currentMock->addUpdateMessage();
    }

    /**
     * @test
     * that addUpdateMessage won't add message if an identical one already exists
     *
     * @covers ::addUpdateMessage
     *
     * @throws Exception if test class name is not defined
     */
    public function addUpdateMessage_withExistingIdenticalMessage_doesNotAddMessage()
    {
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('getInstalledVersion', 'getLatestVersion'))
            ->getMock();
        $currentMock->method('getInstalledVersion')->willReturn('1.2.3');
        $currentMock->method('getLatestVersion')->willReturn('4.5.6');
        //add first message
        $currentMock->addUpdateMessage();
        /** @var Mage_AdminNotification_Model_Inbox $firstMessage previously added */
        $firstMessage = Mage::getModel('adminnotification/inbox')->getCollection()->getLastItem();
        //try to add second message
        $currentMock->addUpdateMessage();
        /** @var Mage_AdminNotification_Model_Inbox $secondMessage previously added */
        $secondMessage = Mage::getModel('adminnotification/inbox')->getCollection()->getLastItem();
        try {
            $this->assertEquals($firstMessage->getId(), $secondMessage->getId());
            $firstMessage->delete();
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
            $firstMessage->delete();
            $secondMessage->delete();
            throw $e;
        }
    }

    /**
     * @test
     * that addUpdateMessage won't add message if there is a minor update available and non-critical update
     * notifications are disabled
     *
     * @covers ::addUpdateMessage
     *
     * @throws Exception if test class name is not defined
     */
    public function addUpdateMessage_withMinorUpdateAndNonCriticalUpdateNotificationsDisabled_doesNotAddMessage()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getUpdateSeverity', 'boltHelper', 'getLatestVersion', 'getPatchVersion'))->getMock();
        $this->boltHelperMock->expects($this->once())->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn(true);
        $this->boltHelperMock->expects($this->once())->method('getShouldDisableNotificationsForNonCriticalUpdates')
            ->willReturn(true);
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $inboxMock = $this->getClassPrototype('adminnotification/inbox')->setMethods(array('add'))->getMock();
        $inboxMock->expects($this->never())->method('add');
        Bolt_Boltpay_TestHelper::stubSingleton('adminnotification/inbox', $inboxMock);
        $currentMock->method('getUpdateSeverity')->willReturn('minor');
        $currentMock->addUpdateMessage();
    }

    /**
     * @test
     * that getPatchVersion returns correct patched version for known releases
     *
     * @covers ::getPatchVersion
     *
     * @dataProvider getPatchVersion_withKnownVersionsThatArePatchedProvider
     *
     * @param string $version for which to retrieve patched version
     * @param string $patchedVersion expected output of the method call
     *
     * @throws GuzzleHttp\Exception\GuzzleException if unable to retrieve Github rate limit
     */
    public function getPatchVersion_withKnownVersionsThatArePatched_returnsPatchedVersion($version, $patchedVersion)
    {
        $githubLimit = json_decode(Mage::helper('boltpay')->getApiClient()->get('https://api.github.com/rate_limit')->getBody());
        if ($githubLimit->rate->remaining < 1) {
            $this->markTestSkipped('Github API limit exceeded');
        }

        $this->assertEquals($patchedVersion, Mage::getModel('boltpay/updater')->getPatchVersion($version));
    }

    /**
     * Data provider for {@see getPatchVersion_withKnownVersionsThatArePatched_returnsPatchedVersion}
     *
     * @return array containing installed and corresponding patched version
     */
    public function getPatchVersion_withKnownVersionsThatArePatchedProvider()
    {
        return array(
            array('version' => '2.4.1', 'patchedVersion' => null),
            array('version' => '2.4.0', 'patchedVersion' => '2.4.1'),
            array('version' => '2.0.0', 'patchedVersion' => '2.0.2'),
            array('version' => '1.4.0', 'patchedVersion' => '1.4.1'),
            array('version' => '1.3.2', 'patchedVersion' => '1.3.8'),
            array('version' => '1.0.0', 'patchedVersion' => '1.0.12'),
            array('version' => '1.0.10', 'patchedVersion' => '1.0.12'),
        );
    }

    /**
     * @test
     * that getPatchVersion logs exception and returns null if an exception occurs during Github tags request
     *
     * @covers ::getPatchVersion
     *
     * @throws Exception if test class name is not defined
     */
    public function getPatchVersion_whenExceptionIsThrownWhenRetrievingTags_logsExceptionAndReturnsNull()
    {
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $requestException = new RequestException('', null);
        $this->apiClientMock->expects($this->once())->method('get')->willThrowException($requestException);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($requestException);
        $currentMock->getPatchVersion('1.2.3');
    }

    /**
     * @test
     * that getPatchVersion successfully retrieves patch from Github tag that is not on the first page
     *
     * @covers ::getPatchVersion
     *
     * @throws Exception if test class name is not defined
     */
    public function getPatchVersion_withMultiplePages_returnsPatchVersion()
    {
        /** @var Bolt_Boltpay_Model_Updater|MockObject $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(array('boltHelper'))->getMock();
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $tagResponseMock = $this->getClassPrototype('Psr\Http\Message\ResponseInterface')->getMock();
        $nextPageUrl = 'https://api.github.com/repositories/125256996/tags?page=2&per_page=100';
        $this->apiClientMock->expects($this->exactly(2))->method('get')
            ->withConsecutive(
                array(Bolt_Boltpay_Model_Updater::GITHUB_API_BOLT_REPOSITORY_URL . 'tags' . '?' . http_build_query(array('per_page' => 100))),
                array($nextPageUrl)
            )
            ->willReturn($tagResponseMock);
        $tagResponseMock->expects($this->exactly(2))->method('getBody')->willReturnOnConsecutiveCalls(
            '[{"name": "1.3.8"}]',
            '[{"name": "1.2.4"}]'
        );
        $tagResponseMock->expects($this->once())->method('getHeaderLine')->with('link')->willReturn(
            '<https://api.github.com/repositories/125256996/tags?page=1&per_page=1>; rel="prev", <'. $nextPageUrl .'>; rel="next", <https://api.github.com/repositories/125256996/tags?page=54&per_page=1>; rel="last", <https://api.github.com/repositories/125256996/tags?page=1&per_page=1>; rel="first"'
        );
        $this->assertEquals('1.2.4', $currentMock->getPatchVersion('1.2.3'));
    }

    /**
     * @test
     * that getConnectManagerURL returns Connect Manager URL with default directory name
     *
     * @covers ::getConnectManagerURL
     *
     * @throws ReflectionException if getConnectManagerURL method is not defined
     */
    public function getConnectManagerURL_withDefaultManagerDirectory_returnsConnectManagerURL()
    {
        $ioFileMock = $this->getClassPrototype('Varien_Io_File')->getMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, '_ioFile', $ioFileMock);
        $ioFileMock->expects($this->once())->method('fileExists')->with('downloader', false)->willReturn(true);
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getConnectManagerURL');
        $this->assertEquals(Mage::getBaseUrl('web') . 'downloader/?updates=yes', $result);
    }

    /**
     * @test
     * that getConnectManagerURL returns Connect Manager URL when its directory has been renamed
     * due to security concerns
     *
     * @covers ::getConnectManagerURL
     *
     * @throws ReflectionException if getConnectManagerURL method is not defined
     */
    public function getConnectManagerURL_withRenamedManagerDirectory_returnsConnectManagerURL()
    {
        $ioFileMock = $this->getClassPrototype('Varien_Io_File')
            ->setMethods(array('read', 'ls', 'fileExists', 'open'))
            ->getMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, '_ioFile', $ioFileMock);
        $ioFileMock->expects($this->once())->method('ls')->with(Varien_Io_File::GREP_DIRS)->willReturn(
            array(
                array(
                    'text'        => 'tools',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/tools',
                ),
                array(
                    'text'        => 'docker-container',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/docker-container',
                ),
                array(
                    'text'        => 'errors',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/errors',
                ),
                array(
                    'text'        => 'media',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/media',
                ),
                array(
                    'text'        => 'js',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/js',
                ),
                array(
                    'text'        => 'skin',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/skin',
                ),
                array(
                    'text'        => '.git',
                    'mod_date'    => '2020-05-14 18:13:01',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/.git',
                ),
                array(
                    'text'        => 'ws4er8ty0u',
                    'mod_date'    => '2020-05-15 10:26:48',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/ws4er8ty0u',
                ),
                array(
                    'text'        => 'tests',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/tests',
                ),
                array(
                    'text'        => '.github',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/.github',
                ),
                array(
                    'text'        => 'bin',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/bin',
                ),
                array(
                    'text'        => 'app',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/app',
                ),
                array(
                    'text'        => 'lib',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/lib',
                ),
                array(
                    'text'        => 'shell',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/shell',
                ),
                array(
                    'text'        => 'includes',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/includes',
                ),
                array(
                    'text'        => '.circleci',
                    'mod_date'    => '2020-05-05 19:05:37',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/.circleci',
                ),
                array(
                    'text'        => 'dev',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/dev',
                ),
                array(
                    'text'        => 'drcfgybhuni',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/drcfgybhuni',
                ),
                array(
                    'text'        => 'var',
                    'mod_date'    => '2020-04-20 17:23:41',
                    'permissions' => 'drwxrwxrwx',
                    'owner'       => 'www-data / ',
                    'leaf'        => false,
                    'id'          => '/var/www/html/magento/var',
                ),
            )
        );
        $ioFileMock->expects($this->exactly(18))->method('open')->withConsecutive(
            array(array('path' => 'tools')),
            array(array('path' => 'docker-container')),
            array(array('path' => 'errors')),
            array(array('path' => 'media')),
            array(array('path' => 'js')),
            array(array('path' => 'skin')),
            array(array('path' => '.git')),
            array(array('path' => 'ws4er8ty0u')),
            array(array('path' => 'tests')),
            array(array('path' => '.github')),
            array(array('path' => 'bin')),
            array(array('path' => 'app')),
            array(array('path' => 'lib')),
            array(array('path' => 'shell')),
            array(array('path' => 'includes')),
            array(array('path' => '.circleci')),
            array(array('path' => 'dev')),
            array(array('path' => 'drcfgybhuni')),
            array(array('path' => 'var'))
        )->willReturn(true);
        $ioFileMock->expects($this->exactly(19))->method('fileExists')->withConsecutive(
            array('downloader', false),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini'),
            array('config.ini') // not called
        )->willReturnOnConsecutiveCalls(
            false, //'downloader'
            false, //'tools'
            false, //'docker-container'
            false, //'errors'
            false, //'media'
            false, //'js'
            false, //'skin'
            false, //'.git'
            true, //'ws4er8ty0u'
            false, //'tests'
            false, //'.github'
            false, //'bin'
            false, //'app'
            false, //'lib'
            false, //'shell'
            false, //'includes'
            false, //'.circleci'
            false, //'dev'
            true, //'drcfgybhuni'
            false //'var' not called
        );
        $ioFileMock->expects($this->exactly(2))->method('read')->with('config.ini')
            ->willReturnOnConsecutiveCalls(
                'unrelated config.ini',
                'root_channel=community'
            );
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getConnectManagerURL');
        $this->assertEquals(Mage::getBaseUrl('web') . 'drcfgybhuni/?updates=yes', $result);
    }

    /**
     * @test
     * that getConnectManagerURL returns null if Manager directory cannot be found
     *
     * @covers ::getConnectManagerURL
     *
     * @throws ReflectionException if getConnectManagerURL method is not defined
     */
    public function getConnectManagerURL_withoutManagerDirectory_returnsNull()
    {
        $ioFileMock = $this->getClassPrototype('Varien_Io_File')->setMethods(array('fileExists', 'ls'))->getMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, '_ioFile', $ioFileMock);
        $ioFileMock->expects($this->once())->method('fileExists')->with('downloader', false)->willReturn(false);
        $ioFileMock->expects($this->once())->method('ls')->with(Varien_Io_File::GREP_DIRS)->willReturn(array());
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getConnectManagerURL');
        $this->assertNull($result);
    }

    /**
     * @test
     * that getConnectManagerURL returns null if Manager directory is not default
     * and root subdirectories cannot be listed
     *
     * @covers ::getConnectManagerURL
     *
     * @throws ReflectionException if getConnectManagerURL method is not defined
     */
    public function getConnectManagerURL_whenUnableToListSubdirectories_returnsNull()
    {
        $ioFileMock = $this->getClassPrototype('Varien_Io_File')->setMethods(array('fileExists', 'ls'))->getMock();
        Bolt_Boltpay_TestHelper::setNonPublicProperty($this->currentMock, '_ioFile', $ioFileMock);
        $ioFileMock->expects($this->once())->method('fileExists')->with('downloader', false)->willReturn(false);
        $exception = new Exception('Unable to list current working directory.');
        $ioFileMock->expects($this->once())->method('ls')->with(Varien_Io_File::GREP_DIRS)
            ->willThrowException($exception);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $result = Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'getConnectManagerURL');
        $this->assertNull($result);
    }

    /**
     * @test
     * that isMagentoConnectRestClientAvailable returns true if Mage_Connect_Rest class is available, otherwise false
     *
     * @covers ::isMagentoConnectRestClientAvailable
     *
     * @throws ReflectionException if isMagentoConnectRestClientAvailable method is not defined
     */
    public function isMagentoConnectRestClientAvailable_always_determinesIfMagentoConnectRestClientIsAvailable()
    {
        $this->assertEquals(
            class_exists('Mage_Connect_Rest'),
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'isMagentoConnectRestClientAvailable')
        );
    }

    /**
     * @test
     * that getBoltMarketplaceReleases returns the same result with or without using Mage_Connect_Rest class
     *
     * @covers ::getBoltMarketplaceReleases
     *
     * @throws Exception if test class name is not defined
     */
    public function getBoltMarketplaceReleases_regardlessOfConnectClassesAvailability_returnsReleases()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('isMagentoConnectRestClientAvailable'))
            ->getMock();
        $currentMock->expects($this->exactly(2))->method('isMagentoConnectRestClientAvailable')
            ->willReturnOnConsecutiveCalls(true, false);
        $this->assertEquals(
            Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'getBoltMarketplaceReleases'),
            Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'getBoltMarketplaceReleases')
        );
    }

    /**
     * @test
     * that getBoltMarketplaceReleases returns false and notifies exception if one occurs
     * when retrieving releases from Magento Connect
     *
     * @covers ::getBoltMarketplaceReleases
     *
     * @throws Exception if test class name is not defined
     */
    public function getBoltMarketplaceReleases_withExceptionWhenRetrievingReleases_returnsFalse()
    {
        $currentMock = $this->getTestClassPrototype()
            ->setMethods(array('isMagentoConnectRestClientAvailable', 'boltHelper'))
            ->getMock();
        $currentMock->expects($this->once())->method('isMagentoConnectRestClientAvailable')
            ->willReturn(false);
        $currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $exception = new RequestException('', null);
        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception);
        $this->apiClientMock->expects($this->once())->method('get')
            ->willThrowException($exception);
        $this->assertEmpty(Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, 'getBoltMarketplaceReleases'));
    }

    /**
     * @test
     * that _getSession returns admin session singleton
     *
     * @covers ::_getSession
     *
     * @throws ReflectionException if _getSession method is not defined
     * @throws Mage_Core_Exception if unable to stub singleton
     */
    public function _getSession_always_returnsAdminSessionSingleton()
    {
        $adminSessionMock = $this->getClassPrototype('adminhtml/session')->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('adminhtml/session', $adminSessionMock);
        $this->assertSame(
            $adminSessionMock,
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                '_getSession'
            )
        );
    }
}
