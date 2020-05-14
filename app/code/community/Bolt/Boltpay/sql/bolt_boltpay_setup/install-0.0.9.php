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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * This installer does the following
 * 1. Creates a new attribute for customers entity called bolt_user_id
 * 2. Adds Bolt custom order status and order state of deferred
 */
Mage::log('Installing Bolt 0.0.9 updates', null, 'bolt_install.log');

$installer = $this;
$installer->startSetup();
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$installer->addAttribute(
    "customer",
    "bolt_user_id",
    array(
        "type"       => "varchar",
        "label"      => "Bolt User Id",
        "input"      => "hidden",
        "visible"    => false,
        "required"   => false,
        "unique"     => true,
        "note"       => "Bolt User Id Attribute"
    )
);

/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

$deferredStatusDoesntExists = !is_object($connection->query("SELECT status FROM $statusTable WHERE status = 'deferred'")->fetchObject());
$deferredStateDoesntExists = !is_object($connection->query("SELECT status FROM $statusStateTable WHERE status = 'deferred' AND state = 'deferred'")->fetchObject());

if ($deferredStatusDoesntExists) {
    // Insert statuses
    $connection->insertArray(
        $statusTable,
        array(
            'status',
            'label'
        ),
        array(
            array('status' => 'deferred', 'label' => 'Deferred'),
        )
    );

}

if ($deferredStateDoesntExists) {
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
                'status' => 'deferred',
                'state' => 'deferred',
                'is_default' => 1
            ),
        )
    );
}

Mage::log('Bolt 0.0.9 update installation completed', null, 'bolt_install.log');

$installer->endSetup();