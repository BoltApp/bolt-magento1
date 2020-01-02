<?php

/**
 * @coversDefaultClass Bolt_Boltpay_InvalidTransitionException
 */
class Bolt_Boltpay_InvalidTransitionExceptionTest extends PHPUnit_Framework_TestCase
{
    /** @var string Dummy old status */
    const OLD_STATUS = 'completed';

    /** @var string Dummy new status */
    const NEW_STATUS = 'pending';

    /** @var string Dummy exception message */
    const MESSAGE = 'Cannot transition a transaction from completed to pending';

    /**
     * @test
     * that the constructor sets the previous and new hook status, and that the class extends
     * {@see Bolt_Boltpay_BoltException}
     *
     * @covers ::__construct
     *
     * @return Bolt_Boltpay_InvalidTransitionException instance of the exception used for test chaining
     */
    public function __construct_always_setsOldAndNewStatusAndThenCallsParentConstructor()
    {
        $currentException = new Bolt_Boltpay_InvalidTransitionException(
            self::OLD_STATUS,
            self::NEW_STATUS,
            self::MESSAGE
        );
        $this->assertAttributeEquals(self::OLD_STATUS, '_oldStatus', $currentException);
        $this->assertAttributeEquals(self::NEW_STATUS, '_newStatus', $currentException);
        $this->assertInstanceOf('Bolt_Boltpay_BoltException', $currentException);
        $this->assertInstanceOf('Exception', $currentException);
        return $currentException;
    }

    /**
     * @test
     * that getOldStatus returns value that was provided in constructor as oldStatus parameter
     *
     * @covers ::getOldStatus
     * @depends __construct_always_setsOldAndNewStatusAndThenCallsParentConstructor
     *
     * @param Bolt_Boltpay_InvalidTransitionException $currentException instance of the exception from constructor test
     */
    public function getOldStatus_always_returnsOldStatus($currentException)
    {
        $this->assertEquals(
            self::OLD_STATUS,
            $currentException->getOldStatus()
        );
    }

    /**
     * @test
     * that getNewStatus returns value that was provided in constructor as newStatus parameter
     *
     * @covers ::getNewStatus
     * @depends __construct_always_setsOldAndNewStatusAndThenCallsParentConstructor
     *
     * @param Bolt_Boltpay_InvalidTransitionException $currentException instance of the exception from constructor test
     */
    public function getNewStatus_always_returnsNewStatus($currentException)
    {
        $this->assertEquals(
            self::NEW_STATUS,
            $currentException->getNewStatus()
        );
    }

    /**
     * @test
     * that getMessage returns value that was provided in constructor as message parameter
     *
     * @covers ::getMessage
     * @depends __construct_always_setsOldAndNewStatusAndThenCallsParentConstructor
     *
     * @param Bolt_Boltpay_InvalidTransitionException $currentException instance of the exception from constructor test
     */
    public function getMessage_always_returnsMessage($currentException)
    {
        $this->assertEquals(
            self::MESSAGE,
            $currentException->getMessage()
        );
    }
}
