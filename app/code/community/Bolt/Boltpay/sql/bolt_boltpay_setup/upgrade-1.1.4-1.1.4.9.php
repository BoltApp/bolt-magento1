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
 * This installer unencrypt publishable key
 *
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 1.1.4.9 updates', null, 'bolt_install.log');

$configTable = $installer->getTable('core/config_data');
/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();
$query = $connection->query("SELECT * FROM $configTable WHERE path LIKE 'payment/boltpay/publishable_key_%'");

$resultData = $query->fetchAll();

if (count($resultData)) {
    $coreHelper = Mage::helper('core');
    foreach ($resultData as $row) {
        $keyValue = $row['value'];
        $configId = $row['config_id'];
        $decryptedKey = $coreHelper->decrypt($keyValue);

        if ($decryptedKey && $configId) {
            $connection->query("UPDATE $configTable SET value=? WHERE config_id=?", array($decryptedKey, $configId));
        } else {
            Mage::log("Unexpected $configTable entry.  config_id[$configId] and publishable_key[$decryptedKey]", null, 'bolt_install.log');
            Mage::log($row, null, 'bolt_install.log');
        }
    }
} else {
    Mage::log('Bolt 1.1.4.9: did not find any publishable keys', null, 'bolt_install.log');
}

Mage::log('Bolt 1.1.4.9 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
