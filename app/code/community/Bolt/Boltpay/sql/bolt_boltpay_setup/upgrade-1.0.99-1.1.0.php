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
 * This installer adds the Bolt order token and parent quote id for bolt order tracking.
 */
/* @var Mage_Sales_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 1.1.0 updates', null, 'bolt_install.log');

if (!$installer->getConnection()->tableColumnExists($installer->getTable('sales/quote'), "parent_quote_id")) {
    $installer->addAttribute(
        "quote", "parent_quote_id", array(
            "type"       => "int",
            "label"      => "Original Quote ID",
            "input"      => "hidden",
            "visible"    => false,
            "required"   => false,
            "unique"     => false,
            "note"       => "Original Quote ID"
        )
    );
}

Mage::log('Bolt 1.1.0 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
