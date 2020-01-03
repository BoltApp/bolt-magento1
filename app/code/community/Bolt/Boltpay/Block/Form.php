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
 * Effects how Bolt is displayed when listed as a choice amongst other payment options
 * (e.g. one page checkout, admin checkout)
 */
class Bolt_Boltpay_Block_Form extends Mage_Payment_Block_Form_Cc
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Customizes the template used to display the Bolt payment option
     *
     * @throws Mage_Core_Model_Store_Exception  If the store can not be found by lookup
     */
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
