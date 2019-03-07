<?php
class Bolt_Boltpay_BoltException extends Exception
{
    /** @var Bolt_Boltpay_Helper_Data */
    protected $helper;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $this->helper = Mage::helper('boltpay');
        parent::__construct($message, $code, $previous);
    }
}