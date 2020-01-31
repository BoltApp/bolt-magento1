<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Bolt_Boltpay_Controller_Interface as RESPONSE_CODE;

/**
 * Class Bolt_Boltpay_OrderCreationException
 *
 * This exception is thrown when an error occurs while trying to create a backend order
 */
class Bolt_Boltpay_OrderCreationException extends Bolt_Boltpay_BoltException
{

    /**
     * Database error or error in incoming request
     */
    const E_BOLT_GENERAL_ERROR               = 2001001;
    const E_BOLT_GENERAL_ERROR_TMPL_HMAC     = '{"reason": "Invalid HMAC header"}';
    const E_BOLT_GENERAL_ERROR_TMPL_GENERIC  = '{"reason": "%s"}';

    /**
     * The order already exist in the system
     */
    const E_BOLT_ORDER_ALREADY_EXISTS        = 2001002;
    const E_BOLT_ORDER_ALREADY_EXISTS_TMPL   = '{"display_id": "%s", "order_status": "%s"}';

    /**
     * General non-item cart changes
     */
    const E_BOLT_CART_HAS_EXPIRED                         = 2001003;
    const E_BOLT_CART_HAS_EXPIRED_TMPL_EMPTY              = '{"reason": "Cart is empty"}';
    const E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED            = '{"reason": "Cart has expired"}';
    const E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_FOUND          = '{"reason": "Cart does not exist with reference", "reference": "%s"}';
    const E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_PURCHASABLE    = '{"reason": "The product is not purchasable", "product_id": "%d"}';
    const E_BOLT_CART_HAS_EXPIRED_TMPL_GRAND_TOTAL        = '{"reason": "Grand total has changed", "old_value": "%d", "new_value": "%d"}';
    const E_BOLT_CART_HAS_EXPIRED_TMPL_TAX                = '{"reason": "Tax amount has changed", "old_value": "%d", "new_value": "%d"}';

    /**
     * Currently not used -- shipping tax in M1 is used to fix rounding problems and reports the
     * full cart tax.  Therefore, checking full tax is sufficient
     */
    const E_BOLT_CART_HAS_EXPIRED_TMPL_SHIPPING_TAX       = '{"reason": "Shipping tax amount has changed", "old_value": "%d", "new_value": "%d"}';

    /**
     * Item price changes
     */
    const E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED         = 2001004;
    const E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED_TMPL    = '{"product_id": "%d", "old_price": "%d", "new_price": "%d"}';

    /**
     * Items have become out of stock without back-orders enabled
     */
    const E_BOLT_OUT_OF_INVENTORY         = 2001005;
    const E_BOLT_OUT_OF_INVENTORY_TMPL    = '{"product_id": "%d", "available_quantity": %d, "needed_quantity": %d}';

    /**
     * Error applying discount to the cart
     */
    const E_BOLT_DISCOUNT_CANNOT_APPLY                 = 2001006;
    const E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_COUPON_TOTAL_CHANGED    = '{"reason": "Discount amount has changed", "discount_code": "%s", "old_value": "%d", "new_value": "%d"}';
    const E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_TOTAL_CHANGED           = '{"reason": "Discount total has changed", "old_value": "%d", "new_value": "%d"}';
    const E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_EXPIRED                 = '{"reason": "This coupon has expired", "discount_code": "%s"}';
    const E_BOLT_DISCOUNT_CANNOT_APPLY_TMPL_GENERIC                 = '{"reason": "%s", "discount_code": "%s"}';

    /**
     * Invalid discount code used
     */
    const E_BOLT_DISCOUNT_DOES_NOT_EXIST        = 2001007;
    const E_BOLT_DISCOUNT_DOES_NOT_EXIST_TMPL   = '{"discount_code": "%s"}';

    /**
     * Change in shipping price
     */
    const E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED           = 2001008;
    const E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED_TMPL      = '{"reason": "Shipping total has changed", "old_value": "%d", "new_value": "%d"}';

    /**
     * @var int http response code that is to be returned
     */
    protected $httpCode;

    /**
     * @var string The json to be returned to Bolt associated with this exception
     */
    protected $json;

    /**
     * @var array All possible error codes supported by Bolt
     */
    protected static $validCodes = array(
        self::E_BOLT_GENERAL_ERROR,
        self::E_BOLT_ORDER_ALREADY_EXISTS,
        self::E_BOLT_CART_HAS_EXPIRED,
        self::E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED,
        self::E_BOLT_OUT_OF_INVENTORY,
        self::E_BOLT_DISCOUNT_CANNOT_APPLY,
        self::E_BOLT_DISCOUNT_DOES_NOT_EXIST,
        self::E_BOLT_SHIPPING_PRICE_HAS_BEEN_UPDATED
    );

