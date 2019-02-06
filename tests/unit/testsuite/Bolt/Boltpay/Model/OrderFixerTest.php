<?php

require_once('OrderHelper.php');

/**
 * Class Bolt_Boltpay_Model_OrderFixerTest
 */
class Bolt_Boltpay_Model_OrderFixerTest extends PHPUnit_Framework_TestCase
{
    /** @var null  */
    private $app = null;

    /** @var Bolt_Boltpay_Model_OrderFixer */
    private $currentMock = null;

    /** @var Bolt_Boltpay_OrderHelper */
    private $orderHelper;

    /** @var int|null */
    private static $productId = null;

    private static $dummyProductData = array(
        'sku'   => 'PHPUNIT_TEST_1',
        'price' => 100
    );

    /** @var Mage_Sales_Model_Order | null  */
    private static $order = null;

    /** @var null  */
    private static $transaction = null;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
        $this->app->getStore()->setConfig('payment/boltpay/override_magento_order_on_mismatch', 1);
        $this->app->getStore()->setConfig('payment/boltpay/mismatch_price_tolerance', 1);
        $this->orderHelper = new Bolt_Boltpay_OrderHelper();
        $this->currentMock = Mage::getModel('boltpay/orderFixer');
    }

    protected function tearDown()
    {
        Mage::getSingleton('checkout/cart')->truncate()->save();
    }

    /**
     * Generates data for testing purposes
     */
    public static function setUpBeforeClass()
    {
        try {
            self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct(self::$dummyProductData['sku'], self::$dummyProductData);
            self::$order = Bolt_Boltpay_OrderHelper::createDummyOrder(self::$productId, 1);
        } catch (\Exception $e) {
            self::tearDownAfterClass();
        }
    }

    /**
     * Deletes dummy data
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
        Bolt_Boltpay_OrderHelper::deleteDummyOrder(self::$order);
    }

    public function testRequiresOrderUpdateToMatchBolt()
    {
        $this->setupEnvironment();
        $this->assertTrue($this->currentMock->requiresOrderUpdateToMatchBolt());
    }

    public function testDontRequiresOrderUpdateToMatchBolt()
    {
        $this->setupEnvironment(false);
        $this->assertFalse($this->currentMock->requiresOrderUpdateToMatchBolt());
    }

    public function testUpdateOrderToMatchBolt()
    {
        $this->setupEnvironment();
        $this->currentMock->updateOrderToMatchBolt();
        $this->assertEquals(self::$order->getGrandTotal(), $this->getTransactionGrandTotal());
    }

    /**
     * @param bool $requireUpdate
     * @throws Bolt_Boltpay_BadInputException
     */
    protected function setupEnvironment($requireUpdate = true)
    {
        $magentoGrandTotal = 100;
        $boltGrandTotal = $requireUpdate ? $magentoGrandTotal + 1 : $magentoGrandTotal;

        self::$order->setGrandTotal($magentoGrandTotal);
        self::$transaction = $this->orderHelper->getDummyTransactionObject($boltGrandTotal, $this->getDummyBoltItems());
        $this->currentMock->setupVariables(self::$order, self::$transaction);
    }

    /**
     * @param int $qty
     * @return array
     */
    protected function getDummyBoltItems($qty = 1)
    {
        return array(
            array(
                'sku' => self::$dummyProductData['sku'],
                'amount' => self::$dummyProductData['price'],
                'qty' => $qty
            )
        );
    }

    /**
     * @return float|int
     */
    protected function getTransactionGrandTotal()
    {
        return self::$transaction->order->cart->total_amount->amount / 100;
    }
}
