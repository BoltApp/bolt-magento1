//////////////////////////////////////////////////
// overload prepareParams from sales.js so that
// shipping address data is available on server
// for Bolt order creation
//////////////////////////////////////////////////
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
        var data = this.serializeData('order-billing_method');
        if (data) {
            data.each(function(value) {
                params[value[0]] = value[1];
            });
        }

        /*
         *  this.serializeData Magento implemented approach, (i.e. prototype), is not reliably
         *  serializing this.shippingAddressContainer, so we will do it the more straight-forward,
         *  robust way.
         */
        var address_data = $(this.shippingAddressContainer).select('input', 'select', 'textarea');
        if (address_data) {
            address_data.each(function(form_element) {
                params[form_element.name] = form_element.value;
            });
        }
        var email = document.getElementById('email');

        if (email) {
            params['order[account][email]'] = email.value;
        } else {
            params['order[account][email]'] = '';
        }

        return params;
    }
;
//////////////////////////////////////////////////
