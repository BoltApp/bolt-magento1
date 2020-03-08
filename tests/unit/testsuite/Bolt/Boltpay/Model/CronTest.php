<?php

require_once('OrderHelper.php');
require_once('TestHelper.php');
require_once('MockingTrait.php');

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * Class Bolt_Boltpay_Model_CronTest
 *
 * @coversDefaultClass Bolt_Boltpay_Model_Cron
 */
class Bolt_Boltpay_Model_CronTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /**
     * A time in minutes padded to test entry timestamps to make them eligible for cleanup
     */
    const MINUTES_PADDING_FOR_TEST_ENTRY_TIMESTAMPS = 3;

    /**
     * @var Bolt_Boltpay_Model_Cron The subject object which is actually a true unstubbed instance of the class
     */
    private $_currentMock;

    /**
     * @var int|null Dummy product ID used in all orders
     */
    private static $productId = null;

    /**
     * Initialization before each test.  We currently create a fresh Bolt_Boltpay_Model_Cron model for each test
     */
    public function setUp()
    {
        $this->_currentMock = Mage::getModel('boltpay/cron');
    }

    /**
     * Generates a dummy product used for creating test orders once and only once before any test in this class are run
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(uniqid('PHPUNIT_TEST_'), array(), 20);
    }

    /**
     * Delete dummy products after all test of this class have run
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * @test
     *
     * @covers ::cleanupQuotes
     */
    public function cleanupQuotes_ifExceptionIsThrown_callsNotifyExceptionAndLogWarning()
    {
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logWarning'))
            ->getMock();
        $boltHelperMock->expects($this->once())->method('notifyException');
        $boltHelperMock->expects($this->once())->method('logWarning');
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $resourceMock = $this->getClassPrototype('core/resource')
            ->setMethods(array('getConnection'))
            ->getMock();
        $connectionMock = $this->getClassPrototype('Magento_Db_Adapter_Pdo_Mysql')
            ->setMethods(array('query'))
            ->getMock();
        $connectionMock->method('query')->willThrowException(new Exception('expected exception'));
        $resourceMock->method('getConnection')->with('core_write')->willReturn($connectionMock);
        TestHelper::stubSingleton('core/resource', $resourceMock);
        $this->_currentMock->cleanupQuotes();
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * to see if the cron will delete pending_payment Bolt orders that are older than 15 minutes while leaving all
     * other orders
     *
     * @covers ::cleanupOrders
     *
     * @throws Mage_Core_Exception on failure to create or delete a dummy order
     */
    public function cleanupOrders_deletesOrdersOlderThan15Minutes()
    {
        $cron = $this->_currentMock;

        $pendingPaymentOrders = $paidOrders = $ordersPastExpiration = $activeOrders = [];
        $cleanupDate = gmdate(
            'Y-m-d H:i:s',
            time() - 60 * (Bolt_Boltpay_Model_Cron::PRE_AUTH_STATE_TIME_LIMIT_MINUTES + self::MINUTES_PADDING_FOR_TEST_ENTRY_TIMESTAMPS)
        );
        $this->createDummyOrders($pendingPaymentOrders, $paidOrders, $cleanupDate, $ordersPastExpiration, $activeOrders);

        ////////////////////////////////
        // Call subject method
        ////////////////////////////////
        $cron->cleanupOrders();
        ////////////////////////////////

        $casesCovered = [];
        // Check for preserved orders
        foreach ($activeOrders as $id => $activeOrder) {
            /** @var Mage_Sales_Model_Order $foundOrder */
            $foundOrder = Mage::getModel('sales/order')->load($id);
            $this->assertFalse($foundOrder->isObjectNew());
            if (array_key_exists($id, $paidOrders)) {
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $activeOrder->getState());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $activeOrder->getStatus());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $foundOrder->getState());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $foundOrder->getStatus());
                $casesCovered['active|preserved|processing'] = true;
            } else {
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $activeOrder->getState());
                $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $activeOrder->getStatus());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $foundOrder->getState());
                $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $foundOrder->getStatus());
                $casesCovered['active|preserved|pending_payment'] = true;
            }
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($activeOrder);
        }

        // Check that expired pending_payment orders were deleted while others preserved
        foreach ($ordersPastExpiration as $id => $expiredOrder) {
            /** @var Mage_Sales_Model_Order $foundOrder */
            $foundOrder = Mage::getModel('sales/order')->load($id);
            if (array_key_exists($id, $paidOrders)) {
                $this->assertFalse($foundOrder->isObjectNew());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $expiredOrder->getState());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $expiredOrder->getStatus());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $foundOrder->getState());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $foundOrder->getStatus());
                $casesCovered['expired|preserved|processing'] = true;
            } else {
                $this->assertArrayHasKey($id, $pendingPaymentOrders);
                $this->assertTrue($foundOrder->isObjectNew());
                $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $expiredOrder->getState());
                $this->assertEquals(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING, $expiredOrder->getStatus());
                $this->assertEmpty($foundOrder->getState());
                $this->assertEmpty($foundOrder->getStatus());
                $casesCovered['expired|deleted|pending_payment'] = true;
            }
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($expiredOrder);
        }

        // make sure that we have create found and handled at least one order for each case
        $this->assertEquals(4, count($casesCovered));
    }

    /**
     * @test
     *
     * @covers ::cleanupOrders
     */
    public function cleanupOrders_ifremovePreAuthOrderThrowsException_callsNotifyExceptionAndLogWarning()
    {
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logWarning'))
            ->getMock();
        $boltHelperMock->expects($this->once())->method('notifyException');
        $boltHelperMock->expects($this->once())->method('logWarning');
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $pendingPaymentOrders = $paidOrders = $ordersPastExpiration = $activeOrders = [];
        $cleanupDate = gmdate(
            'Y-m-d H:i:s',
            time() - 60 * (Bolt_Boltpay_Model_Cron::PRE_AUTH_STATE_TIME_LIMIT_MINUTES + self::MINUTES_PADDING_FOR_TEST_ENTRY_TIMESTAMPS)
        );
        $this->createDummyOrders($pendingPaymentOrders, $paidOrders, $cleanupDate, $ordersPastExpiration, $activeOrders);
        $boltOrderMock = $this->getClassPrototype('boltpay/order')
            ->setMethods(array('removePreAuthOrder'))
            ->getMock();
        $boltOrderMock->method('removePreAuthOrder')->willThrowException(new Exception('expected exception'));
        TestHelper::stubModel('boltpay/order', $boltOrderMock);

        $this->_currentMock->cleanupOrders();

        foreach ($activeOrders as $activeOrder) {
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($activeOrder);
        }
        foreach ($ordersPastExpiration as $expiredOrder) {
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($expiredOrder);
        }
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     *
     * @covers ::cleanupOrders
     */
    public function cleanupOrders_ifExceptionIsThrown_callsNotifyExceptionAndLogWarning()
    {
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logWarning'))
            ->getMock();
        $boltHelperMock->expects($this->once())->method('notifyException');
        $boltHelperMock->expects($this->once())->method('logWarning');
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $orderMock = $this->getClassPrototype('sales/order')
            ->setMethods(array('getCollection'))
            ->getMock();
        $orderMock->method('getCollection')->willThrowException(new Exception('expected exception'));
        TestHelper::stubModel('sales/order', $orderMock);
        $this->_currentMock->cleanupOrders();
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     *
     * @covers ::deactivateQuote
     */
    public function deactivateQuote_deactivatesQuotesAssociatedWithBoltOrders()
    {
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1, 'boltpay');
        $quoteId = $order->getQuoteId();
        $quoteModel = Mage::getModel('sales/quote');
        $quoteModel->loadByIdWithoutStore($quoteId)->setIsActive(1)->save();
        $this->_currentMock->deactivateQuote();

        $this->assertEquals(0, $quoteModel->loadByIdWithoutStore($quoteId)->getIsActive());

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * @test
     *
     * @covers ::deactivateQuote
     */
    public function deactivateQuote_ifExceptionIsThrown_callsNotifyException()
    {
        $boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException'))
            ->getMock();
        $boltHelperMock->expects($this->once())->method('notifyException');
        TestHelper::stubHelper('boltpay', $boltHelperMock);
        $resourceMock = $this->getClassPrototype('core/resource')
            ->setMethods(array('getConnection'))
            ->getMock();
        $connectionMock = $this->getClassPrototype('Magento_Db_Adapter_Pdo_Mysql')
            ->setMethods(array('query'))
            ->getMock();
        $connectionMock->method('query')->willThrowException(new Exception('expected exception'));
        $resourceMock->method('getConnection')->with('core_write')->willReturn($connectionMock);
        TestHelper::stubSingleton('core/resource', $resourceMock);
        $this->_currentMock->deactivateQuote();
        TestHelper::restoreOriginals();
    }

    /**
     * @param array $pendingPaymentOrders
     * @param array $paidOrders
     * @param string $cleanupDate
     * @param array $ordersPastExpiration
     * @param array $activeOrders
     * @throws Mage_Core_Exception
     */
    private function createDummyOrders(array &$pendingPaymentOrders, array &$paidOrders, $cleanupDate, array &$ordersPastExpiration, array &$activeOrders)
    {
        // Create dummy orders
        for ($i = 0; $i < 5; $i++) {
            for ($j = rand(2, 3); $j > 0; $j--) {
                $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1, 'boltpay');
                if ($i % 2) {
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
                        ->setStatus(Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING);
                    $pendingPaymentOrders[$order->getId()] = $order;
                } else {
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
                        ->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $paidOrders[$order->getId()] = $order;
                }
                $order->save();

                if ($i < 2) {
                    $order->setCreatedAt($cleanupDate);
                    $order->save();
                    $ordersPastExpiration[$order->getId()] = $order;
                } else {
                    $activeOrders[$order->getId()] = $order;
                }
            }
        }
    }
}