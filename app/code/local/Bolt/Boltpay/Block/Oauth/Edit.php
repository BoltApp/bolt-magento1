<?php
class Bolt_Boltpay_Block_Oauth_Edit extends Mage_Adminhtml_Block_Widget_Form_Container {

    public function __construct()
    {
        $this->_controller  = 'oauth';
        $this->_mode = 'edit';
        $this->_blockGroup = 'boltpay';
        $this->_headerText = "Bolt Integration";
        parent::__construct();
        $this->_updateButton('save', 'label', Mage::helper('poll')->__('Publish'));
    }
}