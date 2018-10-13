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
 * This installer unencrypt publishable key
 *
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 1.3.4 updates', null, 'bolt_install.log');

$installer->addAttribute("quote", "bolt_reference",  array(
    "type"       => "varchar",
    "label"      => "Bolt Reference",
    "input"      => "hidden",
    "visible"    => false,
    "required"   => false,
    "unique"     => false,
    "note"       => "Bolt Reference for the quote"
));

Mage::log('Bolt 1.3.4 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
