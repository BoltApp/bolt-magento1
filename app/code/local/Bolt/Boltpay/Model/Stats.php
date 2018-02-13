<?php

class Bolt_Boltpay_Model_Stats extends Mage_Core_Model_Resource_Db_Abstract {

    protected function _construct() {
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
        $result = $adapter->fetchRow($select->from(array('order'=>$this->getTable('sales/order')), $fields)
            ->where('created_at > ?', '2016-10-01')
            ->where('created_at < ?', '2016-11-01')
            ->where('state NOT IN (\'canceled\', \'closed\')'));

        return $result;
    }

}