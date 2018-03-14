<?php
/**
 * This installer adds user session id data to quote table
 */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 0.0.17 updates', null, 'bolt_install.log');

$installer->addAttribute("quote", "user_session_id",  array(
    "type"       => "varchar",
    "label"      => "User Session ID",
    "input"      => "hidden",
    "visible"    => false,
    "required"   => false,
    "unique"     => false,
    "note"       => "User Session ID for the quote"
));

Mage::log('Bolt 0.0.17 update installation completed', null, 'bolt_install.log');

$installer->endSetup();