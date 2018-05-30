
var Bolt_MiniCart = {
    options: {
        wrapperID: '',
        loaderClass: 'bolt-minicart-loading',
        publishableKey: '',
        connectScript: '',
        checkoutCartActionUrl: '',
        successOrderUrl: ''
    },
    boltCart: {
        'orderToken': '',
        'authcapture': false
    },
    _loader: '',
    _wrapper: '',
    isCartDataLoaded: false,
    isOrderCompleted: false,
    initialize: function (params) {

        this.options = Object.assign(this.options, params);

        this._create();

    },
    _create: function () {
        this._loader = document.getElementById(this.options.loaderClass);
        this._wrapper = document.getElementById(this.options.wrapperID);

        this._insertConnectScript();
    },
    _insertConnectScript: function () {
        let scriptTag = document.getElementById('bolt-connect'),
            publishableKey = this.options.publishableKey,
            self = this;

        this.enableLoader();

        setTimeout(function () {
            self.hideDefaultCheckoutButtons();

            if (scriptTag) {
                // scriptTag.setAttribute('data-publishable-key', publishableKey);
                self.disableLoader();
                self.hideDefaultCheckoutButtons();

                return;
            }
            if (!scriptTag) {
                scriptTag = document.createElement('script');
                scriptTag.setAttribute('type', 'text/javascript');
                scriptTag.setAttribute('async', '');
                scriptTag.setAttribute('src', self.options.connectScript);
                scriptTag.setAttribute('id', 'bolt-connect');
                scriptTag.setAttribute('data-publishable-key', publishableKey);
                scriptTag.onload = self.refresh();

                document.head.appendChild(scriptTag);
            }
        }, 1000);
    },
    _destroy: function () {
    },
    refresh: function () {
        this.getBoltCartResponse();
    },
    enableLoader: function () {
        this._loader.classList.remove('bolt-loader-disabled');
    },
    disableLoader: function () {
        this._loader.classList.add('bolt-loader-disabled');
    },
    getBoltCallbacksConfig: function (cartData) {
        let self = this;

        debugger;
        return {
            check: function () {
                if (cartData.callbacks.hasOwnProperty('check')) {
                    cartData.callbacks.check();
                }
                return !!self.boltCart.orderToken;
            },
            success: function (transaction, callback) {
                // This function is called when the Bolt checkout transaction is successful.

                self.isOrderCompleted = true;
                // **IMPORTANT** callback must be executed at the end of this function if `success`
                // is defined.
                callback();
            },
            close: function () {
                // This function is called when the Bolt checkout modal is closed.
                // debugger;
                // if (typeof bolt_checkout_close === 'function') {
                    // used internally to set overlay in firecheckout
                    // bolt_checkout_close();
                // }
                if (self.isOrderCompleted) {
                    location.href = self.options.successOrderUrl;
                }
            }
        };
    },
    configureBoltButton: function (cartData) {
        let self = this
            // hints = {},
            // callbacks = this.getBoltCallbacksConfig(cartData)
        ;

        debugger;
        BoltCheckout.configure(cartData.boltCart, cartData.hintData, cartData.callbacks);
    },
    hideDefaultCheckoutButtons: function () {
        let replacementSelectors = this.options.replacementButtonSelectors;

        if (replacementSelectors.length) {
            replacementSelectors.map(function (currentValue, index) {
                let elm = document.querySelector(currentValue);

                if (elm) {
                    elm.style.display = 'none';
                }
            });
        }
    },
    getBoltCartResponse: function (callback) {
        let self = this;

        self.isCartDataLoaded = false;

        new Ajax.Request(
            self.options.checkoutCartActionUrl,
            {
                method: 'post',
                onSuccess: function (response) {
                    if (response.status === 200) {
                        let result = response.responseText,
                            resultParse = JSON.parse(result);

                        // Object.assign(self.boltCart, result.cart_data.boltCart);

                        self.isCartDataLoaded = true;

                        let callbacks = resultParse.cart_data.callbacks;
                        // console.log(response);
                        // console.log(result);
                        console.log(callbacks);
                        console.log(JSON.parse(callbacks.check));
                        // console.log(response.responseText);

                        // self.configureBoltButton(result.cart_data);

                        self.disableLoader();
                    }
                },
            }
        );
    }
};