    /**
     * Bolt_Boltpay_OrderCreationException constructor.
     *
     * @param int    $code          The Bolt-defined error code
     * @param string $dataTemplate  specific Bolt error sub-category
     * @param array  $dataValues    An array of values to be added to the data template
     * @param string $message       The exception message to throw.
     * @param Exception $previous  [optional] The previous exception/error used for exception chaining.
     *
     * @see Bolt_Boltpay_OrderCreationException::$validCodes for the supported Bolt-defined error codes
     */
    public function __construct(
        $code = self::E_BOLT_GENERAL_ERROR,
        $dataTemplate = self::E_BOLT_GENERAL_ERROR_TMPL_GENERIC,
        array $dataValues = array(),
        $message = null,
        $previous = null
    ) {
        // Throwable is PHP7 specific, so we need to check Exception explicitly here.
        if (!($previous instanceof Exception)) {
            $previous = null;
        }

        // If code is invalid, we will use the generic message
        if (!in_array($code, self::$validCodes)) {
            $originalCode = $code;
            $originalDataTemplate = $dataTemplate;
            $originalDataValues = $dataValues;
            $originalJson = $this->createJson($originalCode, $originalDataTemplate, $originalDataValues);

            $this->boltHelper()->notifyException(
                new Exception(
                    "Invalid response code specified [$code]. Default "
                    .self::E_BOLT_GENERAL_ERROR." will be used instead."
                    ."\nSupported codes: ".json_encode(self::$validCodes)
                    ."\nSupplied error:\n$originalJson"
                ),
                array(),
                'warning'
            );

            $code = self::E_BOLT_GENERAL_ERROR;
            $dataTemplate = self::E_BOLT_GENERAL_ERROR_TMPL_GENERIC;
            $dataValues = array($message."\nSupplied error:\n$originalJson");
        }

        foreach($dataValues as $index => $value ) {
            $dataValues[$index] = addcslashes($dataValues[$index], '"\\');
        }

        $this->httpCode = $this->selectHttpCode($code, $dataTemplate);
        $this->json = $this->createJson($code, $dataTemplate, $dataValues);

        if (empty($message)) {
            $message = $this->getJson();
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Finds the httpCode based on the error parameters
     *
     * @param int    $code         Bolt-defined error code
     * @param string $dataTemplate specific Bolt error sub-category
     *
     * @see Bolt_Boltpay_OrderCreationException::$validCodes for the supported Bolt-defined error codes
     *
     * @return int The HTTP code that was found which matches the provided error info
     */
    public function selectHttpCode( $code, $dataTemplate )
    {
        // Select the http code
        switch ($code) {
            case self::E_BOLT_ORDER_ALREADY_EXISTS:
                return RESPONSE_CODE::HTTP_CONFLICT; // 409
            case self::E_BOLT_CART_HAS_EXPIRED:
                switch ($dataTemplate) {
                    case self::E_BOLT_CART_HAS_EXPIRED_TMPL_NOT_FOUND:
                        return RESPONSE_CODE::HTTP_NOT_FOUND; // 404
                    case self::E_BOLT_CART_HAS_EXPIRED_TMPL_EMPTY:
                    case self::E_BOLT_CART_HAS_EXPIRED_TMPL_EXPIRED:
                        return RESPONSE_CODE::HTTP_GONE;  // 410
                }

            case self::E_BOLT_GENERAL_ERROR:
                if ($dataTemplate === self::E_BOLT_GENERAL_ERROR_TMPL_HMAC) {
                    return RESPONSE_CODE::HTTP_UNAUTHORIZED; // 401
                }

            default:
                return RESPONSE_CODE::HTTP_UNPROCESSABLE_ENTITY; // 422
        }
    }

    /**
     * Creates the JSON to be returned to the Bolt server
     *
     * @param int    $code         Bolt-defined error code
     * @param string $dataTemplate specific Bolt error sub-category
     * @param array  $dataValues   An array of values to be added to the data template
     *
     * @return string  The JSON that was created as part of this call
     */
    private function createJson( $code, $dataTemplate, array $dataValues = array() )
    {
        array_unshift($dataValues, $dataTemplate);
        $dataJson = call_user_func_array(array($this->boltHelper(), '__'), $dataValues);
        return <<<JSON
        {
            "status": "failure",
            "error": [{
                "code": $code,
                "data": [$dataJson]
            }]
        }
JSON;
    }

    /**
     * The request body to be returned for this error
     *
     * @return string   well-formatted JSON that conforms to the Bolt error code standard
     */
    public function getJson()
    {
        return $this->json;
    }

    /**
     * Returns the HTTP response code
     *
     * @return int HTTP response code defined by error type
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }
}