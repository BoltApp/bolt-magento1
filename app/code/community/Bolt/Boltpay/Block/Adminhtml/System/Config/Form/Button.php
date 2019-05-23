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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Block_Adminhtml_System_Config_Form_Button
 *
 * Generates button used to fire check() javascript function which validates if Bolt configuration is correct.
 *
 */
class Bolt_Boltpay_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    use Bolt_Boltpay_BoltGlobalTrait;

    protected $_template = 'boltpay/system/config/button.phtml';

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'id' => 'boltpay_check_button',
                    'label' => $this->boltHelper()->__('Check'),
                    'onclick' => 'javascript:check(); return false;'
                )
            );

        return $button->toHtml();
    }

    /**
     * Gets storeId from current scope
     * @return int
     */
    public function getStoreId()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) {
            return Mage::getModel('core/store')->load($code)->getId();
        }

        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) {
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            return Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
        }

        // Returns default admin level
        return 0;
    }
}