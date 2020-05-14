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
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'isOutputEnabled', '_getSession', 'getUpdater', 'getLayout'))
            ->getMock();
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->currentMock->method('_getSession')->willReturn($this->sessionMock);
        $this->currentMock->method('getUpdater')->willReturn($this->updaterMock);
        $this->currentMock->method('getLayout')->willReturn(Mage::getSingleton('core/layout'));
    }

    /**
     * @test
     * that canShow returns true only if all of the following conditions are met
     * 1. Mage_AdminNotification is enabled
     * 2. Mage_AdminNotification output is not disabled
     * 3. Notification is allowed by ACL for current administrator
     * 4. An update is available
     *
     * @covers ::_canShow
     * @dataProvider canShow_withVariousResultsOfTheCheckMethodsProvider
     *
     * @param bool $isAdminNotificationModuleEnabled
     * @param bool $isAdminNotificationModuleOutputEnabled
     * @param bool $isNotificationToolbarAllowedByACL
     * @param bool $isUpdateAvailable
     *
     * @throws ReflectionException if canShow method is not defined
     */
    public function canShow_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown(
        $isAdminNotificationModuleEnabled,
        $isAdminNotificationModuleOutputEnabled,
        $isNotificationToolbarAllowedByACL,
        $isUpdateAvailable
    ) {
        $this->boltHelperMock->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleEnabled);
        $this->currentMock->method('isOutputEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleOutputEnabled);
        $this->sessionMock->method('isAllowed')->with('admin/system/adminnotification/show_toolbar')
            ->willReturn($isNotificationToolbarAllowedByACL);
        $this->updaterMock->method('isUpdateAvailable')->willReturn($isUpdateAvailable);
        $this->assertEquals(
            $isAdminNotificationModuleEnabled && $isAdminNotificationModuleOutputEnabled
            && $isNotificationToolbarAllowedByACL && $isUpdateAvailable,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, '_canShow')
        );
    }

    /**
     * Data provider for {@see canShow_withVariousResultsOfTheCheckMethods}
     * @see Bolt_Boltpay_TestHelper::getAllBooleanCombinations
     *
     * @return array of all possible combinations for 4 boolean variables
     */
    public function canShow_withVariousResultsOfTheCheckMethodsProvider()
    {
        return Bolt_Boltpay_TestHelper::getAllBooleanCombinations(4);
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
     * that _toHtml is gated behind {@see Bolt_Boltpay_Block_Adminhtml_Notification::_canShow}
     *
     * @covers ::_toHtml
     * @dataProvider _toHtml_withStubbedCanShowMethodProvider
     *
     * @param bool $canShow stubbed result of {@see Bolt_Boltpay_Block_Adminhtml_Notification::_canShow}
     *
     * @throws ReflectionException if _toHtml method is not defined
     * @throws Exception if test class name is not defined
     */
    public function _toHtml_withStubbedCanShowMethod_rendersTemplateOnlyIfCanShowIsTrue($canShow)
    {
        $currentMock = $this->getTestClassPrototype()->setMethods(array('_canShow', 'renderView'))->getMock();
        $currentMock->expects($this->once())->method('_canShow')->willReturn($canShow);
        $currentMock->expects($canShow ? $this->once() : $this->never())->method('renderView');
        Bolt_Boltpay_TestHelper::callNonPublicFunction($currentMock, '_toHtml');
    }

    /**
     * Data provider for {@see _toHtml_withStubbedCanShowMethod_rendersTemplateOnlyIfCanShowIsTrue}
     *
     * @return bool[][] stubbed results of canShow method
     */
    public function _toHtml_withStubbedCanShowMethodProvider()
    {
        return array(
            array('_canShow' => true),
            array('_canShow' => false)
        );
    }
}
