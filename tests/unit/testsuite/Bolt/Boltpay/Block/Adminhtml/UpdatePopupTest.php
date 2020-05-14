<?php

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Adminhtml_UpdatePopup
 */
class Bolt_Boltpay_Block_Adminhtml_UpdatePopupTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Block_Adminhtml_UpdatePopup';

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data mocked instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var MockObject|Bolt_Boltpay_Block_Adminhtml_UpdatePopup mocked instance of the class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Admin_Model_Session mocked instance of the admin session model
     */
    private $sessionMock;

    /**
     * @var MockObject|Bolt_Boltpay_Model_Updater mocked instance of the Boltpay updater model
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
            ->setMethods(array('isAllowed', 'isFirstPageAfterLogin'))->getMock();
        $this->updaterMock = $this->getClassPrototype('boltpay/updater')
            ->setMethods(array('isUpdateAvailable', 'getLatestVersion'))->getMock();
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
     * 5. The current page being the first one after successful admin login
     *
     * @covers ::_canShow
     * @dataProvider canShow_withVariousResultsOfTheCheckMethodsProvider
     *
     * @param bool $isAdminNotificationModuleEnabled
     * @param bool $isAdminNotificationModuleOutputEnabled
     * @param bool $isNotificationToolbarAllowedByACL
     * @param bool $isUpdateAvailable
     * @param bool $isFirstPageAfterLogin
     *
     * @throws ReflectionException if canShow method is not defined
     */
    public function canShow_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown(
        $isAdminNotificationModuleEnabled,
        $isAdminNotificationModuleOutputEnabled,
        $isNotificationToolbarAllowedByACL,
        $isUpdateAvailable,
        $isFirstPageAfterLogin
    ) {
        $this->boltHelperMock->method('isModuleEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleEnabled);
        $this->currentMock->method('isOutputEnabled')->with('Mage_AdminNotification')
            ->willReturn($isAdminNotificationModuleOutputEnabled);
        $this->sessionMock->method('isAllowed')->with('admin/system/adminnotification/show_toolbar')
            ->willReturn($isNotificationToolbarAllowedByACL);
        $this->sessionMock->method('isFirstPageAfterLogin')->willReturn($isFirstPageAfterLogin);
        $this->updaterMock->method('isUpdateAvailable')->willReturn($isUpdateAvailable);
        $this->assertEquals(
            $isAdminNotificationModuleEnabled && $isAdminNotificationModuleOutputEnabled
            && $isNotificationToolbarAllowedByACL && $isUpdateAvailable && $isFirstPageAfterLogin,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, '_canShow')
        );
    }

    /**
     * Data provider for {@see canShow_withVariousResultsOfTheCheckMethods}
     * @see Bolt_Boltpay_TestHelper::getAllBooleanCombinations
     *
     * @return array of all possible combinations for 5 boolean variables
     */
    public function canShow_withVariousResultsOfTheCheckMethodsProvider()
    {
        return Bolt_Boltpay_TestHelper::getAllBooleanCombinations(5);
    }

    /**
     * @test
     * that getReleaseDownloadLink returns release archive based on latest version
     *
     * @covers ::getReleaseDownloadLink
     */
    public function getReleaseDownloadLink_always_returnsReleaseDownloadLink()
    {
        $this->assertEquals(
            'https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.1.3.zip',
            $this->currentMock->getReleaseDownloadLink('2.1.3')
        );
    }
}
