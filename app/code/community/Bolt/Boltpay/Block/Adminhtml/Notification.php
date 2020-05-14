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

/**
 * Class Bolt_Boltpay_Block_Adminhtml_Notification
 *
 * Block used for displaying notification message related to Bolt in admin dashboard
 */
class Bolt_Boltpay_Block_Adminhtml_Notification extends Mage_Adminhtml_Block_Template
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @var string path to template file in theme
     */
    protected $_template = 'boltpay/notification.phtml';

    /**
     * Prevents rendering of the block if {@see _canShow} returns false
     *
     * @return string html of the rendered template or empty string
     */
    protected function _toHtml()
    {
        if (!$this->_canShow()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Determines if block should be rendered or not
     * Depends on:
     * 1. Mage_AdminNotification being enabled
     * 2. Mage_AdminNotification having output not disabled
     * 3. Notification allowed by ACL for current administrator
     * 4. An update is available
     *
     * @return bool true if all confitions are met, otherwise false
     */
    protected function _canShow()
    {
        return $this->boltHelper()->isModuleEnabled('Mage_AdminNotification')
            && $this->isOutputEnabled('Mage_AdminNotification')
            && $this->_getSession()->isAllowed('admin/system/adminnotification/show_toolbar')
            && $this->getUpdater()->isUpdateAvailable();

    }

    /**
     * Generates severity icon URL using {@see Mage_Adminhtml_Block_Notification_Window::getSeverityIconsUrl}
     *
     * @return string icon url for current update severity
     */
    public function getSeverityIconUrl()
    {
        /** @var Mage_Adminhtml_Block_Notification_Window $notificationWindowBlock */
        $notificationWindowBlock = $this->getLayout()->createBlock('adminhtml/notification_window');
        $severity = $this->getUpdater()->getUpdateSeverity();
        $notificationWindowBlock->setData(
            'notice_severity',
            $severity == 'patch' ? 'SEVERITY_CRITICAL' : 'SEVERITY_NOTICE'
        );
        return $notificationWindowBlock->getSeverityIconsUrl();
    }

    /**
     * Convenience method that returns admin session singleton
     *
     * @return Mage_Admin_Model_Session admin session singleton
     */
    protected function _getSession()
    {
        return Mage::getSingleton('admin/session');
    }

    /**
     * Convenience method that returns Boltpay updater singleton
     *
     * @return Bolt_Boltpay_Model_Updater bolt updater singleton
     */
    public function getUpdater()
    {
        return Mage::getSingleton('boltpay/updater');
    }
}
