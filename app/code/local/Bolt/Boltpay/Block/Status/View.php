<?php
class Bolt_Boltpay_Block_Status_View extends Mage_Adminhtml_Block_Template {
    public function getBoltUserIdStatus() {
        $customer = Mage::getModel('customer/customer');
        $eavConfig = Mage::getModel('eav/config');

        $attributes = $eavConfig->getEntityAttributeCodes('customer', $customer);

        if (in_array('bolt_user_id', $attributes)) {
            return "OK";
        } else {
            return "ERROR (Bolt User Id not found in Customer object)";
        }
    }

    public function getBoltInstallStatus() {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql        = "SELECT * FROM core_resource WHERE code = 'bolt_boltpay_setup'";
        $rows       = $connection->fetchAll($sql);

        if (empty($rows)) {
            return "No bolt_boltpay_setup entry found";
        } else {
            return implode(",", $rows[0]);
        }
    }
}