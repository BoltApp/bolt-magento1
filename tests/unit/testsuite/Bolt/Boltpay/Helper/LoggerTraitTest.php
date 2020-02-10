<?php
require_once('TestHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_LoggerTrait
 */
class Bolt_Boltpay_Helper_LoggerTraitTest extends PHPUnit_Framework_TestCase
{

    /**
     * Reset trait and registry values
     */
    protected function tearDown()
    {
        Bolt_Boltpay_Helper_LoggerTrait::$isLoggerEnabled = true;
        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');
    }

    /**
     * Creates mock of Logger trait with specific methods mocked
     *
     * @param array $methods to be mocked
     * @return PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_LoggerTrait mocked instance of current trait
     */
    private function getCurrentMock($methods = array())
    {
        return $this->getMockBuilder('Bolt_Boltpay_Helper_LoggerTrait')
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMockForTrait();
    }

    /**
     * @test
     * that error method calls log method with 'error' severity level
     *
     * @dataProvider logMessageAndContextProvider
     * @covers       Bolt_Boltpay_Helper_LoggerTrait::error
     *
     * @param string $message expected to be passed to log method
     * @param array  $context expected to be passed to log method
     */
    public function error_withVariousMessagesAndContext_willCallLogWithErrorSeverityLevel($message, $context)
    {
        $currentMock = $this->getCurrentMock(array('log'));
        $currentMock->expects($this->once())->method('log')
            ->with(Bolt_Boltpay_Helper_LogLevel::ERROR, $message, $context);

        $currentMock->error($message, $context);
    }

    /**
     * @test
     * that warning method calls log method with 'warning' severity level
     *
     * @dataProvider logMessageAndContextProvider
     * @covers       Bolt_Boltpay_Helper_LoggerTrait::warning
     *
     * @param string $message expected to be passed to log method
     * @param array  $context expected to be passed to log method
     */
    public function warning_withVariousMessagesAndContext_willCallLogWithWarningSeverityLevel($message, $context)
    {
        $currentMock = $this->getCurrentMock(array('log'));
        $currentMock->expects($this->once())->method('log')
            ->with(Bolt_Boltpay_Helper_LogLevel::WARNING, $message, $context);

        $currentMock->warning($message, $context);
    }

    /**
     * @test
     * that info method calls log method with 'info' severity level
     *
     * @dataProvider logMessageAndContextProvider
     * @covers       Bolt_Boltpay_Helper_LoggerTrait::info
     *
     * @param string $message expected to be passed to log method
     * @param array  $context expected to be passed to log method
     */
    public function info_withVariousMessagesAndContext_willCallLogWithInfoSeverityLevel($message, $context)
    {
        $currentMock = $this->getCurrentMock(array('log'));
        $currentMock->expects($this->once())->method('log')
            ->with(Bolt_Boltpay_Helper_LogLevel::INFO, $message, $context);

        $currentMock->info($message, $context);
    }

    /**
     * @test
     * that log method  with provided message and context
     *
     * @dataProvider logMessageAndContextProvider
     * @covers       Bolt_Boltpay_Helper_LoggerTrait::log
     *
     * @param string $message to be logger
     * @param array  $context of the message
     */
    public function log_loggerEnabled_willCallNotifyException($message, $context)
    {
        $currentMock = $this->getCurrentMock(array('addBreadcrumb', 'notifyException'));
        $level = $this->getRandomLogLevel();

        $currentMock->expects($this->once())->method('addBreadcrumb')
            ->with(
                array(
                    'level'   => $level,
                    'context' => var_export($context, true),
                )
            );

        $currentMock->expects($this->once())->method('notifyException')->with(new Exception((string)$message));

        $currentMock->log($level, $message, $context);
    }

    /**
     * @test
     * that log method doesn't log when disabled via static property
     *
     * @dataProvider logMessageAndContextProvider
     * @covers       Bolt_Boltpay_Helper_LoggerTrait::log
     *
     * @param string $message to be logger
     * @param array  $context of the message
     */
    public function log_loggerDisabled_doesNothing($message, $context)
    {
        $currentMock = $this->getCurrentMock(array('addBreadcrumb', 'notifyException'));
        Bolt_Boltpay_Helper_LoggerTrait::$isLoggerEnabled = false;
        $level = $this->getRandomLogLevel();

        $currentMock->expects($this->never())->method('addBreadcrumb');
        $currentMock->expects($this->never())->method('notifyException');

        $currentMock->log($level, $message, $context);
    }

    /**
     * @test
     * that logBenchmark doesn't do anything when not enabled in Bolt config
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     */
    public function logBenchmark_notEnabledInExtraConfig_doesNothing()
    {
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'log'));
        $currentMock->expects($this->once())->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(false);
        $currentMock->expects($this->never())->method('log');
        $currentMock->logBenchmark('Label', true, true, true);
    }

    /**
     * @test
     * that log benchmark method with all arguments false will only register request_start_time and last_process_timestamp
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     */
    public function logBenchmark_allParametersFalse_willOnlyUpdateRegistryEntries()
    {
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'log'));
        $currentMock->expects($this->once())->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(true);
        $currentMock->expects($this->never())->method('log');

        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');

        $currentMock->logBenchmark('Label', false, false, false);

        $startTime = Mage::registry('bolt/request_start_time');
        $lastProcessTimestamp = Mage::registry('bolt/last_process_timestamp');

        $this->assertNotNull($startTime);
        $this->assertNotNull($lastProcessTimestamp);
    }

    /**
     * @test
     * that log benchmark method will log provided benchmark immediately when should log individually argument set to true
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     */
    public function logBenchmark_onlyLogIndividuallyParameterTrue_willCallInfoMethod()
    {
        $label = 'Test Label';
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'info'));
        $currentMock->expects($this->once())->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(true);

        $currentMock->expects($this->once())->method('info')->willReturnCallback(
            function ($message) use ($label) {
                $this->assertNotFalse(
                    preg_match_all(
                        "/Benchmark.*?\\*(?'label'.+?)\\*/s",
                        $message,
                        $benchmarks
                    )
                );
                $this->assertEquals(
                    $label,
                    $benchmarks['label'][0]
                );
            }
        );

        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');

        $currentMock->logBenchmark($label, true, false, false);

        $startTime = Mage::registry('bolt/request_start_time');
        $lastProcessTimestamp = Mage::registry('bolt/last_process_timestamp');

        $this->assertNotNull($startTime);
        $this->assertNotNull($lastProcessTimestamp);
    }

    /**
     * @test
     * that log benchmark with should include in full log argument set to true will only add benchmark to $benchmarks property
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     */
    public function logBenchmark_onlyShouldIncludeInFullLogParameterTrue_willAppendBenchmarkToInternalProperty()
    {
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'info'));
        $currentMock->expects($this->never())->method('info');
        $currentMock->expects($this->once())->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(true);

        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');

        $currentMock->logBenchmark('Label', false, true, false);

        $benchmarks = $currentMock::$benchmarks;
        $this->assertCount(1, $benchmarks);
        $this->assertEquals('Label', $benchmarks[0]['label']);
    }

    /**
     * @test
     * that flushing full benchmark log will immediately log all benchmarks entries and truncate the property
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     */
    public function logBenchmark_shouldFlushFullLogTrue_willDelegateAllBenchmarksToInfoMethod()
    {
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'info'));
        $currentMock->expects($this->exactly(3))->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(true);

        $labels = array('Label1', 'Label2', 'Label3');

        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');

        $currentMock->expects($this->once())->method('info')->willReturnCallback(
            function ($message) use ($labels) {
                $this->assertNotFalse(
                    preg_match_all(
                        "/-- Benchmark  (?'number'\d+) --\n\\*(?'label'.+)\\*/",
                        $message,
                        $benchmarks
                    )
                );
                $this->assertEquals($labels, $benchmarks['label']);
            }
        );

        $currentMock->logBenchmark($labels[0], false, true, false);
        $currentMock->logBenchmark($labels[1], false, true, false);
        $currentMock->logBenchmark($labels[2], false, true, true);

        $this->assertEmpty($currentMock::$benchmarks);
    }

    /**
     * @test
     * Flushing full benchmark log where two benchmarks have the same time percentage to cover an edge case in sorting
     *
     * @covers Bolt_Boltpay_Helper_LoggerTrait::logBenchmark
     *
     * @throws Mage_Core_Exception if registry key already exists
     */
    public function logBenchmark_flushFullLogWithTwoBenchmarksThatTookTheSameTimeToExecute_willExecuteEdgeCaseInSortingFunction()
    {
        $currentMock = $this->getCurrentMock(array('getExtraConfig', 'info'));
        $currentMock->expects($this->once())->method('getExtraConfig')->with('enableBenchmarkProfiling')
            ->willReturn(true);

        Mage::unregister('bolt/request_start_time');
        Mage::unregister('bolt/last_process_timestamp');

        Mage::register('bolt/request_start_time', 1000);

        $currentMock::$benchmarks = array(
            array('label' => 'Label 1', 'time' => 1500),
            array('label' => 'Label 2', 'time' => 2000),
        );

        $currentMock->logBenchmark('Test Label', false, false, true);

        $this->assertEmpty($currentMock::$benchmarks);
    }

    /**
     * Data provider for log messages and context
     *
     * @return array of log message and context pairs
     */
    public function logMessageAndContextProvider()
    {
        return array(
            array('Error message', array('test' => 'test')),
            array('Test', array('error' => true)),
            array('Test', array()),
        );
    }

    /**
     * Returns random log level from list
     *
     * @return string log level
     */
    private function getRandomLogLevel()
    {
        $logLevels = array(
            Bolt_Boltpay_Helper_LogLevel::ERROR,
            Bolt_Boltpay_Helper_LogLevel::WARNING,
            Bolt_Boltpay_Helper_LogLevel::INFO,
        );
        return $logLevels[mt_rand(0, count($logLevels) - 1)];
    }
}