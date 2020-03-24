<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_TransactionTrait
 */
class Bolt_Boltpay_Helper_TransactionTraitTest extends PHPUnit_Framework_TestCase
{
    /** @var int Dummy quote id */
    const IMMUTABLE_QUOTE_ID = 1000;

    /** @var int Dummy order increment id */
    const INCREMENT_ID = 11000;

    /** @var string Dummy Bolt transaction display id in the format of '<increment id>|<immutable quote id>' */
    const DISPLAY_ID_IN_PIPED_FORMAT = '11000|1000';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_TransactionTrait Mock of the current trait
     */
    private $currentMock;

    /**
     * Create current mock object of the trait
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_TransactionTrait')
            ->getMockForTrait();
    }

    /**
     * @test
     * that getImmutableQuoteIdFromTransaction on transaction object containing increment and
     * immutable quote id divided by | delimiter will return the immutable quote id
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     */
    public function getImmutableQuoteIdFromTransaction_inPipedFormat_returnsImmutableQuoteId()
    {
        $transaction = $this->createTransactionObject(self::DISPLAY_ID_IN_PIPED_FORMAT, self::IMMUTABLE_QUOTE_ID - 1);
        $this->assertEquals(
            self::IMMUTABLE_QUOTE_ID,
            $this->currentMock->getImmutableQuoteIdFromTransaction($transaction)
        );
    }

    /**
     * @test
     * Retrieving immutable quote id from transaction object in the old legacy format
     * where only the immutable quote id is in transaction's order reference
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     * @throws Exception if unable to create/delete dummy quote
     */
    public function getImmutableQuoteIdFromTransaction_inOldLegacyFormat_returnsImmutableQuoteId()
    {
        $orderReference = $immutableQuoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $parentQuoteId = $immutableQuoteId - 1;
        Mage::getModel('sales/quote')
            ->loadByIdWithoutStore($immutableQuoteId)
            ->setData('parent_quote_id', $parentQuoteId)
            ->save();
        $transaction = $this->createTransactionObject('9999999999', $orderReference);

        $this->assertEquals($immutableQuoteId, $this->currentMock->getImmutableQuoteIdFromTransaction($transaction));
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($immutableQuoteId);
    }

    /**
     * @test
     * Retrieving immutable quote id from transaction object in the old legacy format
     * where only the parent quote id is in transaction's order reference
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     */
    public function getImmutableQuoteIdFromTransaction_newLegacyFormat_returnsImmutableQuoteId()
    {
        $orderReference = $parentQuoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $immutableQuoteId = $parentQuoteId + 1;
        Mage::getModel('sales/quote')
            ->loadByIdWithoutStore($parentQuoteId)
            ->setData('parent_quote_id', $immutableQuoteId)
            ->save();
        $transaction = $this->createTransactionObject('9999999999', $orderReference);

        $this->assertEquals($immutableQuoteId, $this->currentMock->getImmutableQuoteIdFromTransaction($transaction));
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($parentQuoteId);
    }

    /**
     * @test
     * Getting increment id from of transaction display_id
     * that contains increment and immutable quote id divided by | delimiter
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getIncrementIdFromTransaction
     */
    public function getIncrementIdFromTransaction_inPipedFormat_returnsIncrementId()
    {
        $orderReference = $parentQuoteId = self::IMMUTABLE_QUOTE_ID - 1;
        $transaction = $this->createTransactionObject(self::DISPLAY_ID_IN_PIPED_FORMAT, $orderReference);
        $this->assertEquals(
            self::INCREMENT_ID,
            $this->currentMock->getIncrementIdFromTransaction($transaction)
        );
    }

    /**
     * @test
     * Getting increment id from transaction where the display id is in any legacy format, (i.e. non-piped),
     * will simply return the transaction's display id value
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getIncrementIdFromTransaction
     */
    public function getIncrementIdFromTransaction_inAnyLegacyFormat_returnsTransactionDisplayId()
    {
        $orderReference = 1234567890; # could be the parent quote or immutable quote ID. old or new legacy is irrelevant
        $transaction = $this->createTransactionObject(self::INCREMENT_ID, $orderReference);
        $this->assertEquals(
            self::INCREMENT_ID,
            $this->currentMock->getIncrementIdFromTransaction($transaction)
        );
    }

    /**
     * Creates transaction object with provider display id and order reference
     *
     * @param string|null $displayId to be set as display_id in transaction object
     * @param string|null $orderReference to be set as order_reference in transaction object
     * @return object containing transaction data
     */
    private function createTransactionObject($displayId = null, $orderReference = null)
    {
        return json_decode(
            json_encode(
                array(
                    'order' => array(
                        'cart' => array(
                            'display_id' => $displayId,
                            'order_reference' => $orderReference
                        )
                    )
                )
            )
        );
    }
}