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

/**
 * This sets the hidden Bolt payment form
 * that is used in callback to save the order
 *
 * @deprecated Getting order data is done through the Bolt Fetch API call
 */
class Bolt_Boltpay_Block_Form extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        if (!Mage::app()->getStore()->isAdmin()) {
            $mark = Mage::getConfig()->getBlockClassName('core/template');
            $mark = new $mark;
            $mark->setTemplate('boltpay/mark.phtml');
            $this->setMethodLabelAfterHtml($mark->toHtml());
        }
        parent::_construct();
        $this->setTemplate('boltpay/form.phtml')->setMethodTitle(Mage::getStoreConfig('payment/boltpay/title'));
    }
}
