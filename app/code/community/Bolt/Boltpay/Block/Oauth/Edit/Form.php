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

/**
 * Class which defines the admin form for Bolt OAuth settings used to call Magento store API
 *
 * @deprecated OAuth will no longer be used in future versions
 */
class Bolt_Boltpay_Block_Oauth_Edit_Form extends Mage_Adminhtml_Block_Widget_Form {

    public function __construct()
    {
        parent::__construct();
        $this->setId('oauth_edit_form');
    }

    /**
     * Sets up form fields
     *
     * @return Mage_Adminhtml_Block_Widget_Form   The form for the admin Bolt OAuth settings
     */
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
            'label'     => Mage::helper('boltpay')->__('Consumer Secret'),
            'title'     => Mage::helper('boltpay')->__('Consumer Secret'),
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