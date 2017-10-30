<?php
/**
 * This installer does the following
 * 1. Creates a new attribute for customers entity called bolt_user_id
 */
$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 0.0.4 updates', null, 'bolt_install.log');

$installer->addAttribute("customer", "bolt_user_id",  array(
    "type"       => "varchar",
    "label"      => "Bolt User Id",
    "input"      => "hidden",
    "visible"    => false,
    "required"   => false,
    "unique"     => true,
    "note"       => "Bolt User Id Attribute"

));

// Required tables
$statusTable = $installer->getTable('sales/order_status');
$statusStateTable = $installer->getTable('sales/order_status_state');

// Insert statuses
$installer->getConnection()->insertArray(
    $statusTable,
    array(
        'status',
        'label'
    ),
    array(
        array('status' => 'order_in_review', 'label' => 'Order In Review'),
        array('status' => 'authorized', 'label' => 'Authorized'),
        array('status' => 'deferred', 'label' => 'Deferred'),
        array('status' => 'ready_for_shipment', 'label' => 'Ready for Shipment'),
    )
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $statusStateTable,
    array(
        'status',
        'state',
        'is_default'
    ),
    array(
        array(
            'status' => 'order_in_review',
            'state' => 'pending_payment',
            'is_default' => 1
        ),
        array(
            'status' => 'authorized',
            'state' => 'authorized',
            'is_default' => 1
        ),
        array(
            'status' => 'deferred',
            'state' => 'payment_review',
            'is_default' => 1
        ),
        array(
            'status' => 'ready_for_shipment',
            'state' => 'ready_for_shipment',
            'is_default' => 1
        )
    )
);

Mage::log('Bolt 0.0.4 update installation completed', null, 'bolt_install.log');

$installer->endSetup();