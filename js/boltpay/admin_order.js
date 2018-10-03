//////////////////////////////////////////////////
// overload prepareParams from sales.js so that
// shipping address data is available on server
// for Bolt order creation
//////////////////////////////////////////////////

var addBillingToPrepareParams = false;

AdminOrder.prototype.prepareParams =
    function(params){
        if (!params) {
            params = {};
        }
        if (!params.customer_id) {
            params.customer_id = this.customerId;
        }
        if (!params.store_id) {
            params.store_id = this.storeId;
        }
        if (!params.currency_id) {
            params.currency_id = this.currencyId;
        }
        if (!params.form_key) {
            params.form_key = FORM_KEY;
        }

        var billingMethodContainer = $('order-billing_method');
        if (billingMethodContainer) {
            var data = this.serializeData('order-billing_method');
            if (data) {
                data.each(function(value) {
                    params[value[0]] = value[1];
                });
            }
        }

        var shippingMethodContainer = $('order-shipping_method');
        if (shippingMethodContainer) {
            var data = this.serializeData('order-shipping_method');
            if (data) {
                data.each(function(value) {
                    params[value[0]] = value[1];
                });
            }
        }

        /*
         *  this.serializeData Magento implemented approach, (i.e. prototype), is not reliably
         *  serializing this.shippingAddressContainer, and this.billingAddressContainer so we will
         *  do it the more straight-forward, robust way.
         */
        if (this.shippingAddressContainer) {
            var shipping_address_data = $(this.shippingAddressContainer).select('input', 'select', 'textarea');
            if (shipping_address_data) {
                shipping_address_data.each(function(form_element) {
                    params[form_element.name] = form_element.value;
                });
            }
        }

        if (addBillingToPrepareParams) {
            var billing_address_data = $(this.billingAddressContainer).select('input', 'select', 'textarea');
            if (billing_address_data) {
                billing_address_data.each(function(form_element) {
                    params[form_element.name] = form_element.value;
                });
            }
        }

        var email = document.getElementById('email');

        if (email && email.value) {
            params['order[account][email]'] = email.value;
            addBillingToPrepareParams = true;
        }

        return (typeof this.customPrepareParams == 'function') ? this.customPrepareParams(params) : params;
    }
;
//////////////////////////////////////////////////

// Require email in admin
var intervalId = setInterval(
    function() {
        var email = document.getElementById('email');

        if (email) {
            email.classList.add('required-entry');
            clearInterval(intervalId);
        }
    },500
);