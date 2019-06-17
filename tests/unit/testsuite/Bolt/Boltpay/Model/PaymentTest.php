<?php

class Bolt_Boltpay_Model_PaymentTest extends PHPUnit_Framework_TestCase
{
    private $app;

    /** @var Bolt_Boltpay_Model_Payment */
    private $_currentMock;

    public function setUp() 
    {
        /* You'll have to load Magento app in any test classes in this method */
        $this->app = Mage::app('default');
        $this->_currentMock = Mage::getModel('boltpay/payment');
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

        // All the features that are disabled
        $this->assertFalse($this->_currentMock->canUseForMultishipping());
        $this->assertFalse($this->_currentMock->canCreateBillingAgreement());
        $this->assertFalse($this->_currentMock->isGateway());
        $this->assertFalse($this->_currentMock->isInitializeNeeded());
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
}
