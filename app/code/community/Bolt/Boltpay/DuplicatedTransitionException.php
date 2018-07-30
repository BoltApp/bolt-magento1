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
 * Class Bolt_Boltpay_DuplicatedTransitionException
 *
 * This exception is thrown when a transaction is trying to process order creation while a previous transaction already started before its arrival 
 */
class Bolt_Boltpay_DuplicatedTransitionException extends Exception
{
    private $_processedBoltReference;
    /**
     * Bolt_Boltpay_InvalidTransitionException constructor.
     *
     * @param string $message           [optional] The Exception message to throw.
     * @param int $code                 [optional] The Exception code.
     * @param Throwable|null $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct($processedBoltReference, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->_processedBoltReference = $processedBoltReference;
        parent::__construct($message, $code, $previous);
    }
    
    public function getProcessedBoltReference()
    {
        return $this->_processedBoltReference;
    }
}