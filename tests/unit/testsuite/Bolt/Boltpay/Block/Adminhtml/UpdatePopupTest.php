<?php

use GuzzleHttp\Exception\RequestException;
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
     * @var MockObject|Boltpay_Guzzle_ApiClient mocked instance of API client
     */
    private $apiClientMock;

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Exception if test class name is not defined
     */
    protected function setUp()
    {
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')->getMock();
        $this->apiClientMock = $this->getClassPrototype('Boltpay_Guzzle_ApiClient')->getMock();
        $this->sessionMock = $this->getClassPrototype('admin/session')
            ->setMethods(array('isAllowed', 'isFirstPageAfterLogin', 'getUser'))->getMock();
        $this->updaterMock = $this->getClassPrototype('boltpay/updater')
            ->setMethods(array('isUpdateAvailable', 'getLatestVersion', 'getUpdateSeverity'))->getMock();
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('boltHelper', 'isOutputEnabled', '_getSession', 'getUpdater', 'getLayout'))
            ->getMock();
        $this->boltHelperMock->method('getApiClient')->willReturn($this->apiClientMock);
        $this->currentMock->method('boltHelper')->willReturn($this->boltHelperMock);
        $this->currentMock->method('_getSession')->willReturn($this->sessionMock);
        $this->currentMock->method('getUpdater')->willReturn($this->updaterMock);
        $this->currentMock->method('getLayout')->willReturn(Mage::getSingleton('core/layout'));
    }

    /**
     * @test
     * that shouldDisplay returns true only if all of the following conditions are met
     * 1. Mage_AdminNotification is enabled
     * 2. Mage_AdminNotification output is not disabled
     * 3. Notification is allowed by ACL for current administrator
     * 4. An update is available
     * 5. Notifications are not disabled for non critical updates (only for minor updates)
     * 6. The current page being the first one after successful admin login
     *
     * @covers ::shouldDisplay
     * @dataProvider shouldDisplay_withVariousResultsOfTheCheckMethodsProvider
     *
     * @param bool $isAdminNotificationModuleEnabled stubbed result of {@see Mage_Core_Helper_Abstract::isModuleEnabled}
     * @param bool $isAdminNotificationModuleOutputEnabled stubbed result of {@see Mage_Adminhtml_Block_Template::isOutputEnabled}
     * @param bool $isNotificationToolbarAllowedByACL stubbed result of {@see Mage_Admin_Model_Session::isAllowed}
     * @param bool $isUpdateAvailable stubbed result of {@see Bolt_Boltpay_Model_Updater::isUpdateAvailable}
     * @param bool $isFirstPageAfterLogin stubbed result of {@see Mage_Admin_Model_Session::isFirstPageAfterLogin}
     *
     * @throws ReflectionException if shouldDisplay method is not defined
     */
    public function shouldDisplay_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown(
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
        $this->updaterMock->method('getUpdateSeverity')->willReturn('patch');
        $this->assertEquals(
            $isAdminNotificationModuleEnabled && $isAdminNotificationModuleOutputEnabled
            && $isNotificationToolbarAllowedByACL && $isUpdateAvailable && $isFirstPageAfterLogin,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'shouldDisplay')
        );
    }

    /**
     * Data provider for {@see shouldDisplay_withVariousResultsOfTheCheckMethods_determinesIfBlockShouldBeShown}
     * @see Bolt_Boltpay_TestHelper::getAllBooleanCombinations
     *
     * @return array of all possible combinations for 5 boolean variables
     */
    public function shouldDisplay_withVariousResultsOfTheCheckMethodsProvider()
    {
        return Bolt_Boltpay_TestHelper::getAllBooleanCombinations(5);
    }

    /**
     * @test
     * that shouldDisplay returns true only once per minor version for the same admin
     * by storing the displayed versions in admin user extra data
     *
     * @dataProvider shouldDisplay_withMinorUpdateProvider
     *
     * @param mixed  $adminUserExtraData current admin user extra data
     * @param string $latestVersion available for update
     * @param array  $expectedAdminUserExtraDataOnSave admin user extra data expected to be saved
     * @param bool   $nonCriticalUpdateNotificationsDisabled configuration value
     * @param bool   $expectedResult of the method call
     *
     * @throws ReflectionException if shouldDisplay method is not defined
     */
    public function shouldDisplay_withMinorUpdate_returnsTrueOncePerMinorUpdateForSameAdmin(
        $adminUserExtraData,
        $latestVersion,
        $expectedAdminUserExtraDataOnSave,
        $nonCriticalUpdateNotificationsDisabled,
        $expectedResult
    ) {
        $this->boltHelperMock->method('isModuleEnabled')->with('Mage_AdminNotification')->willReturn(true);
        $this->currentMock->method('isOutputEnabled')->with('Mage_AdminNotification')->willReturn(true);
        $this->sessionMock->method('isAllowed')->with('admin/system/adminnotification/show_toolbar')->willReturn(true);
        $this->sessionMock->method('isFirstPageAfterLogin')->willReturn(true);
        $this->updaterMock->method('isUpdateAvailable')->willReturn(true);
        $this->updaterMock->method('getUpdateSeverity')->willReturn('minor');
        $this->updaterMock->method('getLatestVersion')->willReturn($latestVersion);
        $adminUserMock = $this->getClassPrototype('Mage_Admin_Model_User')->setMethods(array('getExtra', 'saveExtra'))
            ->getMock();
        $this->sessionMock->method('getUser')->willReturn($adminUserMock);
        $adminUserMock->expects($nonCriticalUpdateNotificationsDisabled ? $this->never() : $this->once())
            ->method('getExtra')->willReturn($adminUserExtraData);
        $adminUserMock->expects($expectedResult ? $this->once() : $this->never())->method('saveExtra')
            ->with($expectedAdminUserExtraDataOnSave);
        $this->boltHelperMock->method('getShouldDisableNotificationsForNonCriticalUpdates')
            ->willReturn($nonCriticalUpdateNotificationsDisabled);

        $this->assertEquals(
            $expectedResult,
            Bolt_Boltpay_TestHelper::callNonPublicFunction($this->currentMock, 'shouldDisplay')
        );
    }

    /**
     * Data provider for {@see shouldDisplay_withMinorUpdate_returnsTrueOncePerMinorUpdateForSameAdmin}
     *
     * @return array[] containing admin user extra data, latest version, expected extra data saved and result
     */
    public function shouldDisplay_withMinorUpdateProvider()
    {
        return array(
            array(
                'adminUserExtraData'                     => array(),
                'latestVersion'                          => '1.5.0',
                'expectedAdminUserExtraDataOnSave'       => array('bolt_minor_update_popups_shown' => array('1.5.0')),
                'nonCriticalUpdateNotificationsDisabled' => false,
                'expectedResult'                         => true,
            ),
            array(
                'adminUserExtraData'                     => null,
                'latestVersion'                          => '1.5.0',
                'expectedAdminUserExtraDataOnSave'       => array('bolt_minor_update_popups_shown' => array('1.5.0')),
                'nonCriticalUpdateNotificationsDisabled' => false,
                'expectedResult'                         => true,
            ),
            array(
                'adminUserExtraData'                     => array('bolt_minor_update_popups_shown' => array('1.5.0')),
                'latestVersion'                          => '1.5.0',
                'expectedAdminUserExtraDataOnSave'       => null,
                'nonCriticalUpdateNotificationsDisabled' => false,
                'expectedResult'                         => false,
            ),
            array(
                'adminUserExtraData'                     => array(
                    'configState'                    => array(
                        'sales_general'              => '1',
                        'sales_totals_sort'          => '0',
                        'sales_reorder'              => '0',
                        'sales_identity'             => '0',
                        'sales_minimum_order'        => '1',
                        'sales_dashboard'            => '1',
                        'sales_gift_options'         => '0',
                        'sales_msrp'                 => '1',
                        'payment_keys'               => '1',
                        'payment_where_to_add_bolt'  => '1',
                        'payment_additional_options' => '1',
                        'payment_advanced_settings'  => '1',
                        'persistent_options'         => '1',
                    ),
                    'bolt_minor_update_popups_shown' => array('1.5.0')
                ),
                'latestVersion'                          => '1.6.0',
                'expectedAdminUserExtraDataOnSave'       => array(
                    'configState'                    => array(
                        'sales_general'              => '1',
                        'sales_totals_sort'          => '0',
                        'sales_reorder'              => '0',
                        'sales_identity'             => '0',
                        'sales_minimum_order'        => '1',
                        'sales_dashboard'            => '1',
                        'sales_gift_options'         => '0',
                        'sales_msrp'                 => '1',
                        'payment_keys'               => '1',
                        'payment_where_to_add_bolt'  => '1',
                        'payment_additional_options' => '1',
                        'payment_advanced_settings'  => '1',
                        'persistent_options'         => '1',
                    ),
                    'bolt_minor_update_popups_shown' => array('1.5.0', '1.6.0')
                ),
                'nonCriticalUpdateNotificationsDisabled' => false,
                'expectedResult'                         => true,
            ),
            array(
                'adminUserExtraData'                     => array(
                    'bolt_minor_update_popups_shown' => array(
                        '1.5.0',
                        '1.6.0'
                    )
                ),
                'latestVersion'                          => '1.6.0',
                'expectedAdminUserExtraDataOnSave'       => null,
                'nonCriticalUpdateNotificationsDisabled' => false,
                'expectedResult'                         => false,
            ),
            array(
                'adminUserExtraData'                     => array(),
                'latestVersion'                          => '1.5.0',
                'expectedAdminUserExtraDataOnSave'       => null,
                'nonCriticalUpdateNotificationsDisabled' => true,
                'expectedResult'                         => false,
            ),
        );
    }

    /**
     * @test
     * that getReleaseDownloadLink returns release archive based on latest version if it is found on the server
     *
     * @covers ::getReleaseDownloadLink
     */
    public function getReleaseDownloadLink_withValidReleaseUrl_returnsReleaseDownloadLink()
    {
        $urlCheckMock = $this->getClassPrototype('Psr\Http\Message\ResponseInterface')
            ->setMethods(array('getStatusCode'))->getMockForAbstractClass();
        $expectedLink = 'https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.1.3.zip';
        $this->apiClientMock->expects($this->once())->method('head')->with($expectedLink)->willReturn($urlCheckMock);
        $urlCheckMock->expects($this->once())->method('getStatusCode')->willReturn(200);
        $this->assertEquals(
            $expectedLink,
            $this->currentMock->getReleaseDownloadLink('2.1.3')
        );
    }

    /**
     * @test
     * that getReleaseDownloadLink returns empty string if release archive is not found on the server
     *
     * @covers ::getReleaseDownloadLink
     */
    public function getReleaseDownloadLink_withInvalidReleaseUrl_returnsEmptyString()
    {
        $urlCheckMock = $this->getClassPrototype('Psr\Http\Message\ResponseInterface')
            ->setMethods(array('getStatusCode'))->getMockForAbstractClass();
        $expectedLink = 'https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.1.3.zip';
        $this->apiClientMock->expects($this->once())->method('head')->with($expectedLink)->willReturn($urlCheckMock);
        $urlCheckMock->expects($this->once())->method('getStatusCode')->willReturn(403);
        $this->assertEquals(
            '',
            $this->currentMock->getReleaseDownloadLink('2.1.3')
        );
    }

    /**
     * @test
     * that getReleaseDownloadLink returns empty string if release archive url check request fails
     *
     * @covers ::getReleaseDownloadLink
     */
    public function getReleaseDownloadLink_withRequestException_returnsEmptyString()
    {

        $expectedLink = 'https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.1.3.zip';
        $requestException = new RequestException('', null);
        $this->apiClientMock->expects($this->once())->method('head')->with($expectedLink)
            ->willThrowException($requestException);
        $this->assertEquals('', $this->currentMock->getReleaseDownloadLink('2.1.3'));
    }
}
