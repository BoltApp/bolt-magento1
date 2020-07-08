<?php

use Bolt_Boltpay_TestHelper as TestHelper;

/**
 * @coversDefaultClass Bolt_Boltpay_Block_Info
 */
class Bolt_Boltpay_Block_InfoTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Block_Info Mocked instance of the block being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Payment_Block_Info
     */
    private $infoMock;
    
    /**
     * @var MockObject|Mage_Sales_Model_Order_Payment Mocked instance of Magento order payment object
     */
    private $paymentMock;

    /**
     * Setup test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Block_Info')
            ->setMethods(array('getInfo','getMethod'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->infoMock = $this->getMockBuilder('Mage_Payment_Block_Info')
            ->setMethods(array('getCcType','getCcLast4','getAdditionalInformation'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
        $this->paymentMock = $this->getMockBuilder('Mage_Sales_Model_Order_Payment')
            ->setMethods(array('getTitle'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
    }

    /**
     * @test
     * that prepareSpecificInformation return a dummy cc info array
     * @covers ::_prepareSpecificInformation
     */
    public function prepareSpecificInformation()
    {
        $this->currentMock->expects(self::any())->method('getInfo')->willReturn($this->infoMock);
        $this->infoMock->expects(self::once())->method('getCcType')->willReturn('visa');
        $this->infoMock->expects(self::once())->method('getCcLast4')->willReturn('1111');
        $this->infoMock->expects(self::once())->method('getAdditionalInformation')->with('bolt_payment_processor')
            ->willReturn('vantiv');
        $data = TestHelper::callNonPublicFunction($this->currentMock, '_prepareSpecificInformation', [null]);
        $this->assertEquals(
            [
                'Credit Card Type' => 'VISA',
                'Credit Card Number' => 'xxxx-1111'
            ], $data->getData()
        );
    }
    
    /**
     * @test
     * 
     * @covers ::displayPaymentMethodTitle
     */
    public function displayPaymentMethodTitle_ifOrderPaidWithCreditCardFromFrontend_returnConfigTitle()
    {
        Mage::app()->getStore()->setId(1);
        $this->currentMock->expects(self::any())->method('getInfo')->willReturn($this->infoMock);
        $this->currentMock->expects(self::any())->method('getMethod')->willReturn($this->paymentMock);
        $this->infoMock->expects(self::once())->method('getAdditionalInformation')->with('bolt_payment_processor')
            ->willReturn('vantiv');
        $this->paymentMock->expects(self::once())->method('getTitle')->willReturn(Mage::getStoreConfig('payment/boltpay/title'));
        $this->assertEquals(
            Mage::getStoreConfig('payment/boltpay/title'),
            $this->currentMock->displayPaymentMethodTitle()
        );
    }
    
    /**
     * @test
     * 
     * @covers ::displayPaymentMethodTitle
     */
    public function displayPaymentMethodTitle_ifOrderPaidWithCreditCardFromAdminArea_returnDefaultTitle()
    {
        $this->currentMock->expects(self::any())->method('getInfo')->willReturn($this->infoMock);
        $this->currentMock->expects(self::any())->method('getMethod')->willReturn($this->paymentMock);
        $this->infoMock->expects(self::once())->method('getAdditionalInformation')->with('bolt_payment_processor')
            ->willReturn('vantiv');
        $this->paymentMock->expects(self::once())->method('getTitle')->willReturn(Bolt_Boltpay_Model_Payment::TITLE);
        $this->assertEquals(
            Bolt_Boltpay_Model_Payment::TITLE,
            $this->currentMock->displayPaymentMethodTitle()
        );
    }
    
    /**
     * @test
     *
     * @dataProvider displayPaymentMethodTitle_ifOrderPaidWithAPM_Provider
     * 
     * @covers ::displayPaymentMethodTitle
     */
    public function displayPaymentMethodTitle_ifOrderPaidWithAPM_returnProperTitle($boltPaymentProcessor, $expectedResult)
    {
        $this->currentMock->expects(self::any())->method('getInfo')->willReturn($this->infoMock);
        $this->infoMock->expects(self::once())->method('getAdditionalInformation')->with('bolt_payment_processor')
            ->willReturn($boltPaymentProcessor);
        $this->assertEquals(
            $expectedResult,
            $this->currentMock->displayPaymentMethodTitle()
        );
    }
    
    /**
     * Data provider for {@see displayPaymentMethodTitle_ifOrderPaidWithAPM_returnProperTitle}
     *
     * @return array containing available payment gateways
     */
    public function displayPaymentMethodTitle_ifOrderPaidWithAPM_Provider()
    {
        return array(
            array(
                'boltPaymentProcessor'  => 'paypal',
                'expectedResult'        => 'Bolt-PayPal'
            ),
            array(
                'boltPaymentProcessor'  => 'afterpay',
                'expectedResult'        => 'Bolt-Afterpay'
            ),
            array(
                'newTransactionStatus'  => 'newapm',
                'expectedResult'        => 'Bolt-NEWAPM'
            ),
        );
    }
}