
var Bolt_MiniCart = {
    options: {
        value: 0,
        wrapperID: '',
        loaderClass: 'bolt-minicart-loading',
        publishableKey: '',
        connectScript: '',
        checkoutCartActionUrl: '',
    },
    boltCart: {
        'orderToken' : '',
        'authcapture': false
    },
    _loader: '',
    _wrapper: '',
    isCartDataLoaded: false,
    initialize: function (params) {
        console.log('>>> Initialize the Bolt_MiniCart <<<');

        this.options = Object.assign(this.options, params);

        this._create();

    },
    _create: function() {
        console.log('# _create method.');
        this.options.value = 50;

        this._loader = document.getElementById(this.options.loaderClass);
        this._wrapper = document.getElementById(this.options.wrapperID);

        this._insertConnectScript();
    },
    _insertConnectScript: function() {
        console.log('# _insertConnectScript method.');
        let scriptTag = document.getElementById('bolt-connect'),
            publishableKey = this.options.publishableKey,
            self = this;

        this.enableLoader();

        setTimeout(function() {
            console.log('### _insertConnectScript');

            self.hideDefaultCheckoutButtons();

            if (scriptTag) {
                // scriptTag.setAttribute('data-publishable-key', publishableKey);
                self.disableLoader();
                self.hideDefaultCheckoutButtons();

                console.log('### _insertConnectScript - script was FOUND!!!');

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
    _destroy: function() {

    },
    refresh: function() {
        console.log('# refresh method.');
        this.getBoltCartResponse();
    },
    enableLoader: function() {
        this._loader.classList.remove('bolt-loader-disabled');
    },
    disableLoader: function() {
        this._loader.classList.add('bolt-loader-disabled');
    },
    configureBoltButton: function() {
        let self = this,
            hints = {},
            callbacks = {
                check: function() {
                    console.log('### Check before open Modal');
                    return !!self.boltCart.orderToken;
                },
                success: function(transaction, callback) {
                    // This function is called when the Bolt checkout transaction is successful.
                    console.log('# configureBoltButton:success callback method');
                    // **IMPORTANT** callback must be executed at the end of this function if `success`
                    // is defined.
                    callback();
                },

                close: function() {
                    // This function is called when the Bolt checkout modal is closed.
                }
            };

        BoltCheckout.configure(this.boltCart, hints, callbacks);
    },
    hideDefaultCheckoutButtons: function() {
        let replacementSelectors = this.options.replacementButtonSelectors;

        console.log('# hideDefaultCheckoutButtons method');
        if (replacementSelectors.length) {
            replacementSelectors.map(function(currentValue, index) {
                let elm = document.querySelector(currentValue);

                if (elm) {
                    console.log(elm);
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
                method:'post',
                onSuccess:
                    function(response) {

                        if (response.status === 200) {
                            let result = response.responseJSON;

                            Object.assign(self.boltCart, result.cart_data.boltCart);

                            self.isCartDataLoaded = true;

                            self.configureBoltButton();

                            self.disableLoader();
                        }
                    },
            }
        );
    }
};
