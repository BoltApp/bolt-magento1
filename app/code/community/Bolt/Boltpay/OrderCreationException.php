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

/**
 * Class Bolt_Boltpay_OrderCreationException
 *
 * This exception is thrown when an error occurs while trying to create a backend order
 */
class Bolt_Boltpay_OrderCreationException extends Bolt_Boltpay_BoltException
{

    /**
     * Database error or error in incoming request
     * e.g. {'reason' => 'Invalid HMAC header'}
     */
    const E_BOLT_GENERAL_ERROR               = 2001001;

    /**
     * The order already exist in the system
     *
     * e.g.
     *      {
     *          'display_id' => '2012',
     *          'order_status' => 'processing',
     *      }
     */
    const E_BOLT_ORDER_ALREADY_EXISTS        = 2001002;

    /**
     * General non-item cart changes
     *
     * e.g.
     *     {
     *         'reason' => 'cart does not exist with reference',
     *         'reference' => ‘BLT1202831’’
     *     }
     *
     *     {
     *         'reason' => 'cart is empty'
     *     }
     *
     *     {
     *         'reason' => 'The product  is not purchasable',
     *         'product_id' => '1123'
     *     }
     *
     *     {
     *         'reason' => 'Shipping total is changed',
     *         'old_value' => '1025',
     *         'new_value' => '1100'
     *     }
     *
     *     {
     *         'reason' => 'Discount total is changed',
     *         'old_value' => '1025',
     *         'new_value' => '1100'
     *     }
     *
     *     {
     *         'reason' => 'Tax amount is changed',
     *         'old_value' => '1025',
     *         'new_value' => '100'
     *     }
     *
     *     {
     *         'reason' => 'Shipping tax amount is changed',
     *         'old_value' => '1025',
     *         'new_value' => '1100'
     *     }
     *
     *     //if the contents of the cart has changed
     *     //For now we don't detail what is change
     *     {
     *         'reason' => 'cart has expired'
     *     }
     */
    const E_BOLT_CART_HAS_EXPIRED            = 2001003;

    /**
     * Item price changes
     *
     * e.g.
     *     {
     *         'product_id' => '1123',
     *         'new_price' => 1000,
     *         'old_price' => 990
     *     }
     */
    const E_BOLT_ITEM_PRICE_HAS_BEEN_UPDATED = 2001004;

    /**
     * Items have become out of stock without back-orders enabled
     *
     * e.g.
     *     {
     *         'product_id' => '1123',
     *         'available_quantity' => 2,
     *         'needed_quantity' => 3
     *     }
     */
    const E_BOLT_OUT_OF_INVENTORY            = 2001005;

    /**
     * Error applying discount to the cart
     *
     * e.g.
     *     {
     *         'reason' => 'Discount amount is changed',
     *         'discount_code' => 'code',
     *         'old_value' => 200,
     *         'new_value' => 100
     *     }
     *
     *     {
     *         'reason' => 'This coupon has expired',
     *         'discount_code' => 'code'
     *     }
     *
     *     {
     *         'reason' => 'Order does not meet criteria',
     *         'discount_code' => 'code'
     *     }
     */
    const E_BOLT_DISCOUNT_CANNOT_APPLY       = 2001006;

    /**
     * Invalid discount code used
     *
     * e.g.
     *     {
     *         'discount_code' => 'coupon_code'
     *     }
     */
    const E_BOLT_DISCOUNT_DOES_NOT_EXIST     = 2001007;

    /**
     * @var string The json to be returned to Bolt associated with this exception
     */
    protected $json;

    /**
     * Bolt_Boltpay_InvalidTransitionException constructor.
     *
     * @param string $json         		 JSON to be returned to Bolt
     * @param string $message           The exception message to throw.
     * @param int $code                 The Bolt
     * defined exception code.
     * @param Throwable|null $previous  [optional] The previously throwable used for exception chaining.
     */
    public function __construct($json = null, $message = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (empty($json)) {
            $this->json = $this->helper->__( 'Unable to create order.' );
        }

        if (empty($message)) {
            $this->message = $this->helper->__( 'Unable to create order.' );
        }
    }

}