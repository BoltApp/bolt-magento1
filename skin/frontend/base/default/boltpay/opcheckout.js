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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

(function () {
    ShippingMethod.prototype.nextStep = function (transport) {
        if (transport && transport.responseText){
            try{
                response = eval('(' + transport.responseText + ')');
            }
            catch (e) {
                response = {};
            }
        }

        if (response.error) {
            alert(response.message);
            return false;
        }

        if (response.update_section) {
            $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
        }

        payment.initWhatIsCvvListeners();

        if (response.goto_section) {
            checkout.gotoSection(response.goto_section, true);

            if (response.goto_section == 'review') {
                checkout.reloadProgressBlock('payment');
                checkout.reloadProgressBlock();
            }

            return;
        }

        if (response.payment_methods_html) {
            $('checkout-payment-method-load').update(response.payment_methods_html);
        }

        checkout.setShippingMethod();
    };

    Review.prototype.initialize = function (saveUrl, successUrl, agreementsForm, cartUrl) {
        this.nextUrl = cartUrl;
        this.saveUrl = saveUrl;
        this.successUrl = successUrl;
        this.agreementsForm = agreementsForm;
        this.onSave = this.nextStep.bindAsEventListener(this);
    };

    Review.prototype.save = function (callback) {
        var params = Form.serialize("co-payment-form");
        this.callback = callback;
        if (this.agreementsForm) {
            params += '&'+Form.serialize(this.agreementsForm);
        }

        params.save = true;
        var request = new Ajax.Request(
            this.saveUrl,
            {
                method:'post',
                parameters:params,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
    };

    Review.prototype.redirect = function () {
        if(this.nextUrl != false) {
            window.location = this.nextUrl;
        }
    };

    Review.prototype.nextStep = function (transport) {
        if (transport && transport.responseText) {
            try{
                response = eval('(' + transport.responseText + ')');
            }
            catch (e) {
                response = {};
            }

            if (response.redirect) {
                this.isSuccess = true;
                this.nextUrl = response.redirect;
                return;
            }

            if (response.success) {
                this.isSuccess = true;
                this.nextUrl=this.successUrl;
            }
            else{
                var msg = response.error_messages;
                if (typeof(msg)=='object') {
                    msg = msg.join("\n");
                }

                if (msg) {
                    alert(msg);
                }
            }

            if (response.update_section) {
                $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
            }

            if (response.goto_section) {
                checkout.gotoSection(response.goto_section, true);
            }

            if (this.callback) {
                this.callback();
            } else if (this.nextUrl) {
                window.location=this.nextUrl;
            }
        }
    };

    if (shippingMethod) {
        shippingMethod.onSave = shippingMethod.nextStep.bindAsEventListener(shippingMethod);
    }
})();

if (!payment) {
    var payment = new Payment('co-payment-form');
}

if (!quoteBaseGrandTotal) {
    var quoteBaseGrandTotal = 0;
}

if (!lastPrice) {
    var lastPrice = false;
}

payment.addAfterInitFunction(
    'boltpay', function () {
    jQuery("#p_method_boltpay").trigger('click');
    }
);
