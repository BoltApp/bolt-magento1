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
 * Class Bolt_Boltpay_Helper_Logger
 *
 * The Magento Helper class that provides logger utility methods
 *
 */
class Bolt_Boltpay_Helper_Logger extends Mage_Core_Helper_Abstract
{
    use Bolt_Boltpay_Helper_LoggerTrait;
    
    public function log($level, $message, array $context = array()) {
        //example for now
        if(Bolt_Boltpay_Helper_LoggerTrait::$isLoggerEnabled){
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'level'  => $level,
                    'context'  => var_export($context, true),
                )
            );
            Mage::helper('boltpay/bugsnag')->notifyException( new Exception((string)$message) );
        }        
    }    
}