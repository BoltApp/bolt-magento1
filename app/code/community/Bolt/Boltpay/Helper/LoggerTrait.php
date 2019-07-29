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

    use Bolt_Boltpay_Helper_BugsnagTrait;

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
    public function log($level, $message, array $context = array()) {
        //example for now
        if(Bolt_Boltpay_Helper_LoggerTrait::$isLoggerEnabled){
            $this->addBreadcrumb(
                array(
                    'level'  => $level,
                    'context'  => var_export($context, true),
                )
            );
            $this->notifyException( new Exception((string)$message) );
        }
    }

    /**
     * Logs the time taken to reach the point in the codebase
     *
     * @param string $label                  Label to add to the benchmark
     * @param bool   $shouldLogIndividually  If true, the benchmark will be logged separately in addition to with the full log
     * @param bool   $shouldIncludeInFullLog If false, this benchmark will not be included in the full log
     * @param bool   $shouldFlushFullLog     If true, will log the full log up to this benchmark call.
     */
    public function logBenchmark( $label, $shouldLogIndividually = false, $shouldIncludeInFullLog = true, $shouldFlushFullLog = false ) {
        if (!$this->getExtraConfig('enableBenchmarkProfiling')) { return; }

        $index = Mage::registry('bolt/processing_time_index') ?: 0;
        $startTime = Mage::registry('bolt/request_start_time') ?: microtime(true);
        $lastTime = Mage::registry('bolt/last_process_timestamp') ?: $startTime;
        $currentTime = microtime(true);

        $summary = "-- Processing Time $index --\n*$label*\nTime since last timestamp marker: *".round(($currentTime - $lastTime), 3)."s*\n";
        $summary .= 'Time since benchmark profiling start: '.round(($currentTime - $startTime), 3).'s';

        if ($shouldLogIndividually) {$this->info($summary);}

        try {
            Mage::register('bolt/request_start_time', $startTime, true);
            Mage::unregister('bolt/last_process_timestamp');
            Mage::register('bolt/last_process_timestamp', $currentTime);
            Mage::unregister('bolt/processing_time_index');
            Mage::register('bolt/processing_time_index', ++$index);
        } catch ( Mage_Core_Exception $mce ) {
            // convenience clobber to suppress IDE warnings.  Logic does not permit the exception
        }

        $fullSummary = Mage::getSingleton('core/session')->getFullSummary() ?: '';

        if ($shouldIncludeInFullLog) {
            $fullSummary .= ($fullSummary) ? "\n\n".$summary : $summary;
            Mage::getSingleton('core/session')->setFullSummary($fullSummary);
        }

        if ( $shouldFlushFullLog ) {
            if ($fullSummary) {$this->info($fullSummary);}
            Mage::unregister('bolt/request_start_time');
            Mage::unregister('bolt/last_process_timestamp');
            Mage::unregister('bolt/processing_time_index');
        }
    }
}
