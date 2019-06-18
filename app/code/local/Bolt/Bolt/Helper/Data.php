<?php

class Bolt_Bolt_Helper_Data extends Bolt_Boltpay_Helper_Data
{
    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($successCustom, $checkoutType)
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        if($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) {
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
        }elseif($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE) {
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
