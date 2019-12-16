<?php

require_once 'Bolt/Boltpay/controllers/ProductpageController.php';
require_once 'StreamHelper.php';
require_once 'ProductProvider.php';
require_once 'TestHelper.php';

/**
 * @coversDefaultClass Bolt_Boltpay_ProductpageController
 * @group failing
 */
class Bolt_Boltpay_ProductpageControllerTest extends PHPUnit_Framework_TestCase
{
    /** @var string Dummy HMAC */
    const TEST_HMAC = 'fdd6zQftGT36/tGRItDZ0oB48VSptxj6TpZImLy4aZ4=';

    /** @var string Dummy transaction reference */
    const REFERENCE = 'TEST-BOLT-TRNX';

    /**
     * @var int Dummy product id
     */
    private static $productId;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_ProductpageController Mocked instance of the class being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mage_Core_Controller_Response_Http Mocked instance of response object
     */
    private $response;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $helperMock;

    /**
     * Clear registry data from previous tests and create dummy product
     */
    public static function setUpBeforeClass()
    {
        $checkoutQuote = Mage::getSingleton('checkout/cart')->getQuote();
        //delete immutable quote
        Mage::getModel('sales/quote')->load($checkoutQuote->getId(), 'parent_quote_id')->delete();
        $checkoutQuote->delete();
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            null
        );
        unset($_SERVER['HTTP_X_BOLT_HMAC_SHA256']);
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/checkout/cart');
        Mage::unregister('_singleton/sales/quote');
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1', array(), 20);
    }

    /**
     * Setup test dependencies, called before each test
     */
    protected function setUp()
    {
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_ProductpageController')
            ->setMethods(array('getResponse'))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();

        $this->response = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader', 'setBody', 'setHttpResponseCode'))
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('verify_hook', 'setResponseContextHeaders', 'notifyException', 'logException'))
            ->getMock();

        Mage::register('_helper/boltpay', $this->helperMock);

        $this->currentMock->method('getResponse')->willReturn($this->response);
    }

    /**
     * Delete dummy product after tests are done
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
    }

    /**
     * Reset Magento registry values and session quote to prevent conflicts with other tests
     */
    protected function tearDown()
    {
        Bolt_Boltpay_StreamHelper::restore();
        $checkoutQuote = Mage::getSingleton('checkout/cart')->getQuote();
        //delete immutable quote
        Mage::getModel('sales/quote')->load($checkoutQuote->getId(), 'parent_quote_id')->delete();
        $checkoutQuote->delete();
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            Mage::getSingleton('checkout/session'),
            '_quote',
            null
        );
        Mage::unregister('_helper/boltpay');
        Mage::unregister('_singleton/checkout/cart');
        Mage::unregister('_singleton/sales/quote');
        unset($_SERVER['HTTP_X_BOLT_HMAC_SHA256']);
    }

    /**
     * @test
     * that createCartAction when called with sufficient data will create quote and return its data in JSON format
     *
     * @covers ::createCartAction
     * @covers ::sendResponse
     */
    public function createCartAction_withSufficientData_returnsSuccessResponseWithCartDataInJSON()
    {
        $payload = array('items' => array(array('reference' => self::$productId, 'quantity' => 1)));
        $jsonPayload = json_encode($payload);
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);

        $this->helperMock->expects($this->once())->method('verify_hook')->with($jsonPayload, self::TEST_HMAC)
            ->willReturn(true);

        $this->expectsResponse(
            200,
            function ($body) use ($product) {
                $result = json_decode($body, true);
                $this->assertEquals(JSON_ERROR_NONE, json_last_error());
                $this->assertArraySubset(
                    array(
                        'status' => 'success',
                        'cart'   => array(
                            'items' => array(
                                array(
                                    'quantity'     => 1,
                                    'name'         => $product->getName(),
                                    'sku'          => $product->getSku(),
                                    'unit_price'   => 1000,
                                    'total_amount' => 1000,
                                )
                            )
                        )
                    ),
                    $result
                );
            }
        );

        Bolt_Boltpay_StreamHelper::setData($jsonPayload);
        Bolt_Boltpay_StreamHelper::register();

        $this->currentMock->createCartAction();
    }

    /**
     * @test
     * that createCartAction when requested with invalid HMAC will return error response with appropriate message
     *
     * @covers ::createCartAction
     * @covers ::sendResponse
     */
    public function createCartAction_withInvalidHMACParameter_returnsErrorResponseWithErrorMessage()
    {
        $jsonPayload = json_encode(array());
        $_SERVER['HTTP_X_BOLT_HMAC_SHA256'] = self::TEST_HMAC;

        $this->helperMock->expects($this->once())->method('verify_hook')->with($jsonPayload, self::TEST_HMAC)
            ->willReturn(false);

        $this->expectsErrorResponse('Failed HMAC Authentication');
        Bolt_Boltpay_StreamHelper::setData($jsonPayload);
        Bolt_Boltpay_StreamHelper::register();

        $this->currentMock->createCartAction();
    }

    /**
     * Configure response mock to expect error response with specific message
     *
     * @param string $message Error message to expect in body
     */
    private function expectsErrorResponse($message)
    {
        $this->expectsResponse(
            422,
            array(
                'status' => 'failure',
                'error'  =>
                    array(
                        'code'    => 6009,
                        'message' => $message
                    )
            )
        );
    }

    /**
     * Configures response and helper mock to expect response with provided HTTP code and body
     *
     * @param int            $httpCode HTTP code to expect in response
     * @param array|callable $body array to expect as body in JSON format or callback used to validate body content
     */
    private function expectsResponse($httpCode, $body)
    {
        $this->helperMock->expects($this->once())->method('setResponseContextHeaders');
        $this->response->expects($this->once())->method('setHeader')->with('Content-type', 'application/json');
        $this->response->expects($this->once())->method('setHttpResponseCode')->with($httpCode);
        if (is_callable($body)) {
            $this->response->expects($this->once())->method('setBody')->willReturnCallback($body);
        } else {
            $this->response->expects($this->once())->method('setBody')->with(json_encode($body));
        }
    }
}