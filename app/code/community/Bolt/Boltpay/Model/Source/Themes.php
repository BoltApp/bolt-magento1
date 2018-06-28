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
 * Class Bolt_Boltpay_Model_Source_Themes
 *
 * This class defines options for themes
 */
class Bolt_Boltpay_Model_Source_Themes
{
  public function toOptionArray()
  {
    return array(
      array(
        'value' => 'light',
        'label' => Mage::helper('boltpay')->__('Light, for dark backgrounds')
      ),
      array(
        'value' => 'dark',
        'label' => Mage::helper('boltpay')->__('Dark, for light backgrounds')
      ),
    );
  }
}
