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
     * Run module modification files. Return version of last applied upgrade (false if no upgrades applied)
     *
     * @param string $actionType self::TYPE_*
     * @param string $fromVersion
     * @param string $toVersion
     * @return string|false
     * @throws Mage_Core_Exception
     */
    protected function _modifyResourceDb($actionType, $fromVersion, $toVersion)
    {
        // We can't update feature switches right now, because Magento settings
        // (test flag and merchant key) isn't available yet, so we set flag to do it later
        Bolt_Boltpay_Model_FeatureSwitch::$shouldUpdateFeatureSwitches = ($actionType === self::TYPE_DATA_UPGRADE);

        return parent::_modifyResourceDb($actionType, $fromVersion, $toVersion);
    }
}