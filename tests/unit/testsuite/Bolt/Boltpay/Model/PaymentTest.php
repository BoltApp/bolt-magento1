<?php
require_once('TestHelper.php');
require_once('OrderHelper.php');

class Bolt_Boltpay_Model_PaymentTest extends PHPUnit_Framework_TestCase
{
    private $app;

    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Bolt_Boltpay_TestHelper|null
     */
    private $testHelper = null;

    /** @var Bolt_Boltpay_Model_Payment */
    private $_currentMock;

    /**
     * @var int|null Dummy product ID
     */
    private static $productId = null;

    public function setUp() 
    {
        /* You'll have to load Magento app in any test classes in this method */
        $this->app = Mage::app('default');
        $this->_currentMock = Mage::getModel('boltpay/payment');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    /**
     * Generate dummy product data used for creating test orders
     */
    public static function setUpBeforeClass()
    {
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1', array(), 20);
        Bolt_Boltpay_Helper_Data::$fromHooks = true;
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    public function testPaymentConstants()
    {
        $payment =  $this->_currentMock;
        $this->assertEquals('Credit & Debit Card',$payment::TITLE);
        $this->assertEquals('boltpay', $payment->getCode());
    }

    public function testPaymentConfiguration()
    {
        // All the features that are enabled
        $this->assertTrue($this->_currentMock->canAuthorize());
        $this->assertTrue($this->_currentMock->canCapture());
        $this->assertTrue($this->_currentMock->canRefund());
        $this->assertTrue($this->_currentMock->canVoid(new Varien_Object()));
        $this->assertTrue($this->_currentMock->canUseCheckout());
        $this->assertTrue($this->_currentMock->canFetchTransactionInfo());
        $this->assertTrue($this->_currentMock->canEdit());
        $this->assertTrue($this->_currentMock->canRefundPartialPerInvoice());
        $this->assertTrue($this->_currentMock->canCapturePartial());
        $this->assertTrue($this->_currentMock->canUseInternal());
        $this->assertTrue($this->_currentMock->isInitializeNeeded());

        // All the features that are disabled
        $this->assertFalse($this->_currentMock->canUseForMultishipping());
        $this->assertFalse($this->_currentMock->canCreateBillingAgreement());
        $this->assertFalse($this->_currentMock->isGateway());
        $this->assertFalse($this->_currentMock->canManageRecurringProfiles());
        $this->assertFalse($this->_currentMock->canOrder());
    }

    public function testAssignDataIfNotAdminArea()
    {
        $data = new Varien_Object(array('bolt_reference' => '123456890'));

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea'))
            ->enableOriginalConstructor()
            ->getMock();

        $currentMock->expects($this->once())
            ->method('isAdminArea')
            ->will($this->returnValue(false));

        $result = $currentMock->assignData($data);

        $this->assertEquals($currentMock, $result);
    }

    public function testAssignData()
    {
        $data = new Varien_Object(array('bolt_reference' => '123456890'));

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea', 'getInfoInstance'))
            ->enableOriginalConstructor()
            ->getMock();

        $currentMock->expects($this->once())
            ->method('isAdminArea')
            ->will($this->returnValue(true));

        $mockPaymentInfo = $this->getMockBuilder('Mage_Payment_Model_Info')
            ->setMethods(array('setAdditionalInformation'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $currentMock->expects($this->once())
            ->method('getInfoInstance')
            ->will($this->returnValue($mockPaymentInfo));

        $result = $currentMock->assignData($data);

        $this->assertEquals($currentMock, $result);
    }

    public function testGetConfigDataIfSkipPaymentEnableAndAllowSpecific()
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $currentMock
            ->method('isAdminArea')
            ->will($this->returnValue(false));

        $field = 'allowspecific';
        $result = $currentMock->getConfigData($field);

        $this->assertNull($result);
    }

    public function testGetConfigDataIfSkipPaymentEnableAndSpecificCountry()
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $currentMock
            ->method('isAdminArea')
            ->will($this->returnValue(false));

        $field = 'specificcountry';
        $result = $currentMock->getConfigData($field);

        $this->assertNull($result);
    }

    public function testGetConfigDataAdminAreaWithFieldTitle()
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 0);

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $currentMock->expects($this->once())
            ->method('isAdminArea')
            ->will($this->returnValue(true));

        $field = 'title';
        $result = $currentMock->getConfigData($field);

        $this->assertEquals(Bolt_Boltpay_Model_Payment::TITLE_ADMIN, $result, 'ADMIN_TITLE field does not match');
    }

