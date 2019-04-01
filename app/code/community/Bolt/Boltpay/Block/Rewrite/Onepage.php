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
 * Class Bolt_Boltpay_Block_Rewrite_Onepage
 *
 * Defines onpage checkout steps depending on the boltpay skip payment configuration.
 * If the skip payment is set, which means Bolt is the only payment method, then skip the payment select step.
 */
class Bolt_Boltpay_Block_Rewrite_Onepage extends Mage_Checkout_Block_Onepage
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Get checkout steps codes
     *
     * @return array
     */
    protected function _getStepCodes() 
    {
        $steps = array('login', 'billing', 'shipping', 'shipping_method');
        if (!Mage::getStoreConfig('payment/boltpay/active') || !Mage::getStoreConfig('payment/boltpay/skip_payment')) {
            array_push($steps, 'payment');
        }

        array_push($steps, 'review');
        return $steps;
    }
}
