<?php

class Bolt_Boltpay_Model_PaymentTest extends PHPUnit_Framework_TestCase
{
    public function setUp() 
    {
        /* You'll have to load Magento app in any test classes in this method */
        $app = Mage::app('default');
    }

    public function testPaymentConstants() 
    {
        $payment = Mage::getModel('boltpay/payment');
        $this->assertEquals('Credit & Debit Card', $payment::TITLE);
        $this->assertEquals('boltpay', $payment->getCode());
    }

    public function testPaymentConfiguration() 
    {
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
        $this->assertTrue($payment->canCaptureOnce());
        $this->assertTrue($payment->canUseInternal());

        // All the features that are disabled
        $this->assertFalse($payment->canUseForMultishipping());
        $this->assertFalse($payment->canCreateBillingAgreement());
        $this->assertFalse($payment->isGateway());
        $this->assertFalse($payment->isInitializeNeeded());
        $this->assertFalse($payment->canManageRecurringProfiles());
        $this->assertFalse($payment->canOrder());
    }
}