    public function testGetConfigDataNotAdminAreaWithFieldTitle()
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 0);

        $currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Payment')
            ->setMethods(array('isAdminArea'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();

        $currentMock->expects($this->once())
            ->method('isAdminArea')
            ->will($this->returnValue(false));

        $field = 'title';
        $result = $currentMock->getConfigData($field);

        $this->assertEquals(Bolt_Boltpay_Model_Payment::TITLE, $result, 'TITLE field does not match');
    }

    public function testCanReviewPayment(){
        $orderPayment = new Mage_Sales_Model_Order_Payment();
        $orderPayment->setAdditionalInformation('bolt_transaction_status', Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE);
        $this->assertTrue($this->_currentMock->canReviewPayment($orderPayment));
    }

    /**
     * Test if product inventory is restored after order cancellation.
     * Order will be deleted once Bolt notify the store that transaction is irreversibly rejected
     *
     * @throws Mage_Core_Exception
     */
    public function testCancelOrderOnTransactionUpdate()
    {
        $expectedProductStock = 10;
        $orderProductQty = 2;

        // Assert initial product store stock
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals($expectedProductStock, (int)$storeProduct->getQty());

        // Create order with the product
        $this->testHelper->createCheckout('guest');
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, $orderProductQty);

        // After order creation product store stock should be reduced by the order qty
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals(($expectedProductStock - $orderProductQty), (int)$storeProduct->getQty());

        // Transaction is set to REJECTED_IRREVERSIBLE
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', '12345');
        $boltPayment = Mage::getModel('boltpay/payment');
        $boltPayment->handleTransactionUpdate(
            $order->getPayment(),
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED);

        // After the hook is triggered order should be deleted and product stock restored
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals($expectedProductStock, (int)$storeProduct->getQty());
        $this->assertEquals('canceled', $order->getStatus());

        // Delete dummy order
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Test if product inventory is restored after order cancellation.
     * Order will be canceled once Bolt notify the store that transaction is voided
     *
     * @throws Mage_Core_Exception
     */
    public function testCancelOrderOnVoidTransactionUpdate()
    {
        $expectedProductStock = 10;
        $orderProductQty = 2;

        // Assert initial product store stock
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals($expectedProductStock, (int)$storeProduct->getQty());

        // Create order with the product
        $this->testHelper->createCheckout('guest');
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, $orderProductQty);

        // After order creation product store stock should be reduced by the order qty
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals(($expectedProductStock - $orderProductQty), (int)$storeProduct->getQty());

        // Void Transaction
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('bolt_reference', '23456');
        $boltPayment = Mage::getModel('boltpay/payment');
        $boltPayment->handleVoidTransactionUpdate($order->getPayment());

        // After the hook is triggered order should be deleted and product stock restored
        $storeProduct = Bolt_Boltpay_ProductProvider::getStoreProductWithQty(self::$productId);
        $this->assertEquals($expectedProductStock, (int)$storeProduct->getQty());
        $this->assertEquals('canceled', $order->getStatus());

        // Delete dummy order
        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }
  
    /**
     * Verifies that when the order state is "pending_payment" and a pending hook is received:
     *
     * 1.) The order state is set to "payment_review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     */
    public function testHandleTransactionUpdateWithPendingForOrdersWithPendingPayment()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $order->setStatus('pending_bolt');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionPending(true);
        $orderPayment->unsAdditionalInformation('bolt_transaction_status');
        $orderPayment->setAdditionalInformation('bolt_reference', 'GS01-TST0-YR19');


        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertEquals('pending_bolt', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction'));

        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            null
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());

        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history)-1]->getComment()
        );

        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals('pending', $orderPayment->getAdditionalInformation('bolt_transaction_status'));

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Verifies that when there is no Bolt reference set in the payment and a hook is received
     *
     * 1.) The order state is unchanged
     * 2.) The order status is unchanged
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for bolt_transaction_status is unchanged
     * 6.) An Exception is thrown
     */
    public function testHandleTransactionUpdateWithPendingForOrdersWithNoBoltTransactionReference()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus('pending');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->unsAdditionalInformation('bolt_reference');
        $orderPayment->unsAdditionalInformation('bolt_transaction_status');
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertNull($orderPayment->getAdditionalInformation('bolt_reference'));
        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertEquals('pending', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $this->assertFalse($orderPayment->getIsTransactionPending());

        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
            );
        } catch (Exception $ex) {
            $this->assertInstanceOf(Exception::class, $ex);
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertTrue($exceptionThrown);
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_reference'));
            $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
            $this->assertEquals('pending', $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Verifies that when the order state is "new" and a pending hook is received:
     *
     * 1.) The order state is set to "payment review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     */
    public function testHandleTransactionUpdateWithPendingForNewOrders()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_NEW);
        $order->setStatus('pending');

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_NEW, $order->getState());
        $this->assertEquals('pending', $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            null
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history)-1]->getComment()
        );
        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Verifies that when the order state is "processing" and a pending hook is received:
     *
     * 1.) The order state is set to "payment review"
     * 2.) The order status is set to "payment_review"
     * 3.) There is a history message confirming this update
     * 4.) The payment's IsTransactionPending flag is set to true
     * 5.) The payment's additional information for `bolt_transaction_status` is `pending`
     */
    public function testHandleTransactionUpdateWithPendingForOrdersBeingProcessed()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PROCESSING, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );
        $payment->handleTransactionUpdate(
            $orderPayment,
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            Bolt_Boltpay_Model_Payment::TRANSACTION_AUTHORIZED
        );

        $history = $order->getAllStatusHistory();
        $commentsCountAfterCall = count($history);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            'BOLT notification: Payment is under review',
            $history[count($history)-1]->getComment()
        );
        $this->assertEquals($commentsCountBeforeCall + 1, $commentsCountAfterCall);
        $this->assertTrue($orderPayment->getIsTransactionPending());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );

        Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
    }

    /**
     * Verifies that when the order state is "completed" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "complete"
     * 2.) The order status is unchanged - "complete"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForCompletedOrders()
    {
        $order = $this->createMockOrderWithPayment(
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            0.0 // This will complete the order. We can not explicitly set the order state to "completed" as it is a protected state and will throw an exception.
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );
        $orderPayment->setIsTransactionPending(true);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
                $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
                $this->assertEquals(
                    'Cannot transition a transaction from completed to pending',
                    $ex->getMessage()
                );
                $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_COMPLETE, $order->getStatus());
            $this->assertTrue($exceptionThrown);
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_COMPLETED,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Verifies that when the order state is "closed" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "closed"
     * 2.) The order status is unchanged - "closed"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForClosedOrders()
    {
        $order = $this->createMockOrderWithPayment(
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
            5.0,
            true // This will close the order. We can not create completed order as "closed" is a protected state
        );

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionPending(false);
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
        );

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from credit to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CLOSED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REFUND,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Verifies that when the order state is "cancelled" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "canceled"
     * 2.) The order status is unchanged - "canceled"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForCanceledOrders()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_CANCELED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
        );
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
        $this->assertEquals(
            $orderPayment->getAdditionalInformation('bolt_transaction_status'),
            Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
        );
        $this->assertFalse($orderPayment->getIsTransactionPending());
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from cancelled to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_CANCELLED,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     *
     * Verifies that when the order state is "holded" and a pending hook is received:
     *
     * 1.) The order state is unchanged - "holded"
     * 2.) The order status is unchanged - "holded"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is unchanged
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForOnHoldOrders()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_HOLDED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_HOLDED);
        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();

        $initialIsPendingState = $orderPayment->getIsTransactionPending();
        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;
        $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getStatus());
        $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                null
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from on-hold to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);
            $this->assertTrue($exceptionThrown);
            $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_HOLDED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertEquals($initialIsPendingState, $orderPayment->getIsTransactionPending());
            $this->assertNull($orderPayment->getAdditionalInformation('bolt_transaction_status'));
            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Verifies that when the order state is "canceled", when last payment status is "irreversible_rejected"
     * and a pending hook is received:
     *
     * 1.) The order state is unchanged - "canceled"
     * 2.) The order status is unchanged - "canceled"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is false
     * 5.) The payment's additional information for `bolt_transaction_status` is unchanged
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForOrdersWithCanceledIrreversibleRejected()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_CANCELED);
        $order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE
        );
        $orderPayment->setIsTransactionPending(false);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from rejected_irreversible to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertFalse($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_IRREVERSIBLE,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Verifies that when the order state is "payment_review", when last payment status is "reversible_rejected"
     * and a pending hook is received:
     *
     * 1.) The order state is unchanged - "payment_review"
     * 2.) The order status is unchanged - "payment_review"
     * 3.) There is no new history messages
     * 4.) The payment's IsTransactionPending flag is true
     * 5.) The payment's additional information for `bolt_transaction_status` is rejected_reversible
     * 6.) An Bolt_Boltpay_InvalidTransitionException is thrown
     */
    public function testHandleTransactionUpdateWithPendingForOrdersWithPaymentReviewReversibleRejected()
    {
        $order = $this->createMockOrderWithPayment(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
        $order->setStatus(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);

        $payment = Mage::getModel('boltpay/payment');
        $payment->setOrder($order);
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(
            'bolt_transaction_status',
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
        );
        $orderPayment->setIsTransactionPending(true);

        $history = $order->getAllStatusHistory();
        $commentsCountBeforeCall = count($history);
        $exceptionThrown = false;

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
            $orderPayment->getAdditionalInformation('bolt_transaction_status')
        );
        $this->assertTrue($orderPayment->getIsTransactionPending());
        try {
            $payment->handleTransactionUpdate(
                $orderPayment,
                Bolt_Boltpay_Model_Payment::TRANSACTION_PENDING,
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE
            );
        } catch (Bolt_Boltpay_InvalidTransitionException $ex) {
            $this->assertInstanceOf(Bolt_Boltpay_InvalidTransitionException::class, $ex);
            $this->assertEquals(
                'Cannot transition a transaction from rejected_reversible to pending',
                $ex->getMessage()
            );
            $exceptionThrown = true;
        } finally {
            $history = $order->getAllStatusHistory();
            $commentsCountAfterCall = count($history);

            $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
            $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getStatus());
            $this->assertEquals($commentsCountBeforeCall, $commentsCountAfterCall);
            $this->assertTrue($exceptionThrown);
            $this->assertTrue($orderPayment->getIsTransactionPending());
            $this->assertEquals(
                Bolt_Boltpay_Model_Payment::TRANSACTION_REJECTED_REVERSIBLE,
                $orderPayment->getAdditionalInformation('bolt_transaction_status')
            );

            Bolt_Boltpay_OrderHelper::deleteDummyOrder($order);
        }
    }

    /**
     * Initializes the mock order used for testing transaction webhooks
     *
     * @param string $initialOrderState State to be set when creating test order. In some cases it will be
     *                                  different from $previousOrderState
     * @param float  $baseGrandTotal    Base grand total for the order. If equals to 0 the order state
     *                                  will be set to "complete". If greater than zero initial order
     *                                  state will be set to $previousOrderState while saving the order.
     * @param float  $totalRefunded     Total refund for the order. If greater than 0 order will be closed
     *
     * @return Mage_Sales_Model_Order Order object we will use for assertion
     * @throws Mage_Core_Exception Throws exception in case there is no Bolt reference set in the payment object
     */
    private function createMockOrderWithPayment($initialOrderState, $baseGrandTotal = 5.0, $totalRefunded = 0.0)
    {
        // Create dummy order
        $order = Bolt_Boltpay_OrderHelper::createDummyOrder(
            self::$productId,
            1,
            Bolt_Boltpay_Model_Payment::METHOD_CODE
        );

        // Set refund and base grand total
        $order->setTotalRefunded($totalRefunded);
        $order->setBaseGrandTotal($baseGrandTotal);

        // Set and confirm initial state of the order. This is important starting point
        $order->setState($initialOrderState);
        $order->save();

        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation('bolt_reference', 'GS01-TST0-YR19');

        return $order;
    }
}
