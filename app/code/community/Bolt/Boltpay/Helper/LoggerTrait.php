<?php

/**
 * Describes log levels.
 */
class Bolt_Boltpay_Helper_LogLevel
{
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const INFO      = 'info';
}

/**
 * This is a simple Logger trait that classes can include.
 *
 * It simply delegates all log-level-specific methods to the `log` method to
 * reduce boilerplate code that a simple Logger that does the same thing with
 * messages regardless of the error level has to implement.
 */
trait Bolt_Boltpay_Helper_LoggerTrait
{
    
    public static $isLoggerEnabled = true;
    
    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->log(Bolt_Boltpay_Helper_LogLevel::ERROR, $message, $context);
    }
    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->log(Bolt_Boltpay_Helper_LogLevel::WARNING, $message, $context);
    }
    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->log(Bolt_Boltpay_Helper_LogLevel::INFO, $message, $context);
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    abstract public function log($level, $message, array $context = array());
}
