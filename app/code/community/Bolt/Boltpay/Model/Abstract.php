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
 * Class Bolt_Boltpay_Model_Abstract
 *
 * The Bolt Model superclass
 *
 */
class Bolt_Boltpay_Model_Abstract extends Mage_Core_Model_Abstract
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function isAdmin()
    {
        return (bool)Mage::app()->getStore()->isAdmin();
    }

    /**
     * Create Block by type in the current layout.
     *
     * @param string $blockType
     * @return Mage_Core_Block_Abstract
     */
    protected function getLayoutBlock($blockType)
    {
        return Mage::app()->getLayout()->createBlock($blockType);
    }
}
