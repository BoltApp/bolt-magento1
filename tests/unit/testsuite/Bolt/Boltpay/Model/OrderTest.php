<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Model_OrderTest
 */
class Bolt_Boltpay_Model_OrderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Bolt_Boltpay_TestHelper|null
     */
    private $testHelper = null;

    /**
     * @var Bolt_Boltpay_Model_Order
     */
    private $currentMock;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->currentMock = Mage::getModel('boltpay/order');
        $this->testHelper = new Bolt_Boltpay_TestHelper();
    }

    protected function tearDown()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
    }

    /**
     * Generate dummy products for testing purposes
     */
    public static function setUpBeforeClass()
    {
        // Create some dummy product:
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_' . 1);
    }

    /**
     * Delete dummy products after the test
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    public function testGetOutOfStockSKUs()
    {
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = (bool)$this->currentMock->getOutOfStockSKUs($cart->getQuote());

        $this->assertFalse($result);
    }

    /**
     * Check that the reference is present. If not, we have an exception.
     */
    public function testCreateOrder_ifReferenceIsEmptyThrowException()
    {
        $message = "Bolt transaction reference is missing in the Magento order creation process.";
        $this->setExpectedException('Exception', $message);
        $this->currentMock->createOrder('');
    }

    /**
     * check that the order is in the system.  If not, we have an exception.
     */
    public function testCreateOrder_ifImmutableQuoteEmptyThrowException()
    {
        $reference = 'AAAA-BBBB-XXXX-ZZZZ';
        $quoteId = 1000;
        $immutableQuoteId = 1001;
        $sessionQuoteId = 1000;

        $transaction = $this->testHelper->getTransactionMock($quoteId);

        $quote = $this->getMockBuilder('Mage_Sales_Model_Quote')
            ->setMethods(['isEmpty'])
            ->disableOriginalConstructor()
            ->getMock();

        $quote->method('isEmpty')
            ->willReturn(true);

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Model_Order')
            ->setMethods(['getImmutableQuoteIdFromTransaction', 'getQuoteById'])
            ->getMock();

        $this->currentMock->method('getImmutableQuoteIdFromTransaction')
            ->willReturn($immutableQuoteId);
        $this->currentMock->method('getQuoteById')
            ->will($this->returnValueMap([
                [$immutableQuoteId, $quote]
            ]));

        $message = "The expected immutable quote [$immutableQuoteId] is missing from the Magento system.  Were old quotes recently removed from the database?";
        $this->setExpectedException('Exception', $message);

        $this->currentMock->createOrder($reference, $sessionQuoteId, false, $transaction);
    }
}
