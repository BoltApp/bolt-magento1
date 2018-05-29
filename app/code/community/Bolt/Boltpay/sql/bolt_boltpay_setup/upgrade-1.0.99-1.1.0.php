<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * This installer adds the Bolt order token and parent quote id for bolt order tracking.
 */
/* @var Mage_Sales_Model_Resource_Setup $installer */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 1.1.0 updates', null, 'bolt_install.log');

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

Mage::log('Bolt 1.1.0 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
