<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Adminhtml_Notification
 */
class Bolt_Boltpay_Block_Adminhtml_NotificationTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string name of the class tested
     */
    protected $testClassName = 'Bolt_Boltpay_Block_Adminhtml_Notification';

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Bolt_Boltpay_Block_Adminhtml_Notification mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Admin_Model_Session mocked instance of admin session model
     */
    private $sessionMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Updater mocked instance of Bolt updater model
     */
    private $updaterMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject mocked instance of the Bolt feature switch model
     */
    private $featureSwitchMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Exception if test class name is not defined
     */
    protected function setUp()
    {
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')->getMock();
        $this->sessionMock = $this->getClassPrototype('admin/session')
            ->setMethods(array('isAllowed'))->getMock();
        $this->updaterMock = $this->getClassPrototype('boltpay/updater')
            ->setMethods(array('isUpdateAvailable', 'getUpdateSeverity'))->getMock();
        $this->featureSwitchMock = $this->getClassPrototype('boltpay/featureSwitch')
            ->setMethods(array('isSwitchEnabled'))->getMock();
        Bolt_Boltpay_TestHelper::stubSingleton('boltpay/featureSwitch', $this->featureSwitchMock);
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'isOutputEnabled', '_getSession', 'getUpdater', 'getLayout'))
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->currentMock->method('_getSession')->willReturn($this->sessionMock);
        $this->currentMock->method('getUpdater')->willReturn($this->updaterMock);
        $this->currentMock->method('getLayout')->willReturn(Mage::getSingleton('core/layout'));
    }

    /**
     * Restore original values
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that shouldDisplay returns true only if all of the following conditions are met
     * 1. Mage_AdminNotification is enabled
     * 2. Mage_AdminNotification output is not disabled
     * 3. Notification is allowed by ACL for current administrator
     * 4. An update is available
     * 5. In case of minor update - notifications are not disabled for non critical updates
     *
     * @covers ::shouldDisplay
     * @dataProvider shouldDisplay_withVariousResultsOfTheCheckMethodsProvider
     *
     * @param bool $isNewReleaseNotificationsFeatureSwitchEnabled stubbed result of {@see Bolt_Boltpay_Model_FeatureSwitch::isSwitchEnabled}
     * @param bool $isAdminNotificationModuleEnabled stubbed result of {@see Mage_Core_Helper_Abstract::isModuleEnabled}
     * @param bool $isAdminNotificationModuleOutputEnabled stubbed result of {@see Mage_Adminhtml_Block_Template::isOutputEnabled}
     * @param bool $isNotificationToolbarAllowedByACL stubbed result of {@see Mage_Admin_Model_Session::isAllowed}
     * @param bool $isUpdateAvailable stubbed result of {@see Bolt_Boltpay_Model_Updater::isUpdateAvailable}
     * @param bool $isUpdateSeverityMinor
     * @param bool $areNotificationsForNonCriticalUpdatesDisabled
     *
     * @throws ReflectionException if shouldDisplay method is not defined
     */
    public function shouldDisplay_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown(
        $isNewReleaseNotificationsFeatureSwitchEnabled,
        $isAdminNotificationModuleEnabled,
        $isAdminNotificationModuleOutputEnabled,
        $isNotificationToolbarAllowedByACL,
        $isUpdateAvailable,
        $isUpdateSeverityMinor,
        $areNotificationsForNonCriticalUpdatesDisabled
    ) {
        $this->featureSwitchMock->method('isSwitchEnabled')->with(Bolt_Boltpay_Model_FeatureSwitch::BOLT_NEW_RELEASE_NOTIFICATIONS_SWITCH_NAME)
            ->willReturn($isNewReleaseNotificationsFeatureSwitchEnabled);
        $this->boltHelperMock->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleEnabled);
        $this->currentMock->method('isOutputEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleOutputEnabled);
        $this->sessionMock->method('isAllowed')->with('admin/system/adminnotification/show_toolbar')
            ->willReturn($isNotificationToolbarAllowedByACL);
        $this->updaterMock->method('isUpdateAvailable')->willReturn($isUpdateAvailable);
        $this->updaterMock->method('getUpdateSeverity')->willReturn($isUpdateSeverityMinor ? 'minor' : 'patch');
        $this->boltHelperMock->method('getShouldDisableNotificationsForNonCriticalUpdates')
            ->willReturn($areNotificationsForNonCriticalUpdatesDisabled);
        $this->assertEquals(
            $isNewReleaseNotificationsFeatureSwitchEnabled && $isAdminNotificationModuleEnabled
            && $isAdminNotificationModuleOutputEnabled && $isNotificationToolbarAllowedByACL && $isUpdateAvailable
            && !($isUpdateSeverityMinor && $areNotificationsForNonCriticalUpdatesDisabled),
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'shouldDisplay')
        );
    }

    /**
     * Data provider for {@see shouldDisplay_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown}
     * @see Bolt_Boltpay_TestHelper::getAllBooleanCombinations
     *
     * @return array of all possible combinations for 6 boolean variables
     */
    public function shouldDisplay_withVariousResultsOfTheCheckMethodsProvider()
    {
        return Bolt_Boltpay_TestHelper::getAllBooleanCombinations(7);
    }

    /**
     * @test
     * that getSeverityIconUrl returns icon URL from {@see Mage_Adminhtml_Block_Notification_Window::getSeverityIconsUrl}
     * based on update severity level
     *
     * @dataProvider getSeverityIconUrl_withVariousUpdateSeverityLevelsProvider
     *
     * @covers ::getSeverityIconUrl
     *
     * @param string $updateSeverity level of difference between latest and installed versions of Boltpay module
     */
    public function getSeverityIconUrl_withVariousUpdateSeverityLevels_returnsIconURL($updateSeverity)
    {
        $this->updaterMock->expects($this->once())->method('getUpdateSeverity')->willReturn($updateSeverity);
        $result = $this->currentMock->getSeverityIconUrl();
        /** @var Mage_Adminhtml_Block_Notification_Window $notificationWindowBlock */
        $notificationWindowBlock = Mage::getSingleton('core/layout')->createBlock('adminhtml/notification_window');
        $notificationWindowBlock->setData(
            'notice_severity',
            $updateSeverity == 'patch' ? 'SEVERITY_CRITICAL' : 'SEVERITY_NOTICE'
        );
        $this->assertEquals($notificationWindowBlock->getSeverityIconsUrl(), $result);
    }

    /**
     * Data provider for {@see getSeverityIconUrl_withVariousUpdateSeverityLevels_returnsIconURL}
     *
     * @return string[][] containing update severity
     */
    public function getSeverityIconUrl_withVariousUpdateSeverityLevelsProvider()
    {
        return array(
            array('updateSeverity' => 'major'),
            array('updateSeverity' => 'minor'),
            array('updateSeverity' => 'patch'),
        );
    }

    /**
     * @test
     * that _getSession returns admin session singleton
     *
     * @covers ::_getSession
     *
     * @throws ReflectionException if _getSession method is not defined
     * @throws Exception if test class name is not defined
     */
    public function _getSession_always_returnsAdminSessionSingleton()
    {
        $this->assertEquals(
            Mage::getSingleton('admin/session'),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->getTestClassPrototype()->setMethods(null)->getMock(),
                '_getSession'
            )
        );
    }

    /**
     * @test
     * that getUpdater returns Bolt updater singleton
     *
     * @covers ::getUpdater
     *
     * @throws Exception if test class name is not defined
     */
    public function getUpdater_always_returnsBoltUpdaterSingleton()
    {
        /** @var Bolt_Boltpay_Block_Adminhtml_Notification $currentMock */
        $currentMock = $this->getTestClassPrototype()->setMethods(null)->getMock();
        $this->assertEquals(Mage::getSingleton('boltpay/updater'), $currentMock->getUpdater());
    }

    /**
     * @test
     * that _toHtml is gated behind {@see Bolt_Boltpay_Block_Adminhtml_Notification::shouldDisplay}
     *
     * @covers ::_toHtml
     * @dataProvider _toHtml_withStubbedShouldDisplayMethodProvider
     *
     * @param bool $shouldDisplay stubbed result of {@see Bolt_Boltpay_Block_Adminhtml_Notification::shouldDisplay}
     *
     * @throws ReflectionException if _toHtml method is not defined
     * @throws Exception if test class name is not defined
     */
    public function _toHtml_withStubbedShouldDisplayMethod_rendersTemplateOnlyIfShouldDisplayIsTrue($shouldDisplay)
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('shouldDisplay', 'renderView'))->getMock();
        $currentMock->expects($this->once())->method('shouldDisplay')->willReturn($shouldDisplay);
        $currentMock->expects($shouldDisplay ? $this->once() : $this->never())->method('renderView');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, '_toHtml');
    }

    /**
     * Data provider for {@see _toHtml_withStubbedShouldDisplayMethod_rendersTemplateOnlyIfShouldDisplayIsTrue}
     *
     * @return bool[][] stubbed results of shouldDisplay method
     */
    public function _toHtml_withStubbedShouldDisplayMethodProvider()
    {
        return array(
            array('shouldDisplay' => true),
            array('shouldDisplay' => false)
        );
    }
}
