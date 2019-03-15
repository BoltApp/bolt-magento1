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
 * Trait Bolt_Boltpay_BoltGlobalTrait
 *
 * Defines interface global to all registered Bolt Objects
 */
trait Bolt_Boltpay_BoltGlobalTrait {

    /**
     * Returns the main helper class used by Bolt or if from a class that natively supports this function
     * with a parameter, that helper
     *
     * @return Bolt_Boltpay_Helper_Data|Mage_Core_Helper_Abstract
     */
    public function boltHelper() {
        return Mage::helper('boltpay');
    }

}