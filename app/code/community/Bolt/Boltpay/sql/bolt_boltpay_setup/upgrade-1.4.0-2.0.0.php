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
 * This installer does the following
 * 1. Creates the new statuses of "pending_bolt" and maps it to the standard Magento state of "pending_payment"
 * 2. Creates the new statuses of "canceled_bolt" and maps it to the standard Magento state of "canceled"
 */
Mage::log('Installing Bolt 2.0.0 updates', null, 'bolt_install.log');

$installer = $this;
$installer->startSetup();
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

$pendingBoltStatusDoesntExists = !is_object($connection->query("SELECT status FROM $statusTable WHERE status = 'pending_bolt'")->fetchObject());
$pendingBoltMappingDoesntExists = !is_object($connection->query("SELECT status FROM $statusStateTable WHERE status = 'pending_bolt' AND state = 'pending_payment'")->fetchObject());

$canceledBoltStatusDoesntExists = !is_object($connection->query("SELECT status FROM $statusTable WHERE status = 'canceled_bolt'")->fetchObject());
$canceledBoltMappingDoesntExists = !is_object($connection->query("SELECT status FROM $statusStateTable WHERE status = 'canceled_bolt' AND state = 'canceled'")->fetchObject());

if ($pendingBoltStatusDoesntExists) {
    // Insert statuses
    $connection->insertArray(
        $statusTable,
        array(
            'status',
            'label'
        ),
        array(
            array('status' => 'pending_bolt', 'label' => 'Pending Bolt Authorization'),
        )
    );

}

if ($pendingBoltMappingDoesntExists) {
    // Insert states and mapping of statuses to states
    $connection->insertArray(
        $statusStateTable,
        array(
            'status',
            'state',
            'is_default'
        ),
        array(
            array(
                'status' => 'pending_bolt',
                'state' => 'pending_payment',
                'is_default' => 0
            ),
        )
    );
}

if ($canceledBoltStatusDoesntExists) {
    // Insert statuses
    $connection->insertArray(
        $statusTable,
        array(
            'status',
            'label'
        ),
        array(
            array('status' => 'canceled_bolt', 'label' => 'Canceled by Bolt'),
        )
    );

}

if ($canceledBoltMappingDoesntExists) {
    // Insert states and mapping of statuses to states
    $connection->insertArray(
        $statusStateTable,
        array(
            'status',
            'state',
            'is_default'
        ),
        array(
            array(
                'status' => 'canceled_bolt',
                'state' => 'canceled',
                'is_default' => 0
            ),
        )
    );
}

Mage::log('Bolt 2.0.0 update installation completed', null, 'bolt_install.log');

$installer->endSetup();