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

class Bolt_Boltpay_Model_Setup extends Mage_Eav_Model_Entity_Setup
{
    /**
     * Run resource upgrade files from $oldVersion to $newVersion
     *
     * @param string $oldVersion
     * @param string $newVersion
     * @return Mage_Core_Model_Resource_Setup
     */
    protected function _upgradeResourceDb($oldVersion, $newVersion)
    {
        // We can't update feature switches right now, because Magento settings (test flag and merchant key)
        // isn't available yet, so we set flag to do it later
        error_log('update');
        Mage::getSingleton("boltpay/featureSwitch")->needUpdateFeatureSwitches();
        return parent::_upgradeResourceDb($oldVersion, $newVersion);
    }

    /**
     * Run resource installation file
     *
     * @param string $newVersion
     * @return Mage_Core_Model_Resource_Setup
     */
    protected function _installResourceDb($newVersion)
    {
        error_log('install');
        Mage::getSingleton("boltpay/featureSwitch")->needUpdateFeatureSwitches();
        return parent::_installResourceDb($newVersion);
    }
}