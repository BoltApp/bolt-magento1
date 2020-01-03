<?php

require_once('MockingTrait.php');
require_once('OrderHelper.php');

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Service_Order
 */
class Bolt_Boltpay_Model_Service_OrderTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var int Amount to be invoiced */
    const INVOICE_AMOUNT = 100;

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_Model_Service_Order';

    /**
     * @var MockObject|Bolt_Boltpay_Model_Service_Order mock instance of class tested
     */
    private $currentMock;

    /**
     * @var MockObject|Mage_Sales_Model_Convert_Order mock instance of order converter
     */
    private $convertorMock;

    /**
     * @var Mage_Sales_Model_Order dummy order object
     */
    private $order;

    /**
     * @var MockObject|Bolt_Boltpay_Helper_Data mock instance of Bolt helper
     */
    private $boltHelperMock;

    /**
     * @var Bolt_Boltpay_TestHelper instance of test helper
     */
    private $testHelper;

    /**
     * Setup test dependencies
     *
     * @throws Mage_Core_Exception if unable to stub bolt helper
     */
    protected function setUp()
    {
        $this->order = Mage::getModel('sales/order');
        $this->convertorMock = $this->getClassPrototype('Mage_Sales_Model_Convert_Order')
            ->setMethods(array('toInvoice'))
            ->getMock();
        $this->currentMock = $this->getTestClassPrototype()->setConstructorArgs(array($this->order))->setMethods(null)
            ->getMock();
        $this->boltHelperMock = $this->getClassPrototype('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('notifyException', 'logException'))->getMock();
        Bolt_Boltpay_TestHelper::stubHelper('boltpay', $this->boltHelperMock);
    }

    /**
     * Restore original stubbed objects
     *
     * @throws ReflectionException from test helper if Mage doesn't have _config property
     * @throws Mage_Core_Model_Store_Exception from test helper if store doesn't  exist
     * @throws Mage_Core_Exception from test helper if registry key already exists
     */
    protected function tearDown()
    {
        Bolt_Boltpay_TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that prepareInvoiceWithoutItems returns new invoice for order with provided amount
     * which is also added to invoice collection
     *
     * @covers ::prepareInvoiceWithoutItems
     *
     * @throws Exception from method tested if unable to convert order to invoice
     */
    public function prepareInvoiceWithoutItems_withValidAmountAndOrder_returnsNewInvoiceForOrder()
    {
        $this->boltHelperMock->expects($this->never())->method('notifyException');
        $this->boltHelperMock->expects($this->never())->method('logException');

        $invoice = $this->currentMock->prepareInvoiceWithoutItems(self::INVOICE_AMOUNT);
        $this->assertEquals(self::INVOICE_AMOUNT, $invoice->getBaseGrandTotal());
        $this->assertEquals(self::INVOICE_AMOUNT, $invoice->getSubtotal());
        $this->assertEquals(self::INVOICE_AMOUNT, $invoice->getBaseSubtotal());
        $this->assertEquals(self::INVOICE_AMOUNT, $invoice->getGrandTotal());
        $this->assertContains($invoice, $invoice->getOrder()->getInvoiceCollection()->getItems());
    }

    /**
     * @test
     * that prepareInvoiceWithoutItems will log and rethrow exception if it occurs during conversion of order to invoice
     *
     * @covers ::prepareInvoiceWithoutItems
     * @expectedException Exception
     * @expectedExceptionMessage Unable to convert order to invoice
     */
    public function prepareInvoiceWithoutItems_whenConvertorThrowsException_logsAndRethrowsException()
    {
        $exception = new Exception('Unable to convert order to invoice');

        $metaDataConstraint = $this->equalTo(
            array(
                'amount' => self::INVOICE_AMOUNT,
                'order'  => var_export($this->order->debug(), true)
            )
        );

        $this->boltHelperMock->expects($this->once())->method('notifyException')->with($exception, $metaDataConstraint);
        $this->boltHelperMock->expects($this->once())->method('logException')->with($exception, $metaDataConstraint);

        $this->currentMock->setConvertor($this->convertorMock);
        $this->convertorMock->expects($this->once())->method('toInvoice')->willThrowException($exception);

        $this->currentMock->prepareInvoiceWithoutItems(self::INVOICE_AMOUNT);
    }
}
