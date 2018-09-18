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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Model_Admin_Form_Color extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $hexColor = $this->getValue();
        if(!preg_match('/#([a-fA-F0-9]{3}){1,2}\b/', $hexColor)){
            $this->setValue($this->getOldValue());
            Mage::getSingleton('core/session')->addError(Mage::helper('boltpay')->__('Invalid hex color value. Should be in the form #f00000 or #f00'));
        }

        return parent::save();
    }
}
