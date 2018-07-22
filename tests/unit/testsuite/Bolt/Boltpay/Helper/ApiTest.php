<?php

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_Helper_ApiTest
 */
class Bolt_Boltpay_Helper_ApiTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var int|null
     */
    private static $productId = null;

    /**
     * @var Bolt_Boltpay_TestHelper|null
     */
    private $testHelper;

    /**
     * @var Bolt_Boltpay_Helper_Api
     */
    private $currentMock;

    private $testBoltResponse;

    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        /** @var Mage_Core_Model_Store $appStore */
        $appStore = $this->app->getStore();
        $appStore->resetConfig();

        $this->testHelper = null;

        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(['verify_hook_secret', 'verify_hook_api', 'getBoltContextInfo'])
            ->enableOriginalConstructor()
            ->getMock();

        $appStore->setConfig('payment/boltpay/active', 1);

        // Prepare test response object
        $testBoltResponse = new stdClass();
        $testBoltResponse->token = md5('bolt');
        $testBoltResponse->cart = new StdClass();
        $testBoltResponse->external_data = new StdClass();
        $testBoltResponse->cart->order_reference = '69';
        $testBoltResponse->cart->display_id = 100001069;
        $testBoltResponse->cart->currency = [];
        $testBoltResponse->cart->items = [];

        $this->testBoltResponse = (object) $testBoltResponse;
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

    public function testBuildCart()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $_quote = $cart->getQuote();
        $_items = $_quote->getAllItems();
        $_multipage = true;
        $item = $_items[0];
        $product = $item->getProduct();

        $productMediaConfig = Mage::getModel('catalog/product_media_config');
        $imageUrl = $productMediaConfig->getMediaUrl($product->getThumbnail());

        $expected = array (
          'order_reference' => $_quote->getId(),
          'display_id' => NULL,
          'items' =>
          array (
            0 =>
            array (
              'reference' => $_quote->getId(),
              'image_url' => $imageUrl,
              'name' => $item->getName(),
              'sku' => $item->getSku(),
              'description' => substr($product->getDescription(), 0, 8182) ?: '',
              'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
              'unit_price' => round($item->getCalculationPrice() * 100),
              'quantity' => $item->getQty(),
              'type' => 'physical'
            ),
          ),
          'currency' => $_quote->getQuoteCurrencyCode(),
          'discounts' => array (),
          'total_amount' => round($_quote->getSubtotal() * 100),
        );

        $result = $this->currentMock->buildCart($_quote, $_items, $_multipage);

        $this->assertEquals($expected, $result);
    }

    public function testStoreHasAllCartItems()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = $this->currentMock->storeHasAllCartItems($cart->getQuote());

        $this->assertTrue($result);
    }

    public function testIsResponseError()
    {
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertFalse($result);
    }

    public function testIsResponseErrorWithErrors()
    {
        $this->testBoltResponse->errors = ['some_error_key' => 'some_error_message'];
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }

    public function testIsResponseErrorWithErrorCode()
    {
        $this->testBoltResponse->error_code = 10603;
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }
}
