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

    public function setUp()
    {
        /* You'll have to load Magento app in any test classes in this method */
        $this->app = Mage::app('default');
        $this->_currentMock = Mage::getModel('boltpay/payment');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    /**
     * Generate dummy products for testing purposes
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1');
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
}