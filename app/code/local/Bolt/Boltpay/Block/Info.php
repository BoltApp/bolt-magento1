<?php
/**
 * This generated Bolt Payment information
 * Its primarily used in sending out order confirmation
 * emails from the merchant
 */
class Bolt_Boltpay_Block_Info extends Mage_Payment_Block_Info {
    public function _construct() {

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            $this->setTemplate('boltpay/info.phtml');
        }
    }
}
