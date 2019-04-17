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
 * This installer add Datadog Configuration to new Extra Options area
 *
 * /** @var Mage_Core_Model_Resource_Setup $installer
 */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 1.4.1.1 updates', null, 'bolt_install.log');

$configTable = $installer->getTable('core/config_data');
/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();
$extraOptions = $connection->fetchAll("SELECT * FROM $configTable WHERE path = 'payment/boltpay/extra_options'");


$datadogOptions = array(
    'datadogKey' => Bolt_Boltpay_Helper_DataDogTrait::$boltDataDogKey,
    'datadogKeySeverity' => 'error'
);
if (count($extraOptions)) {
    foreach ($extraOptions as $extraRow) {
        $extraRowConfigId = $extraRow['config_id'];
        $extraRowValue = $extraRow['value'];
        $extraRowData = Bolt_Boltpay_Helper_Data::isJSON($extraRowValue) ? json_decode($extraRowValue, true) : array();
        $extraOptionsJSON = json_encode(array_merge($extraRowData, $datadogOptions));
        $connection->query("UPDATE $configTable SET value = '$extraOptionsJSON' WHERE config_id = $extraRowConfigId");

    }
} else {

    /** @var  $config Mage_Core_Model_Config */
    $config = new Mage_Core_Model_Config();
    $extraOptionsJSON = json_encode($datadogOptions);
    $config->saveConfig('payment/boltpay/extra_options', $extraOptionsJSON);

}
Mage::log('Bolt 1.4.1.1 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();

