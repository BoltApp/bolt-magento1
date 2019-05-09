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
 * This installer adds is_bolt_pdp data to check if the session quote is generated on the PDP
 */
$installer = Mage::getResourceModel('sales/setup', 'sales_setup');
$installer->startSetup();

Mage::log('Installing Bolt 1.4.1.2 updates', null, 'bolt_install.log');

if (!$installer->getConnection()->tableColumnExists($installer->getTable('sales/quote'), "is_bolt_pdp")) {
    $installer->addAttribute(
        "quote",
        "is_bolt_pdp",
        array(
            "type" => "boolean",
            'default' => false
        )
    );
}

Mage::log('Bolt 1.4.1.2 update installation completed', null, 'bolt_install.log');

$installer->endSetup();
