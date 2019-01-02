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
 * This installer adds user session id data to quote table
 */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 0.0.17 updates', null, 'bolt_install.log');

if (!$installer->getConnection()->tableColumnExists($installer->getTable('sales/quote'), "user_session_id")) {
    $installer->addAttribute(
        "quote", "user_session_id", array(
        "type"       => "varchar",
        "label"      => "User Session ID",
        "input"      => "hidden",
        "visible"    => false,
        "required"   => false,
        "unique"     => false,
        "note"       => "User Session ID for the quote"
        )
    );
}
Mage::log('Bolt 0.0.17 update installation completed', null, 'bolt_install.log');

$installer->endSetup();
