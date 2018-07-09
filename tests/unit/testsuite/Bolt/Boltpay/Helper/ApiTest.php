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
//            ->disableOriginalClone()
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

    /**
     * @inheritdoc
     */
    public function testVerify_hook()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Api')
            ->setMethods(['verify_hook_secret', 'verify_hook_api', 'getSigningKey'])
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $payload = '{"quote_id":"666","reference":"QQQQ-ABCD-28ZU","transaction_id":"TOeHtN0dxxAgt","notification_type":"pending"}';
        $hmac_header = 'oA50gC+Fajoq0cDmIFcLT+MAN5ukVIXowe3iGO8Glug=';

        $encryptedSigningKey = 'CyyPvZ1MqHLNze2M+nQFBuMnILlm2YblO6FlG7OjHVA72WrV3bjvDLX7ru/0DLp9HA3CGWipH7guy14PcdhPfA==';
        $this->currentMock
            ->method('getSigningKey')
            ->will($this->returnValue($encryptedSigningKey));

        $this->currentMock->expects($this->any())
            ->method('verify_hook_secret')
            ->with(
                $this->equalTo($payload),
                $this->equalTo($hmac_header)
            )
            ->will($this->returnValue(true));

        $this->currentMock->expects($this->any())
            ->method('verify_hook_api')
            ->with(
                $this->equalTo($payload),
                $this->equalTo($hmac_header)
            )
            ->will($this->returnValue(true));

        $result = $this->currentMock->verify_hook($payload, $hmac_header);

        $this->assertTrue($result);
    }

    /**
     * @inheritdoc
     */
    public function testGetApiUrl()
    {
        $urlTest = Bolt_Boltpay_Helper_Api::API_URL_TEST;
        $this->app->getStore()->setConfig('payment/boltpay/test', true);

        $result = $this->currentMock->getApiUrl();

        $this->assertEquals($urlTest, $result);
    }

    /**
     * @inheritdoc
     */
    public function testGetApiUrlProductionMode()
    {
        $urlProd = Bolt_Boltpay_Helper_Api::API_URL_PROD;
        $this->app->getStore()->setConfig('payment/boltpay/test', false);

        $result = $this->currentMock->getApiUrl();

        $this->assertEquals($urlProd, $result);
    }

    /**
     * @inheritdoc
     */
    public function testBuildCart()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $_quote = $cart->getQuote();
        $_items = $_quote->getAllItems();
        $_multipage = true;
        $item = $_items[0];
        $product = $item->getProduct();

        $expected = array (
          'order_reference' => $_quote->getId(),
          'display_id' => NULL,
          'items' =>
          array (
            0 =>
            array (
              'reference' => $_quote->getId(),
              'image_url' => 'http://62e4a9ae.ngrok.io/media/catalog/product/',
              'name' => $item->getName(),
              'sku' => $item->getSku(),
              'description' => substr($product->getDescription(), 0, 8182) ?: '',
              'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
              'unit_price' => round($item->getCalculationPrice() * 100),
              'quantity' => $item->getQty(),
            ),
          ),
          'currency' => $_quote->getQuoteCurrencyCode(),
          'discounts' => array (),
          'total_amount' => round($_quote->getSubtotal() * 100),
        );

        $result = $this->currentMock->buildCart($_quote, $_items, $_multipage);

        $this->assertEquals($expected, $result);
    }

    /**
     * @inheritdoc
     */
    public function testStoreHasAllCartItems()
    {
        $this->testHelper = new Bolt_Boltpay_TestHelper();
        $cart = $this->testHelper->addProduct(self::$productId, 2);

        $result = $this->currentMock->storeHasAllCartItems($cart->getQuote());

        $this->assertTrue($result);
    }

    /**
     * @inheritdoc
     */
    public function testIsResponseError()
    {
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertFalse($result);
    }

    /**
     * @inheritdoc
     */
    public function testIsResponseErrorWithErrors()
    {
        $this->testBoltResponse->errors = ['some_error_key' => 'some_error_message'];
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }

    /**
     * @inheritdoc
     */
    public function testIsResponseErrorWithErrorCode()
    {
        $this->testBoltResponse->error_code = 10603;
        $response = (object) $this->testBoltResponse;

        $result = $this->currentMock->isResponseError($response);

        $this->assertTrue($result);
    }
}
