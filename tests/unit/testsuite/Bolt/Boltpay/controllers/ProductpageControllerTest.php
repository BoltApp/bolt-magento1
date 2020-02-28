<?php

require_once 'Bolt/Boltpay/controllers/ProductpageController.php';
require_once 'ProductProvider.php';
require_once 'MockingTrait.php';
require_once 'TestHelper.php';

/**
 * @coversDefaultClass Bolt_Boltpay_ProductpageController
 */
class Bolt_Boltpay_ProductpageControllerTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Dummy HMAC */
    const TEST_HMAC = 'fdd6zQftGT36/tGRItDZ0oB48VSptxj6TpZImLy4aZ4=';

    /** @var string Dummy transaction reference */
    const REFERENCE = 'TEST-BOLT-TRNX';

    /** @var string Name of the class tested */
    protected $testClassName = 'Bolt_Boltpay_ProductpageController';

    /**
     * @var int Dummy product id
     */
    private static $productId;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_ProductpageController Mocked instance of the class being tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_Data Mocked instance of Bolt helper
     */
    private $helperMock;

    /**
     * Clear registry data from previous tests and create dummy product
     */
    public static function setUpBeforeClass()
    {
        Bolt_Boltpay_TestHelper::clearRegistry();
        self::$productId = Bolt_Boltpay_ProductProvider::createDummyProduct('PHPUNIT_TEST_1', array(), 20);
    }

    /**
     * Setup test dependencies, called before each test
     *
     * @throws Mage_Core_Exception if unable to stub helper
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()
            ->setMethods(array('getResponse', 'sendResponse', 'getRequestData'))
            ->getMock();

        $this->helperMock = $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods(array('verify_hook', 'setResponseContextHeaders', 'notifyException', 'logException'))
            ->getMock();

        Bolt_Boltpay_TestHelper::stubHelper('boltpay', $this->helperMock);
    }

    /**
     * Delete dummy product after tests are done
     */
    public static function tearDownAfterClass()
    {
        Bolt_Boltpay_ProductProvider::deleteDummyProduct(self::$productId);
        Bolt_Boltpay_TestHelper::clearRegistry();
    }

    /**
     * @test
     * that createCartAction when called with sufficient data will create quote and return its data by using
     * @see Bolt_Boltpay_Controller_Traits_WebHookTrait::sendResponse
     *
     * @covers ::createCartAction
     */
    public function createCartAction_withSufficientData_returnsSuccessResponseWithCartDataInJSON()
    {
        $payload = (object)array('items' => array((object)array('reference' => self::$productId, 'quantity' => 1)));
        $this->currentMock->expects($this->once())->method('getRequestData')->willReturn($payload);

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load(self::$productId);

        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                200,
                new PHPUnit_Framework_Constraint_ArraySubset(
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
                    )
                )
            );

        $this->currentMock->createCartAction();
    }

    /**
     * @test
     * that createCartAction returns response with 422 code and exception message if one occurs during data generation
     *
     * @covers ::createCartAction
     *
     * @throws ReflectionException if unable to stub model
     */
    public function createCartAction_ifUnexpectedExceptionOccurs_returns422ResponseContainingMessage()
    {
        $productPageCartMock = $this->getClassPrototype('Bolt_Boltpay_Model_Productpage_Cart')
            ->setMethods(array())->getMock();
        $exception = new Exception('Unexpected exception');
        $productPageCartMock->expects($this->once())->method('generateData')->willThrowException($exception);
        Bolt_Boltpay_TestHelper::stubModel('boltpay/productpage_cart', $productPageCartMock);
        $this->currentMock->expects($this->once())->method('sendResponse')
            ->with(
                422,
                array(
                    'status' => 'failure',
                    'error'  =>
                        array(
                            'code'    => 6009,
                            'message' => $exception->getMessage()
                        )
                )
            );
        $this->currentMock->createCartAction();
    }
}