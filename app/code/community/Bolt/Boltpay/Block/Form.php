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
 * This sets the hidden Bolt payment form
 * that is used in callback to save the order
 *
 * @deprecated Getting order data is done through the Bolt Fetch API call
 */
class Bolt_Boltpay_Block_Form extends Mage_Payment_Block_Form_Cc
{
    use Bolt_Boltpay_BoltGlobalTrait;

    protected function _construct()
    {
        parent::_construct();

        if (!Mage::app()->getStore()->isAdmin()) {
            /** @var Bolt_Boltpay_Block_Checkout_Boltpay $markBlock */
            $markBlock = Mage::app()->getLayout()->createBlock('boltpay/checkout_boltpay');
            $markBlock->setTemplate('boltpay/mark.phtml');
            $this->setMethodLabelAfterHtml($markBlock->toHtml());
        }

        $this->setTemplate('boltpay/form.phtml')->setMethodTitle(Mage::getStoreConfig('payment/boltpay/title'));
    }
}
