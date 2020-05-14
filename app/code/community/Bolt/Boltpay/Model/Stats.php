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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Model_Stats extends Mage_Core_Model_Resource_Db_Abstract
{
    use Bolt_Boltpay_BoltGlobalTrait;

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
