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
 * Trait Bolt_Boltpay_Model_ValidatorTrait
 *
 * Extends the native Magento Validator functionality in a way that allows for clean, multiple inheritance
 *
 */
trait Bolt_Boltpay_Model_Traits_ValidatorTrait {
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Method for resetting rounding delta and base rounding delta. Rounding deltas cause percentage discounts applied to an order to often get
     * off by $0.01 rounding errors because the validator used is a singleton. So every time collectTotals is called
     * it reuses the previous rounding deltas and causes rounding problems. Since Mage_SalesRule_Model_Validator doesn't
     * provide a method for resetting these before calling collectTotals, we created one.
     */
    public function resetRoundingDeltas()
    {
        $this->_roundingDeltas = array();
        $this->_baseRoundingDeltas = array();
    }
}