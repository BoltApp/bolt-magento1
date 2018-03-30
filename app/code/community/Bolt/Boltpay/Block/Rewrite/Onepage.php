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
 * Class Bolt_Boltpay_Block_Rewrite_Onepage
 *
 * Defines onpage checkout steps depending on the boltpay skip payment configuration.
 * If the skip payment is set, which means Bolt is the only payment method, then skip the payment select step.
 */
class Bolt_Boltpay_Block_Rewrite_Onepage extends Mage_Checkout_Block_Onepage {
    /**
     * Get checkout steps codes
     *
     * @return array
     */
    protected function _getStepCodes() {
        $steps = array('login', 'billing', 'shipping', 'shipping_method');
        if (!Mage::getStoreConfig('payment/boltpay/active') || !Mage::getStoreConfig('payment/boltpay/skip_payment')) {
            array_push($steps, 'payment');
        }
        array_push($steps, 'review');
        return $steps;
    }
}
