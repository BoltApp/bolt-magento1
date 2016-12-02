<?php
class Bolt_Boltpay_Block_Oauth_Edit_Form extends Mage_Adminhtml_Block_Widget_Form {

    public function __construct()
    {
        parent::__construct();
        $this->setId('oauth_edit_form');
    }

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array(
            'id'     => 'edit_form',
            'method' => 'post',
            'action' => $this->getUrl('*/*/save', array()),
        ));
        $fieldset = $form->addFieldset(
            'settings',
            array('legend' => Mage::helper('boltpay')->__('Boltpay OAuth Settings'))
        );

        $fieldset->addField('consumer_key', 'text', array(
            'name'      => 'consumer_key',
            'label'     => Mage::helper('boltpay')->__('Consumer Key'),
            'title'     => Mage::helper('boltpay')->__('Consumer Key'),
            'required'  => true
        ));

        $fieldset->addField('consumer_token', 'text', array(
            'name'      => 'consumer_token',
            'label'     => Mage::helper('boltpay')->__('Consumer Token'),
            'title'     => Mage::helper('boltpay')->__('Consumer Token'),
            'required'  => true
        ));

        $fieldset->addField('access_token', 'text', array(
            'name'      => 'access_token',
            'label'     => Mage::helper('boltpay')->__('Access Token'),
            'title'     => Mage::helper('boltpay')->__('Access Token'),
            'required'  => true
        ));

        $fieldset->addField('access_token_secret', 'text', array(
            'name'      => 'access_token_secret',
            'label'     => Mage::helper('boltpay')->__('Access Token Secret'),
            'title'     => Mage::helper('boltpay')->__('Access Token Secret'),
            'required'  => true
        ));

        $fieldset->addField('continue_button', 'note', array(
            'text' => $this->getChildHtml('continue_button'),
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}