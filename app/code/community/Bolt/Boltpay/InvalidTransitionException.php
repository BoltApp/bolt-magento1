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
 * Class Bolt_Boltpay_InvalidTransitionException
 *
 * This exception is thrown when an attempt is made to change a transactions state through
 * and unsupported workflow.
 */
class Bolt_Boltpay_InvalidTransitionException extends Exception
{
    private $_oldStatus;
    private $_newStatus;

    /**
     * Bolt_Boltpay_InvalidTransitionException constructor.
     *
     * @param string $oldStatus         The original status of the transaction
     * @param int $newStatus            The failed destination status
     * @param string $message           [optional] The Exception message to throw.
     * @param int $code                 [optional] The Exception code.
     * @param Throwable|null $previous  [optional] The previous throwable used for the exception chaining.
     */
    public function __construct($oldStatus, $newStatus, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->_oldStatus = $oldStatus;
        $this->_newStatus = $newStatus;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets the original status of the transaction
     *
     * @return string
     */
    public function getOldStatus()
    {
        return $this->_oldStatus;
    }

    /**
     * Gets the failed destination status
     *
     * @return string
     */
    public function getNewStatus()
    {
        return $this->_newStatus;
    }
}