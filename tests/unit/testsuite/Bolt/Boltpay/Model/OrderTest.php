<?php

require_once('TestHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Order
 */
class Bolt_Boltpay_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * @var string The class name of the subject of these test
     */
    protected $testClassName = 'Bolt_Boltpay_Model_Order';

    /**
     * @var Bolt_Boltpay_Model_Order|PHPUnit_Framework_MockObject_MockObject  The mocked instance the test class
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
     */
    public function testActivateOrder()
    {
        $this->markTestIncomplete('broken test');

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

        $order->expects($this->exactly(2))
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
     * That when the discount price is the same at Bolt and Magento, no exception is thrown
     *
     * @covers ::validateTotals
     */
    public function validateTotals_discountAccurate() {

        $mocks = $this->validateTotalsSetup(25, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->never())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->testClassMock,
            'validateTotals',
            [$mockImmutableQuote, $mockTransaction]
        );
    }

    /**
     * @test
     * That when the discount price is different at Bolt than Magento but within the price fault tolerance,
     * no exception is thrown
     *
     * @covers ::validateTotals
     */
    public function validateTotals_discountDiffersWithinTolerance() {

        $mocks = $this->validateTotalsSetup(24, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->once())->method('logWarning');

        TestHelper::callNonPublicFunction(
            $this->testClassMock,
            'validateTotals',
            [$mockImmutableQuote, $mockTransaction]
        );
    }

    /**
     * @test
     * That when the discount price is different at Bolt than Magento beyond the price fault tolerance,
     * a general discount mismatch exception is thrown
     *
     * @covers ::validateTotals
     */
    public function validateTotals_discountDiffersBeyondTolerance() {

        $mocks = $this->validateTotalsSetup(20, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockBoltHelper->expects($this->never())->method('logWarning');

        try {
            TestHelper::callNonPublicFunction(
                $this->testClassMock,
                'validateTotals',
                [$mockImmutableQuote, $mockTransaction]
            );
        } catch (Bolt_Boltpay_OrderCreationException $oce ) {
            $this->assertContains(
                'Discount total has changed',
                $oce->getMessage()
            );
            return;
        }
        $this->fail("Expected a Bolt_Boltpay_OrderCreationException to be thrown but it was not.");
    }

    /**
     * @test
     * That when the discount price is different at Bolt than Magento beyond the price fault tolerance with a coupon
     * being used, a mismatch exception is thrown that includes the coupon code
     *
     * @covers ::validateTotals
     */
    public function validateTotals_couponDiscountDiffersBeyondTolerance() {

        $mocks = $this->validateTotalsSetup(20, .75, .50, 1);

        $mockBoltHelper = $mocks['mockBoltHelper'];
        $mockImmutableQuote = $mocks['mockImmutableQuote'];
        $mockTransaction = $mocks['mockTransaction'];

        $mockImmutableQuote->setCouponCode('BOLT10POFF');
        $mockBoltHelper->expects($this->never())->method('logWarning');

        try {
            TestHelper::callNonPublicFunction(
                $this->testClassMock,
                'validateTotals',
                [$mockImmutableQuote, $mockTransaction]
            );
        } catch (Bolt_Boltpay_OrderCreationException $oce ) {
            $this->assertContains(
                'Discount amount has changed',
                $oce->getMessage()
            );
            $this->assertContains(
                'BOLT10POFF',
                $oce->getMessage()
            );
            return;
        }
        $this->fail("Expected a Bolt_Boltpay_OrderCreationException to be thrown but it was not.");
    }


    /**
     * Prepares fixtures for validateTotals tests
     *
     * @param int $discountAmount                   the Bolt total discount amount in cents
     * @param float $magentoSubtotal                the Magento cart total amount in dollars without the discount included
     * @param float $magentoSubtotalWithDiscount    the Magento cart total amount in dollars with the discount included
     * @param int $priceFaultTolerance              the allowed difference between Bolt and Magento totals in cents
     *
     * @return PHPUnit_Framework_MockObject_MockObject[]|stdClass[] An array of mocks used in the consuming test. Namely:
     *                                                              "mockBoltHelper","mockImmutableQuote","mockTransaction"
     */
    private function validateTotalsSetup( $discountAmount, $magentoSubtotal, $magentoSubtotalWithDiscount, $priceFaultTolerance = 1 ) {

        $this->testClassMock = $this->getMockBuilder($this->testClassName)
            ->setMethods(['boltHelper'])->getMock();
        /**
         * @var Bolt_Boltpay_Helper_Data|PHPUnit_Framework_MockObject_MockObject
         */
        $mockBoltHelper = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(['getExtraConfig', 'logWarning', 'notifyException'])->getMock();

        $this->testClassMock->method('boltHelper')->willReturn($mockBoltHelper);

        $transactionJson =
            "{
              \"order\": {
                \"cart\": {
                  \"items\": [],
                  \"discount_amount\": {
                    \"amount\": $discountAmount
                  }
                }
              }
            }";


        $mockTransaction = json_decode($transactionJson);

        $mockBoltHelper->method('getExtraConfig')
            ->willReturnCallback(
                function ($configName) use ($mockTransaction, $priceFaultTolerance) {
                    $mockTransaction->shouldDoTaxTotalValidation = false;
                    $mockTransaction->shouldDoShippingTotalValidation = false;
                    return $priceFaultTolerance;
                }
            );
        $mockBoltHelper->expects($this->once())->method('getExtraConfig')
            ->with($this->equalTo('priceFaultTolerance'));

        /**
         * @var Mage_Sales_Model_Quote|PHPUnit_Framework_MockObject_MockObject
         */
        $mockImmutableQuote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(['getTotals', 'getBaseSubtotal', 'getBaseSubtotalWithDiscount'])->getMock();

        $magentoTotalsMock = [];
        $mockImmutableQuote->method('getTotals')->willReturn($magentoTotalsMock);
        $mockImmutableQuote->expects($this->once())->method('getTotals');

        $mockImmutableQuote->method('getBaseSubtotal')->willReturn($magentoSubtotal);
        $mockImmutableQuote->expects($this->once())->method('getBaseSubtotal');

        $mockImmutableQuote->method('getBaseSubtotalWithDiscount')->willReturn($magentoSubtotalWithDiscount);
        $mockImmutableQuote->expects($this->once())->method('getBaseSubtotalWithDiscount');

        return [
            "mockBoltHelper" => $mockBoltHelper,
            "mockImmutableQuote" => $mockImmutableQuote,
            "mockTransaction" => $mockTransaction
        ];
    }
}