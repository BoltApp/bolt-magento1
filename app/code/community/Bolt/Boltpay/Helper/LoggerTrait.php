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

    /** @var array A two dimensional array of all benchmark labels and times  */
    public static $benchmarks = [];
    
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

        $startTime = Mage::registry('bolt/request_start_time') ?: microtime(true);
        $previousTime = Mage::registry('bolt/last_process_timestamp') ?: $startTime;
        $currentTime = microtime(true);

        $sectionDivider = '*****************************************************';
        $summaryHeader = '-- Benchmark ';
        $summarySinceLastTime = 'Time since previous benchmark: *';
        $summarySinceStartTime = 'Time since benchmark profiling was started: ';

        if ($shouldLogIndividually) {
            $summary = "\n".$summaryHeader." --\n";
            if ($label) {$summary .= "*$label*\n";}

            $summary .=
                $summarySinceLastTime.round(($currentTime - $previousTime), 3)."s*\n"
                .$summarySinceStartTime.round(($currentTime - $startTime), 3).'s';

            $this->info($summary);
        }

        try {
            Mage::register('bolt/request_start_time', $startTime, true);
            Mage::unregister('bolt/last_process_timestamp');
            Mage::register('bolt/last_process_timestamp', $currentTime);
        } catch ( Mage_Core_Exception $mce ) {
            // convenience clobber to suppress IDE warnings.  Logic does not permit the exception
        }

        if ($shouldIncludeInFullLog) {
            static::$benchmarks[] = [ 'label' => $label, 'time' => $currentTime ];
        }

        if ( $shouldFlushFullLog ) {

            if (static::$benchmarks) {
                $fullSummary = "";
                $finalBenchmarkTime = static::$benchmarks[count(static::$benchmarks) - 1]['time'];
                $totalTime = $finalBenchmarkTime - $startTime;

                $i = 0;
                $previousTime = $startTime;
                $percentSummaryArray = [];
                foreach (static::$benchmarks as $benchmark) {
                    $i++;
                    $summary = $summaryHeader . " $i --\n";
                    $label = $benchmark['label'];
                    if ($label) {
                        $summary .= "*$label*\n";
                    }

                    $benchmarkTime = $benchmark['time'];
                    $processingTime = $benchmarkTime - $previousTime;
                    $processingPercentage = number_format(round(($processingTime / $totalTime) * 100, 2), 2);

                    $summary .=
                        $summarySinceLastTime . round(($processingTime), 3) . "s*\n"
                        . $summarySinceStartTime . round(($benchmarkTime - $startTime), 3) . "s\n"
                        . "Percent of total benchmarked processing time: *$processingPercentage%*";

                    $previousTime = $benchmarkTime;

                    if ($fullSummary) {
                        $fullSummary .= "\n\n";
                    }
                    $fullSummary .= $summary;

                    $percentSummaryArray[] = [
                        'index' => $i,
                        'label' => $label ?: '<No Label>',
                        'percent' => $processingPercentage,
                        'time' => number_format(round(($processingTime), 3), 3)
                    ];
                }
                usort(
                    $percentSummaryArray,
                    function ($a, $b) {
                        if ($a['percent'] == $b['percent']) {
                            return 0;
                        }
                        return ($a['percent'] < $b['percent']) ? 1 : -1;
                    }
                );

                $percentSummary = $percentSummaryArray
                    ? "\n\n$sectionDivider\n\n-- Aggregate Benchmark Processing Time Summary --\n\n"
                    : '';

                $i = 0;
                foreach ($percentSummaryArray as $benchmark) {
                    if ($i++) {
                        $percentSummary .= "\n";
                    }
                    $percentSummary .= str_pad($benchmark['percent'], 5, ' ', STR_PAD_LEFT)
                        . '% - Benchmark '. str_pad( $benchmark['index'], 3)
                        . ' - ' . $benchmark['label'] . ' - *' . $benchmark['time'] . 's*';
                }

                $totalTime = "\n\nTotal Processing Time: *" . round($totalTime, 3) . "s*\n\n$sectionDivider\n";
                $this->info("\n$sectionDivider\n\n".$fullSummary.$percentSummary.$totalTime);
            }

            # fully reset the benchmarks
            Mage::unregister('bolt/request_start_time');
            Mage::unregister('bolt/last_process_timestamp');
            static::$benchmarks = [];
        }
    }
}
