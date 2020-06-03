<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Bolt_Boltpay_Block_Adminhtml_UpdatePopup
 *
 * Block used for displaying update popup in admin
 */
class Bolt_Boltpay_Block_Adminhtml_UpdatePopup extends Bolt_Boltpay_Block_Adminhtml_Notification
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @var string path to template file in theme
     */
    protected $_template = 'boltpay/update_popup.phtml';

    /**
     * Gets the link to the archive of the latest Bolt plugin version
     *
     * @param string $version for which to retrieve the release download link
     *
     * @return string The HTTPS S3 bucket link to the most current zip file or empty string if not found
     */
    public function getReleaseDownloadLink($version)
    {
        $url = sprintf(
            'https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v%s.zip',
            $version
        );
        try {
            $urlCheck = $this->boltHelper()->getApiClient()->head($url);
            if ($urlCheck->getStatusCode() !== 200) {
                return '';
            }
        } catch (GuzzleException $e) {
            return '';
        }

        return $url;
    }

    /**
     * Determines if block should be rendered or not
     * Depends on:
     * 1. Mage_AdminNotification module being enabled
     * 2. Mage_AdminNotification having output not disabled
     * 3. Notification allowed by ACL for current administrator
     * 4. An update for Boltpay being available
     * 5. The current page being the first one after successful admin login
     * 6. Notifications are not disabled for non critical updates (only for minor updates)
     * 7. Popup not displayed previously for the same version (only for minor updates)
     *
     * @return bool true if all conditions are met, otherwise false
     */
    protected function shouldDisplay()
    {
        $shouldDisplay = parent::shouldDisplay() && $this->_getSession()->isFirstPageAfterLogin();
        if ($shouldDisplay && $this->getUpdater()->getUpdateSeverity() == 'minor') {
            /** @var Mage_Admin_Model_User $adminUser */
            $adminUser = $this->_getSession()->getUser();
            /** @var array $adminExtra */
            $adminExtra = $adminUser->getExtra();
            $latestVersion = $this->getUpdater()->getLatestVersion();
            if (!is_array($adminExtra)
                || !key_exists('bolt_minor_update_popups_shown', $adminExtra)
                || !is_array($adminExtra['bolt_minor_update_popups_shown'])) {
                $adminExtra['bolt_minor_update_popups_shown'] = array();
            }

            if (in_array($latestVersion, $adminExtra['bolt_minor_update_popups_shown'])) {
                return false;
            }

            $adminExtra['bolt_minor_update_popups_shown'][] = $latestVersion;
            $adminUser->saveExtra($adminExtra);
        }

        return $shouldDisplay;
    }
}
