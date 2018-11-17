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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * This installer moves Bolt Primary Color to new Extra Options area
 *
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 1.3.7.9 updates', null, 'bolt_install.log');

$configTable = $installer->getTable('core/config_data');
/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();
$query = $connection->query("SELECT * FROM $configTable WHERE path = 'payment/boltpay/color'");

$colorSettings = $query->fetchAll( PDO::FETCH_OBJ );

if (count($colorSettings)) {
    foreach ($colorSettings as $colorDataRow) {
        $hexColor = $colorDataRow->value;

        if ($hexColor) {
            $extraOptionsJSON = json_encode(array('boltPrimaryColor' => $hexColor));
            $connection->query("INSERT INTO $configTable (scope, scope_id, path, value) VALUES ( {$colorDataRow->scope}, {$colorDataRow->scope_id}, 'payment/boltpay/extra_options', '$extraOptionsJSON')");
            $connection->query("DELETE FROM $configTable WHERE path = 'payment/boltpay/color'");
        }
    }
} else {
    Mage::log('Bolt 1.3.7.9: did not find a primary bolt color setting.', null, 'bolt_install.log');
}

Mage::log('Bolt 1.3.7.9 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
