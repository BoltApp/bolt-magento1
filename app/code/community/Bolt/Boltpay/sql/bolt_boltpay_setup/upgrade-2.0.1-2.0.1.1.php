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
 * This installer does the following:
 * 1. Makes sure the "deferred" status is not the default status for state "payment review"
 */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

Mage::log('Installing Bolt 2.0.1.1 updates', null, 'bolt_install.log');

/** @var Magento_Db_Adapter_Pdo_Mysql $connection */
$connection = $installer->getConnection();

$statusStateTable = $installer->getTable('sales/order_status_state');
try {
    $connection->query(
        "UPDATE $statusStateTable SET is_default = 0 WHERE state = :state AND status = :status",
        array(
            'state' => Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            'status' => Bolt_Boltpay_Model_Payment::ORDER_DEFERRED
        )
    );
} catch (\Exception $e) {
    Mage::log("Bolt 2.0.1.1 - " . $e->getMessage(), null, 'bolt_install.log');
}

Mage::log('Bolt 2.0.1.1 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
