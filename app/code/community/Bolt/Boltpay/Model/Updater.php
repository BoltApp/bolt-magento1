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
 * Class Bolt_Boltpay_Model_Updater
 *
 * The Magento Model class that provides utility methods related to module updates
 */
class Bolt_Boltpay_Model_Updater extends Mage_Core_Model_Abstract
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /** @var string Magento Connect default community channel */
    const MAGENTO_CONNECT_CHANNEL = "https://connect20.magentocommerce.com/community";

    /** @var string Boltpay Magento Connect package id */
    const MAGENTO_CONNECT_BOLT_PACKAGE = 'bolt+Bolt';

    /** @var string Magento Connect package XML release field */
    const MAGENTO_CONNECT_PACKAGE_XML_RELEASE_FIELD = 'r';

    /** @var string Magento Connect package XML stability field */
    const MAGENTO_CONNECT_PACKAGE_XML_STABILITY_FIELD = 's';

    /** @var string Magento Connect package XML version field */
    const MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD = 'v';

    /** @var string Boltpay Github repository API endpoint URL */
    const GITHUB_API_BOLT_REPOSITORY_URL = 'https://api.github.com/repos/BoltApp/bolt-magento1/';

    /** @var string Session storage key for latest Bolt version */
    const LATEST_BOLT_VERSION_SESSION_KEY = 'latest_bolt_version';

    /**
     * @var Varien_Io_File filesystem client instance
     */
    protected $_ioFile;

    /**
     * Set IO file property
     */
    protected function _construct()
    {
        $this->_ioFile = new Varien_Io_File();
        $this->_ioFile->cd(BP);
        parent::_construct();
    }

    /**
     * Gets the latest module version available on Magento Marketplace through Magento Connect
     *
     * @return string latest available marketplace version
     */
    protected function getLatestMarketplaceVersion()
    {
        $releases = $this->getBoltMarketplaceReleases();
        if (empty($releases)) {
            return '';
        }

        $versions = array_column($releases, self::MAGENTO_CONNECT_PACKAGE_XML_VERSION_FIELD);
        usort($versions, 'version_compare');
        return array_pop($versions);
    }

    /**
     * Gets latest release version via Github API
     *
     * @return string latest release version available on github
     */
    protected function getLatestGithubVersion()
    {
        try {
            $response = $this->boltHelper()->getApiClient()->get(
                self::GITHUB_API_BOLT_REPOSITORY_URL . 'releases/latest'
            );
            $responseBody = Mage::helper('core')->jsonDecode($response->getBody());
            return @$responseBody['tag_name'];
        } catch (GuzzleException $e) {
            $this->boltHelper()->notifyException($e);
        }

        return '';
    }

    /**
     * Determines whether Git should be used as the source of the latest Boltpay version
     *
     * @return bool true if feature switch for using git version for updates is enabled, otherwise false
     */
    protected function shouldUseGithubVersion()
    {
        try {
            return Mage::getSingleton("boltpay/featureSwitch")
                ->isSwitchEnabled(Bolt_Boltpay_Model_FeatureSwitch::BOLT_UPDATE_USE_GIT_SWITCH_NAME);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Determines if an update or patch is available
     *
     * @return bool true if an update or patch is available, otherwise false
     */
    public function isUpdateAvailable()
    {
        $currentVersion = Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
        return version_compare($currentVersion, $this->getLatestVersion(), '<');
    }

    /**
     * Returns latest Bolt version from session
     * If not set, retrieves either from Magento Connect or Github
     *
     * @return string latest version
     */
    public function getLatestVersion()
    {
        if (!$this->_getSession()->hasData(self::LATEST_BOLT_VERSION_SESSION_KEY)) {
            $latestVersion = $this->shouldUseGithubVersion()
                ? $this->getLatestGithubVersion()
                : $this->getLatestMarketplaceVersion();

            $this->_getSession()->setData(self::LATEST_BOLT_VERSION_SESSION_KEY, $latestVersion);
        }

        return $this->_getSession()->getData(self::LATEST_BOLT_VERSION_SESSION_KEY);
    }

    /**
     * Returns available update severity based on version delta
     *
     * @return string either major, minor or patch
     */
    public function getUpdateSeverity()
    {
        $currentVersion = Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
        if ($this->hasPatch($currentVersion)) {
            return 'patch';
        }

        $installed = explode('.', $currentVersion);
        $latest = explode('.', $this->getLatestVersion());
        if ($latest[0] - $installed[0] > 0) {
            return 'major';
        } else if ($latest[1] - $installed[1] > 0) {
            return 'minor';
        } else if ($latest[2] - $installed[2] > 0) {
            return 'patch';
        }
    }

    /**
     * Adds update message to admin notification if identical one was not already added
     */
    public function addUpdateMessage()
    {
        if (!$this->boltHelper()->isModuleEnabled('Mage_AdminNotification')) {
            return;
        }

        $latestVersion = $this->getLatestVersion();
        switch ($this->getUpdateSeverity()) {
            default:
            case 'major':
                $notificationSeverity = Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR;
                $title = $this->boltHelper()->__('Bolt version %s is now available!', $latestVersion);
                break;
            case 'minor':
                if ($this->boltHelper()->getShouldDisableNotificationsForNonCriticalUpdates()) {
                    return;
                }

                $notificationSeverity = Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE;
                $title = $this->boltHelper()->__('Bolt version %s is now available!', $latestVersion);
                break;
            case 'patch':
                $notificationSeverity = Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL;
                $title = $this->boltHelper()->__('Bolt version %s is available to address a CRITICAL issue.', $latestVersion);
                break;
        }

        $description = $this->boltHelper()->__(
            'Installed version: %s. Latest version: %s',
            Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion(),
            $latestVersion
        );
        /** @var Mage_AdminNotification_Model_Resource_Inbox_Collection $collection */
        $collection = Mage::getModel('adminnotification/inbox')->getCollection()
            ->addFieldToFilter('title', $title)
            ->addFieldToFilter('description', $description);
        if ($collection->getSize() == 0) {
            Mage::getSingleton('adminnotification/inbox')->add($notificationSeverity, $title, $description, '', false);
        }
    }

    /**
     * Determines if a patch exists for provided version of the Bolt module by traversing Github releases
     * and checking for difference only in the patch part of the version
     * Available versions are retrieved from tags endpoint of the Github API
     * @see https://developer.github.com/v3/repos/#list-tags
     *
     * @param string $version for which to retrieve patch version
     *
     * @return bool true if patch is found found, otherwise false
     */
    public function hasPatch($version)
    {
        if (!$this->_getSession()->hasData('bolt_has_patch_for_version_' . $version)) {
            $currentVersion = explode('.', $version);
            $tagsUrl = self::GITHUB_API_BOLT_REPOSITORY_URL . 'tags' . '?' . http_build_query(array('per_page' => 100));
            do {
                try {
                    $tagResponse = $this->boltHelper()->getApiClient()->get($tagsUrl);
                } catch (GuzzleException $e) {
                    $this->boltHelper()->notifyException($e);
                    break;
                }

                $tags = json_decode($tagResponse->getBody());
                foreach ($tags as $tag) {
                    $newVersion = explode('.', ltrim($tag->name, 'v'));
                    if ($currentVersion[0] == $newVersion[0]
                        && $currentVersion[1] == $newVersion[1]
                        && (int)$currentVersion[2] < (int)$newVersion[2]) {
                        $this->_getSession()->setData('bolt_has_patch_for_version_' . $version, true);
                        return true;
                    }
                }

                /**
                 * @link https://developer.github.com/v3/guides/traversing-with-pagination/
                 */
                $link = $tagResponse->getHeaderLine('link');
                $tagsUrl = preg_match("/.*\<(?'url'.*?)\>; rel=\"next\"/", $link, $matches) ? $matches['url'] : null;
            } while ($tagsUrl);
            $this->_getSession()->setData('bolt_has_patch_for_version_' . $version, false);
        }

        return $this->_getSession()->getData('bolt_has_patch_for_version_' . $version);
    }

    /**
     * Gets Magento Connect Manager url
     * @see \Mage_Connect_Adminhtml_Extension_LocalController::indexAction
     *
     * @return string|null connect manager url for the store or null if not found
     */
    public function getConnectManagerURL()
    {
        $downloaderDir = 'downloader';
        if (!$this->_ioFile->fileExists($downloaderDir, false)) {
            // If default downloader directory is not found, it could have been renamed for security reasons
            // Try to find it based on configuration file name
            $downloaderDir = null;
            try {
                foreach ($this->_ioFile->ls(Varien_Io_File::GREP_DIRS) as $dir) {
                    $dirName = $dir['text'];
                    //make sure the config file exists and contains expected content
                    if ($this->_ioFile->open(array('path' => $dirName))
                        && $this->_ioFile->fileExists('config.ini')
                        && strpos($this->_ioFile->read('config.ini'), 'root_channel=') !== false) {
                        $downloaderDir = $dirName;
                        break;
                    }
                }
            } catch (Exception $e) {
                $this->boltHelper()->notifyException($e);
            }
        }

        return $downloaderDir ? Mage::getBaseUrl('web') . $downloaderDir . '/?updates=yes' : null;
    }

    /**
     * Checks whether Magento Connect Rest class is available
     *
     * @return bool true if available, otherwise false
     */
    protected function isMagentoConnectRestClientAvailable()
    {
        return class_exists('Mage_Connect_Rest');
    }

    /**
     * Returns Bolt releases from Magento Marketplace either by using {@see Mage_Connect_Rest}
     * or by directly retrieving releases XML file from Magento Connect
     *
     * @return array containing Marketplace releases of the Bolt module
     */
    public function getBoltMarketplaceReleases()
    {
        if ($this->isMagentoConnectRestClientAvailable()) {
            $rest = new Mage_Connect_Rest('https');
            $rest->setChannel(self::MAGENTO_CONNECT_CHANNEL);
            $releases = $rest->getReleases(self::MAGENTO_CONNECT_BOLT_PACKAGE);
        } else {
            try {
                /** @var string $releasesXML */
                /**
                 * Example of the expected value for $releasesXML
                 *
                 * <?xml version="1.0" ?>
                 * <releases>
                 *  <r>
                 *      <v>1.1.4</v>
                 *      <s>stable</s>
                 *      <d>2020-04-14</d>
                 *  </r>
                 *  <r>
                 *      <v>1.0.6</v>
                 *      <s>stable</s>
                 *      <d>2020-04-14</d>
                 *  </r>
                 * </releases>
                 */
                $releasesXML = $this->boltHelper()->getApiClient()
                    ->get(sprintf("%s/%s/releases.xml", self::MAGENTO_CONNECT_CHANNEL, self::MAGENTO_CONNECT_BOLT_PACKAGE))
                    ->getBody()->getContents();
                $dom = new DOMDocument();
                $dom->loadXML($releasesXML);
                $releases = array();
                /** @var DOMNode $releaseNode */
                foreach ($dom->getElementsByTagName(self::MAGENTO_CONNECT_PACKAGE_XML_RELEASE_FIELD) as $releaseNode) {
                    $releaseData = array();
                    /** @var DOMNode $releaseField */
                    foreach ($releaseNode->childNodes as $releaseField) {
                        if ($releaseField->nodeName !== '#text') {
                            $releaseData[$releaseField->nodeName] = $releaseField->nodeValue;
                        }
                    }

                    $releases[] = $releaseData;
                }
            } catch (GuzzleException $e) {
                $this->boltHelper()->notifyException($e);
                $releases = array();
            }
        }

        return $releases;
    }

    /**
     * Convenience method that returns admin session singleton
     *
     * @return Mage_Adminhtml_Model_Session admin session singleton
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }
}
