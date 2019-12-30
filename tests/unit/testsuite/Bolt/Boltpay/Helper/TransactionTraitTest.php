<?php

require_once('CouponHelper.php');

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_TransactionTrait
 */
class Bolt_Boltpay_Helper_TransactionTraitTest extends PHPUnit_Framework_TestCase
{
    /** @var int Dummy quote id */
    const IMMUTABLE_QUOTE_ID = 1000;

    /** @var int Dummy order increment id */
    const INCREMENT_ID = 11000;

    /** @var string Dummy Bolt transaction display id */
    const DISPLAY_ID = '11000|1000';

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
     * that getImmutableQuoteIdFromTransaction on transaction object containing increment and quote id
     * divided by | delimiter will return quote id
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     */
    public function getImmutableQuoteIdFromTransaction_withTransactionContainingDisplayId_returnsImmutableQuoteId()
    {
        $transaction = $this->createTransactionObject(self::DISPLAY_ID);
        $this->assertEquals(
            self::IMMUTABLE_QUOTE_ID,
            $this->currentMock->getImmutableQuoteIdFromTransaction($transaction)
        );
    }

    /**
     * @test
     * Retrieving immutable quote id from transaction object in legacy format
     * We provide parent quote id higher than quote id to simulate newer version stores
     * where the parent ID is in transaction, and immutable quote ID is in getParentQuoteId()
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     * @throws Exception if unable to create/delete dummy quote
     */
    public function getImmutableQuoteIdFromTransaction_withLegacyTransactionContainingParentQuoteId_returnsParentQuoteId()
    {
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote();
        $parentQuoteId = $quoteId + 1;
        Mage::getModel('sales/quote')
            ->loadByIdWithoutStore($quoteId)
            ->setData('parent_quote_id', $parentQuoteId)
            ->save();
        $transaction = $this->createTransactionObject(null, $quoteId);
        $this->assertEquals($parentQuoteId, $this->currentMock->getImmutableQuoteIdFromTransaction($transaction));
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * @test
     * Retrieving immutable quote id from transaction object in legacy format
     * We set parent quote id to null to simulate older version stores where the immutable quote ID is in transaction
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getImmutableQuoteIdFromTransaction
     */
    public function getImmutableQuoteIdFromTransaction_withLegacyTransactionContainingImmutableQuoteId_returnsImmutableQuoteId()
    {
        $quoteId = Bolt_Boltpay_CouponHelper::createDummyQuote(array('parent_quote_id'=> null));
        $transaction = $this->createTransactionObject(null, $quoteId);
        $this->assertEquals($quoteId, $this->currentMock->getImmutableQuoteIdFromTransaction($transaction));
        Bolt_Boltpay_CouponHelper::deleteDummyQuote($quoteId);
    }

    /**
     * @test
     * Getting increment id from of transaction display_id
     * It should contain increment and quote id divided by | delimiter
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getIncrementIdFromTransaction
     */
    public function getIncrementIdFromTransaction_whenDisplayIdContainingBothIncrementAndQuoteId_returnsFirstPartDividedByDelimiter()
    {
        $transaction = $this->createTransactionObject(self::DISPLAY_ID);
        $this->assertEquals(
            self::INCREMENT_ID,
            $this->currentMock->getIncrementIdFromTransaction($transaction)
        );
    }

    /**
     * @test
     * Getting increment id from transaction display_id that is in legacy format
     * It should contains only increment id without delimiter
     *
     * @covers Bolt_Boltpay_Helper_TransactionTrait::getIncrementIdFromTransaction
     */
    public function getIncrementIdFromTransaction_withLegacyDisplayIdContainingOnlyIncrementId_returnsCompleteDisplayId()
    {
        $transaction = $this->createTransactionObject(self::INCREMENT_ID);
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