<?php
/**
 * This installer does the following
 * 1. Creates a new attribute for customers entity called bolt_user_id
 */
$installer = $this;

$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 0.0.2 updates', null, 'bolt_install.log');

$installer->addAttribute("customer", "bolt_user_id",  array(
    "type"       => "varchar",
    "label"      => "Bolt User Id",
    "input"      => "hidden",
    "visible"    => false,
    "required"   => false,
    "unique"     => true,
    "note"       => "Bolt User Id Attribute"

));

Mage::log('Bolt 0.0.2 update installation completed', null, 'bolt_install.log');

$installer->endSetup();