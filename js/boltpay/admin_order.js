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
        var address_data = this.serializeData(this.shippingAddressContainer);
        if (address_data) {
            address_data.each(function(value) {
                params[value[0]] = value[1];
            });
        }
        return params;
    }
;
//////////////////////////////////////////////////
