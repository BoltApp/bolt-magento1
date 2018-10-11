<?php

class Bolt_Boltpay_Model_PaymentTest extends PHPUnit_Framework_TestCase
{
    private $app;

    public function setUp() 
    {
        /* You'll have to load Magento app in any test classes in this method */
        $this->app = Mage::app('default');
    }

    public function testPaymentConstants() 
    {
        $payment = Mage::getModel('boltpay/payment');
        $this->assertEquals('Credit & Debit Card', $payment::TITLE);
        $this->assertEquals('boltpay', $payment->getCode());
    }

    public function testPaymentConfiguration() 
    {
        /** @var Bolt_Boltpay_Model_Payment $payment */
        $payment = Mage::getModel('boltpay/payment');
        // All the features that are enabled
        $this->assertTrue($payment->canAuthorize());
        $this->assertTrue($payment->canCapture());
        $this->assertTrue($payment->canRefund());
        $this->assertTrue($payment->canVoid(new Varien_Object()));
        $this->assertTrue($payment->canUseCheckout());
        $this->assertTrue($payment->canFetchTransactionInfo());
        $this->assertTrue($payment->canEdit());
        $this->assertTrue($payment->canRefundPartialPerInvoice());
        $this->assertTrue($payment->canCapturePartial());
        $this->assertTrue($payment->canUseInternal());

        // All the features that are disabled
        $this->assertFalse($payment->canUseForMultishipping());
        $this->assertFalse($payment->canCreateBillingAgreement());
        $this->assertFalse($payment->isGateway());
        $this->assertFalse($payment->isInitializeNeeded());
        $this->assertFalse($payment->canManageRecurringProfiles());
        $this->assertFalse($payment->canOrder());
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
}
