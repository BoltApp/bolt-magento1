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

class Bolt_Boltpay_Model_Stats extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct() 
    {
        $this->_init('sales/order', 'entity_id');
    }

    public function getAggregatedData()
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select();
        $fields = array(
            'min_quote_id' => new Zend_Db_Expr('MIN(order.quote_id)'),
            'max_quote_id' => new Zend_Db_Expr('MAX(order.quote_id)'),
            'order_count' => new Zend_Db_Expr('COUNT(order.entity_id)'),
        );
        $result = $adapter->fetchRow(
            $select->from(array('order'=>$this->getTable('sales/order')), $fields)
            ->where('created_at > ?', '2016-10-01')
            ->where('created_at < ?', '2016-11-01')
            ->where('state NOT IN (\'canceled\', \'closed\')')
        );

        return $result;
    }

}
