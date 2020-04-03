<?php
class Bolt_Boltpay_InvalidTransitionException extends Bolt_Boltpay_BoltException
{
    private $_oldStatus;
    private $_newStatus;
    public function __construct($oldStatus, $newStatus, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->_oldStatus = $oldStatus;
        $this->_newStatus = $newStatus;
        parent::__construct($message, $code, $previous);
    }
    public function getOldStatus(){return $this->_oldStatus;}
    public function getNewStatus(){return $this->_newStatus;}
}