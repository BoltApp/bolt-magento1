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
 * This installer adds an index for sales_flat_quote->parent_quote_id
 *
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

Mage::log('Installing Bolt 1.1.3 updates', null, 'bolt_install.log');

$quoteTable = $installer->getTable('sales/quote');

if ($installer->getConnection()->isTableExists($quoteTable)) {
    $table = $installer->getConnection();
    $table->addIndex(
        $quoteTable,
        $installer->getIdxName(
            'sales/quote',
            'parent_quote_id'
        ),
        'parent_quote_id'
    );
}

Mage::log('Bolt 1.1.3 updates installation completed', null, 'bolt_install.log');

$installer->endSetup();
