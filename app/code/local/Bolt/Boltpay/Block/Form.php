<?php
/**
 * This sets the hidden Bolt payment form
 * that is used in callback to save the order
 *
 * @deprecated Getting order data is done through the Bolt Fetch API call
 */
class Bolt_Boltpay_Block_Form extends Mage_Payment_Block_Form_Cc {
    protected function _construct() {
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('boltpay/mark.phtml');
        $this->setMethodLabelAfterHtml($mark->toHtml());
        parent::_construct();
        $this->setTemplate('boltpay/form.phtml')->setMethodTitle('');
    }
}
