<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * Class Bolt_Boltpay_Model_OrderTest
 */
class Bolt_Boltpay_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Order';

    /**
     * @var Bolt_Boltpay_Model_Order  The mocked instance the test class
     */
    private $testClassMock;

    /**
     * Test whether flags are correctly set after an email is sent and that no exceptions are thrown in the process
     */
    public function testSendOrderEmail()
    {
        /** @var Bolt_Boltpay_Model_Order $orderModel */
        $orderModel = Mage::getModel('boltpay/order');

        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(array('getPayment', 'addStatusHistoryComment', 'queueNewOrderEmail'))
            ->getMock();

        $orderPayment = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')
            ->setMethods(['save'])
            ->enableOriginalConstructor()
            ->getMock();

        $order->method('getPayment')
            ->willReturn($orderPayment);

        $order->setIncrementId(187);

        $history = Mage::getModel('sales/order_status_history');

        $order->expects($this->once())
            ->method('queueNewOrderEmail');

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->willReturn($history);

        $this->assertNull($history->getIsCustomerNotified());

        try {
            $orderModel->sendOrderEmail($order);
        } catch ( Exception $e ) {
            $this->fail('An exception was thrown while sending the email');
        }

        $this->assertTrue($history->getIsCustomerNotified());
    }

    /**
     * Test if setBoltUserId successfully associates Bolt customer ID with the Magento customer
     */
    public function testSetBoltUserId()
    {
        $this->testClassMock = $this->getTestClassPrototype()
            ->setMethods(null)
            ->getMock();

        $customer = Mage::getModel('customer/customer');
        $quote = Mage::getModel('Mage_Sales_Model_Quote');

        $quote->setCustomer($customer);

        $session  = Mage::getSingleton('customer/session');
        $session->setBoltUserId(911);

        $this->assertEquals(911, $session->getBoltUserId());
        $this->assertEmpty($quote->getCustomer()->getBoltUserId());

        TestHelper::callNonPublicFunction(
            $this->testClassMock,
            'setBoltUserId',
            [$quote]
        );

        $this->assertEmpty($session->getBoltUserId());
        $this->assertEquals(911, $quote->getCustomer()->getBoltUserId());
    }

    /**
     * Test if activateOrder successfully adds reference to the payment
     * @group ModelOrder
     */
    public function testActivateOrder()
    {
        //$this->markTestIncomplete('broken test');

        $quote = new Mage_Sales_Model_Quote();
        $quote->setIsActive(true);

        $this->testClassMock = $this->getTestClassPrototype()
            ->setMethods(['getParentQuoteFromOrder', 'sendOrderEmail'])
            ->getMock();

        $this->testClassMock->expects($this->exactly(2))
            ->method('getParentQuoteFromOrder')
            ->willReturn($quote);

        $payment = $this->getClassPrototype('Mage_Sales_Model_Order_Payment')
            ->setMethods(['save'])
            ->getMock();

        $order = $this->getClassPrototype('Mage_Sales_Model_Order')
            ->setMethods(['save','getPayment'])
            ->getMock();

        $order->expects($this->exactly(4))
            ->method('getPayment')
            ->willReturn($payment);

        ////////////////////////////////////////////////////////////
        /// First call
        ////////////////////////////////////////////////////////////
        $this->assertEmpty( $order->getCreatedAt() );
        $this->assertTrue( (bool)$quote->getIsActive() );
        $this->assertEmpty($payment->getAdditionalInformation('bolt_reference'));

        TestHelper::callNonPublicFunction(
            $this->testClassMock,
            'activateOrder',
            [$order, json_decode('{"transaction_reference" : "TEST-BOLT-REFE-RENC"}')]
        );

        $this->assertNotEmpty( $order->getCreatedAt() );
        $this->assertFalse( (bool)$quote->getIsActive() );
        $this->assertEquals('TEST-BOLT-REFE-RENC', $payment->getAdditionalInformation('bolt_reference'));
        ////////////////////////////////////////////////////////////

        ////////////////////////////////////////////////////////////
        /// Second call: check that createdAt does not change
        ////////////////////////////////////////////////////////////
        $currentCreatedAt = $order->getCreatedAt();
        TestHelper::callNonPublicFunction(
            $this->testClassMock,
            'activateOrder',
            [$order, json_decode('{"transaction_reference" : "TEST-BOLT-REFE-TWO2"}')]
        );

        $this->assertEquals( $currentCreatedAt, $order->getCreatedAt() );
        $this->assertEquals('TEST-BOLT-REFE-TWO2', $payment->getAdditionalInformation('bolt_reference'));
        ////////////////////////////////////////////////////////////
    }

    /**
     * @test
     * @group ModelOrder
     * @dataProvider isBoltOrderCases
     * @param array $case
     */
    public function isBoltOrder(array $case)
    {
        $mock = $this->getMockBuilder(Bolt_Boltpay_Model_Order::class)->setMethods(null)->getMock();
        $order = $this->getMockBuilder(Mage_Sales_Model_Order::class)->setMethods(array('getPayment'))->getMock();
        $payment = $this->getMockBuilder(Mage_Sales_Model_Order_Payment::class)->setMethods(array('getMethod'))->getMock();
        $payment->expects($this->once())->method('getMethod')->will($this->returnValue($case['method']));
        $order->expects($this->once())->method('getPayment')->will($this->returnValue($payment));
        // Start test
        $result = $mock->isBoltOrder($order);
        $this->assertInternalType('boolean', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test Cases
     * @return array
     */
    public function isBoltOrderCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => false,
                    'method' => ''
                    
                )
            ),
            array(
                'case' => array(
                    'expect' => false,
                    'method' => 'paypal'
                    
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'method' => 'boltpay'
                    
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'method' => 'Boltpay'
                    
                )
            ),
            array(
                'case' => array(
                    'expect' => true,
                    'method' => 'BOLTPAY'
                    
                )
            ),
        );
    }
}
