<?php

use Bolt_Boltpay_Controller_Interface as RESPONSE_CODE;

require_once('TestHelper.php');

/**
 * Class Bolt_Boltpay_OrderCreationExceptionTest
 */
class Bolt_Boltpay_OrderCreationExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test JSON response for general error with default parameters
     */
    public function testGeneralExceptionJson()
    {
        $reason = 'test error';
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
            [$reason]
        );

        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertNotEmpty($exception->status);
        $this->assertExceptionProperties($exception);
        $this->assertNotEmpty($exception->error[0]->data[0]->reason);

        $this->assertEquals(Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR, $exception->error[0]->code);
        $this->assertEquals($reason, $exception->error[0]->data[0]->reason);

        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct data for existing order exception
     */
    public function testExistingCartExceptionJson()
    {
        $dataValues = ['id_123', 'pending'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_ORDER_ALREADY_EXISTS,
            Bolt_Boltpay_OrderCreationException::E_BOLT_ORDER_ALREADY_EXISTS_TMPL,
            $dataValues);
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->display_id);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->order_status);

        $this->assertEquals(RESPONSE_CODE::HTTP_CONFLICT, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct data for various cart exceptions
     */
    public function testCartExceptionJson()
    {
        // Empty cart
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_EMPTY
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Cart is empty', $exception->error[0]->data[0]->reason);
        $this->assertEquals(RESPONSE_CODE::HTTP_GONE, $boltOrderCreationException->getHttpCode());

        // Expired cart
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Cart has expired', $exception->error[0]->data[0]->reason);
        $this->assertEquals(RESPONSE_CODE::HTTP_GONE, $boltOrderCreationException->getHttpCode());

        // Cart does not exist
        $dataValues = ['cart_reference_001'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_FOUND,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Cart does not exist with reference', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->reference);
        $this->assertEquals(RESPONSE_CODE::HTTP_NOT_FOUND, $boltOrderCreationException->getHttpCode());

        // Cart is not purchasable
        $dataValues = [905];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_PURCHASABLE,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('The product is not purchasable', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->product_id);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());

        // Cart grand total has changed
        $dataValues = [100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_GRAND_TOTAL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Grand total has changed', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->old_value);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->new_value);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());

        // Cart discount total has changed
        $dataValues = [100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_DISCOUNT,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Discount total has changed', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->old_value);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->new_value);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());

        // Chart tax has changed
        $dataValues = [100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_CART_HAS_EXPIRED_TMPL_TAX,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Tax amount has changed', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->old_value);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->new_value);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct data for cart items exception
     */
    public function testItemPriceException()
    {
        $dataValues = [905, 100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED_TMPL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->product_id);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->old_price);
        $this->assertEquals($dataValues[2], $exception->error[0]->data[0]->new_price);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct data for cart inventory exception
     */
    public function testCartInventoryException()
    {
        $dataValues = [905, 100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_OUT_OF_INVENTORY,
            Bolt_Boltpay_OrderCreationException::E_BOLT_OUT_OF_INVENTORY_TMPL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->product_id);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->available_quantity);
        $this->assertEquals($dataValues[2], $exception->error[0]->data[0]->needed_quantity);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct exception data when discount can not be applied
     */
    public function testDiscountCanNotBeAppliedException()
    {
        // Generic exception
        $dataValues = ['Discount code too cool to be used', 'FREE_BEER_FOR_LIFE'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_CANNOT_APPLY,
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_GENERIC,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->discount_code);

        // Discount code expired exception
        $dataValues = ['FREE_BEER_FOR_1_SEC'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_CANNOT_APPLY,
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_EXPIRED,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('This coupon has expired', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->discount_code);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct exception data when discount code is invalid
     */
    public function testInvalidDiscountCodeException()
    {
        $dataValues = ['GIVE_ME_FREE'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_DOES_NOT_EXIST,
            Bolt_Boltpay_OrderCreationException::E_BOLT_DISCOUNT_DOES_NOT_EXIST_TMPL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->discount_code);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test for correct exception when shipping price or tax are changed
     */
    public function testShippingPriceOrTaxChangedException()
    {
        $dataValues = [100, 150];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED_TMPL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Shipping total has changed', $exception->error[0]->data[0]->reason);
        $this->assertEquals($dataValues[0], $exception->error[0]->data[0]->old_value);
        $this->assertEquals($dataValues[1], $exception->error[0]->data[0]->new_value);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * In case when we pass string data instead of expected numbers, values in the response will be set to zero
     */
    public function testIncorrectDataJson()
    {
        $dataValues = ['hundred', 'hundred and fifty'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED,
            Bolt_Boltpay_OrderCreationException::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED_TMPL,
            $dataValues
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals('Shipping total has changed', $exception->error[0]->data[0]->reason);
        $this->assertNotEquals($dataValues[0], $exception->error[0]->data[0]->old_value);
        $this->assertNotEquals($dataValues[1], $exception->error[0]->data[0]->new_value);
        $this->assertEquals(0, $exception->error[0]->data[0]->old_value);
        $this->assertEquals(0, $exception->error[0]->data[0]->new_value);
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test if OrderCreationException is keeping a reference to previous error
     */
    public function testExceptionWithPreviousException()
    {
        $reason = 'test with previous error';
        $previousException = new Exception('This is previous error');
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
            [$reason],
            $reason,
            $previousException
        );

        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertNotEmpty($exception->status);
        $this->assertExceptionProperties($exception);
        $this->assertNotEmpty($exception->error[0]->data[0]->reason);

        $this->assertEquals(Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR, $exception->error[0]->code);
        $this->assertEquals($reason, $exception->error[0]->data[0]->reason);
        $this->assertEquals($previousException, $boltOrderCreationException->getPrevious());
        $this->assertEquals('This is previous error', $boltOrderCreationException->getPrevious()->getMessage());
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Test if special characters are escaped in JSON response
     */
    public function testEscapeSpecialCharacters()
    {
        $reason = 'test "error" \path\example';
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
            [$reason]
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($reason, $exception->error[0]->data[0]->reason);

        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * If non-registered code is passed to a constructor it should generate generic exception
     */
    public function testNonRegisteredErrorCode()
    {
        $reason = 'test non-existing code';
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            12345,
            Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
            [$reason],
            $reason
        );
        $exceptionJson = $boltOrderCreationException->getJson();
        $exception = json_decode($exceptionJson);

        $this->assertExceptionProperties($exception);
        $this->assertEquals($reason, $exception->error[0]->data[0]->reason);

        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Testing HTTP response code for general exception with default parameters
     */
    public function testGeneralExceptionHttpCode()
    {
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException();
        $this->assertEquals(RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Testing HTTP response code for general exception with default parameters
     */
    public function testExistingCartExceptionHttpCode()
    {
        $dataValues = ['id_123', 'pending'];
        $boltOrderCreationException = new Bolt_Boltpay_OrderCreationException(
            Bolt_Boltpay_OrderCreationException::E_BOLT_ORDER_ALREADY_EXISTS,
            Bolt_Boltpay_OrderCreationException::E_BOLT_ORDER_ALREADY_EXISTS_TMPL,
            $dataValues);
        $this->assertEquals(RESPONSE_CODE::HTTP_CONFLICT, $boltOrderCreationException->getHttpCode());
    }

    /**
     * Helper function for asserting that all required params exist in the exception instance
     *
     * @param Throwable $exception The exception instance we are testing
     */
    private function assertExceptionProperties($exception)
    {
        $this->assertNotEmpty($exception->error);
        $this->assertNotEmpty($exception->error[0]->code);
        $this->assertNotEmpty($exception->error[0]->data);
        $this->assertEquals('failure', $exception->status);
    }
}