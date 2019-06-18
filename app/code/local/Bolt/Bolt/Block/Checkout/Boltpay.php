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
 * Class Bolt_Boltpay_Block_Checkout_Boltpay
 *
 * This block is used in boltpay/track.phtml and boltpay/replace.phtml templates
 *
 * This is defined in boltpay.xml config file
 *
 * The purpose is to add the Bolt tracking javascript files to every page, Bolt connect javascript to order and product pages,
 * create the order on Bolt side and set up the javascript BoltCheckout.configure process with cart and hint data.
 *
 */
class Bolt_Bolt_Block_Checkout_Boltpay extends Bolt_Boltpay_Block_Checkout_Boltpay
{

    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($successCustom = '', $checkoutType)
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        if($checkoutType === self::CHECKOUT_TYPE_ADMIN) {
            return "function(transaction, callback) {
                $successCustom

                var input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', 'bolt_reference');
                input.setAttribute('value', transaction.reference);
                document.getElementById('edit_form').appendChild(input);

                // order and order.submit should exist for admin
                if ((typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                    order_completed = true;
                    callback();
                }
            }";
        }elseif($checkoutType === self::CHECKOUT_TYPE_MULTI_PAGE) {
            return "function(transaction, callback) {
                new Ajax.Request(
                    '$saveOrderUrl',
                    {
                        method:'post',
                        onSuccess:
                            function() {
                                $successCustom
                                order_completed = true;
                                callback();
                            },
                        parameters: 'reference='+transaction.reference
                    }
                );
            }";
        }else {
            return "function(transaction, callback) {
                var comments = document.getElementById('customerordercomments').value;
                new Ajax.Request(
                    '$saveOrderUrl',
                    {
                        method:'post',
                        onSuccess:
                            function() {
                                $successCustom
                                order_completed = true;
                                callback();
                            },
                        parameters: 'reference='+transaction.reference+'&comments='+comments
                    }
                );
            }";
        }
    }
}
