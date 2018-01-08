<?php
/**
 * Class Bolt_Boltpay_Block_Checkout_Boltpay
 *
 * This block is used in boltpay/track.phtml and boltpay/replace.phtml templates which is used on every page
 *
 * This is defined in boltpay.xml config file
 *
 * The purpose is to add the Bolt tracking javascript files to every page, Bolt connect javascript to order and product pages,
 * create the order on Bolt side and set up BoltConnect process with cart and hint data.
 *
 */

class Bolt_Boltpay_Block_Checkout_Boltpay
    extends Mage_Checkout_Block_Onepage_Review_Info {

    /**
     * @var string The Bolt sandbox url for the javascript
     */
    const JS_URL_TEST = 'https://connect-sandbox.bolt.com';

    /**
     * @var string The Bolt production url for the javascript
     */
    const JS_URL_PROD = 'https://connect.bolt.com';

    /**
     * @var int flag that represents if the capture is automatically done on authentication
     */
    const AUTO_CAPTURE_ENABLED = 1;


    const CSS_SUFFIX = 'bolt-css-suffix';

    /**
     * Set the connect javascript url to production or sandbox based on store config settings
     */
    public function _construct() {
        parent::_construct();
        $this->_jsUrl = Mage::getStoreConfig('payment/boltpay/test') ?
            self::JS_URL_TEST . "/connect.js":
            self::JS_URL_PROD . "/connect.js";
    }
    
    /**
     * Get the track javascript url, production or sandbox, based on store config settings
     */
    public function getTrackJsUrl() {
        return Mage::getStoreConfig('payment/boltpay/test') ?
            self::JS_URL_TEST . "/track.js":
            self::JS_URL_PROD . "/track.js";
    }

    /**
     * Creates an order on Bolt end
     *
     * @param Mage_Sales_Model_Quote $quote    Magento quote object which represents order/cart data
     * @param bool $multipage                  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return mixed json based PHP object
     */
    public function createOrder($quote, $multipage) {

        // Load the required helper class
        $boltHelper = Mage::helper('boltpay/api');

        // Generates order data for sending to Bolt create order API.
        $order_request = $boltHelper->buildOrder($quote, $this->getItems(), $multipage);

        //Mage::log("order_request: ". var_export($order_request, true), null,"bolt.log");

        // Calls Bolt create order API
        $order_response = $boltHelper->transmit('orders', $order_request);

        //Mage::log("order_response: ". json_encode($order_response, JSON_PRETTY_PRINT), null,"bolt.log");

        // Bolt Api call response wrapper method that checks for potential error responses.
        $response = $boltHelper->handleErrorResponse($order_response);

        return $response;
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltConnect with generated data.
     * In BoltConnect.process success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param bool $multipage       Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return string               BoltConnect.process javascript
     */
    public function getCartDataJs($multipage = true) {

        try {

            // Return if the quote is empty
            if (count($this->getItems()) == 0) return;

            // Get customer and cart session objects
            $customerSession = Mage::getSingleton('customer/session');
            $session = Mage::getSingleton('checkout/session');

            // Get the session quote/cart
            $quote = $session->getQuote();
            // Generate new increment order id and associate it with current quote, if not already assigned
            $quote->reserveOrderId()->save();

            // Load the required helper class
            $boltHelper = Mage::helper('boltpay/api');

            ///////////////////////////////////////////////////////////////
            // Populate hints data from quote or customer shipping address.
            //////////////////////////////////////////////////////////////
            $hint_data = $this->getAddressHints($customerSession, $quote);
            ///////////////////////////////////////////////////////////////


            ///////////////////////////////////////////////////////////////////////////////////////
            // Merchant scope: get "bolt_user_id" if the user is logged in or should be registered,
            // sign it and add to hints.
            ///////////////////////////////////////////////////////////////////////////////////////
            $reservedUserId = $this->getReservedUserId($quote, $customerSession);
            $signResponse = null;

            if ($reservedUserId) {
                $signRequest = array(
                    'merchant_user_id' => $reservedUserId,
                );
                $signResponse = $boltHelper->handleErrorResponse($boltHelper->transmit('sign', $signRequest));
            }

            if ($signResponse != null) {
                $hint_data['signed_merchant_user_id'] = array(
                    "merchant_user_id" => $signResponse->merchant_user_id,
                    "signature" => $signResponse->signature,
                    "nonce" => $signResponse->nonce,
                );
            }
            ///////////////////////////////////////////////////////////////////////////////////////


            if (Mage::getStoreConfig('payment/boltpay/auto_capture') == self::AUTO_CAPTURE_ENABLED) {
                $authCapture = 'true';
            } else {
                $authCapture = 'false';
            }

            // Call Bolt create order API
            $orderCreationResponse = $this->createOrder($quote, $multipage);

            //////////////////////////////////////////////////////////////////////////
            // Generate JSON cart and hints objects for the javascript returned below.
            //////////////////////////////////////////////////////////////////////////
            $cart_data = array(
                'authcapture' => $authCapture,
                'orderToken' => $orderCreationResponse->token,
            );

            $json_cart = json_encode($cart_data);
            $json_hints = '{}';
            if (sizeof($hint_data) != 0) {
                $json_hints = json_encode($hint_data);
            }
            //////////////////////////////////////////////////////////////////////////

            // Format the success and save order urls for the javascript returned below.
            $success_url    = $this->getUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
            $save_order_url = $this->getUrl('boltpay/order/save');

            //////////////////////////////////////////////////////
            // Generate and return BoltConnect.process javascript.
            //////////////////////////////////////////////////////
            return ("
                BoltConnect.process(
                    $json_cart,
                    $json_hints,
                    {
                        check: function() {
                            var json_cart = $json_cart;
                            return json_cart.orderToken != '';
                        },
                        close: function() {
                            if (typeof bolt_checkout_close === 'function') bolt_checkout_close();
                        },
                        success: function(transaction, callback) {
                        
                            var onSuccess = function() {
                                setTimeout(function(){location.href = '$success_url';}, 5000);
                                callback();  
                            };
                         
                            var parameters = 'reference='+transaction.reference;
                            
                            new Ajax.Request(
                                '$save_order_url',
                                {
                                    method:'post',
                                    onSuccess: onSuccess,
                                    parameters: parameters
                                }
                            );
                        }
                    }
                );"
            );
            //////////////////////////////////////////////////////

        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * Get address data for sending as hints.
     *
     * @param $session      Customer session
     * @return array        hints data
     */
    private function getAddressHints($session, $quote) {

        $hints = array();

        ///////////////////////////////////////////////////////////////
        // Check if the quote shipping address is set,
        // otherwise use customer shipping address for logged in users.
        ///////////////////////////////////////////////////////////////
        $address = $quote->getShippingAddress();

        if (!$address && $session && $session->isLoggedIn()) {

            $customer = Mage::getModel('customer/customer')->load($session->getId());
            $address = $customer->getDefaultShippingAddress();
        }
        //////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // If address exists populate the hints array with existing address data.
        /////////////////////////////////////////////////////////////////////////
        if ($address) {

            $country_id = @$address->getCountryId();

            if ($country_id) {
                $country      = Mage::getModel('directory/country')->loadByCode($country_id);
                $country_name = @$country->getName();
            }

            if (@$address->getEmail())     $hints['email']           = $address->getEmail();
            if (@$address->getFirstname()) $hints['first_name']      = $address->getFirstname();
            if (@$address->getLastname())  $hints['last_name']       = $address->getLastname();
            if (@$address->getStreet1())   $hints['street_address1'] = $address->getStreet1();
            if (@$address->getStreet2())   $hints['street_address2'] = $address->getStreet2();
            if (@$address->getCity())      $hints['locality']        = $address->getCity();
            if (@$address->getRegion())    $hints['region']          = $address->getRegion();
            if (@$address->getPostcode())  $hints['postal_code']     = $address->getPostcode();
            if (@$address->getTelephone()) $hints['phone']           = $address->getTelephone();
            if (@$country_name)            $hints['country_code']    = $country_id;
            if (@$country_name)            $hints['country']         = $country_name;
        }
        /////////////////////////////////////////////////////////////////////////

        return $hints;
    }

    /**
     * Gets the customer custom attribute, "bolt_user_id", if not set creates one by
     * fetching new Magento customer auto increment ID for the store.
     * Applies to logged in users or the users in the process of registration during the the checkout (checkout type is "register").
     *
     * @param $quote        Magento quote object
     * @param $session      Magento customer/session object
     * @return string|null  the ID used for the Bolt user, or null if the user is not logged in and is not on the onepage checkout page
     */
    function getReservedUserId($quote, $session) {

        $checkout = Mage::getSingleton('checkout/type_onepage');

        $checkoutMethod = $checkout->getCheckoutMethod();

        if ($session->isLoggedIn()) {
            $customer = Mage::getModel('customer/customer')->load($session->getId());

            if ($customer->getBoltUserId() == 0 || $customer->getBoltUserId() == null) {
                Mage::log("Creating new user id for logged in user", null, 'bolt.log');

                $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
                $customer->setBoltUserId($custId);
                $customer->save();
            }

            Mage::log(sprintf("Using Bolt User Id: %s", $customer->getBoltUserId()), null, 'bolt.log');
            return $customer->getBoltUserId();

        } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            Mage::log("Creating new user id for Register checkout", null, 'bolt.log');
            $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
            $session->setBoltUserId($custId);
            return $custId;
        }
    }

    /**
     * Returns CSS_SUFFIX constant to be added to selector identifiers
     * @return string
     */
    function getCssSuffix() {
        return self::CSS_SUFFIX;
    }

    /**
     * Reads the Replace Buttons Style config and generates selectors CSS
     * @return string
     */
    function getSelectorsCSS() {

        $selector_styles = Mage::getStoreConfig('payment/boltpay/selector_styles');

        $selector_styles = array_map('trim', explode('||', trim($selector_styles)));

        $selectors_css = '';

        foreach ($selector_styles as $selector) {

            preg_match('/[^{}]+/', $selector, $selector_identifier);

            $bolt_selector  = trim($selector_identifier[0]) . "-" . self::CSS_SUFFIX;

            preg_match_all('/[^{}]+{[^{}]*}/', $selector, $matches);

            foreach ($matches as $match_array) {
                foreach ($match_array as $match) {

                    preg_match('/{[^{}]*}/', $match, $css);
                    $css = $css[0];

                    preg_match('/[^{}]+/', $match, $identifiers);

                    foreach ($identifiers as $comma_delimited) {

                        $comma_delimited = trim($comma_delimited);
                        $single_identifiers = array_map('trim', explode(',', $comma_delimited));

                        foreach ($single_identifiers as $identifier) {
                            $selectors_css .= $identifier . $bolt_selector . $css;
                            $selectors_css .= $bolt_selector . " " . $identifier . $css;
                        }
                    }
                }
            }
        }

        return $selectors_css;
    }

    /**
     * Returns Additional CSS from configuration.
     * @return string
     */
    function getAdditionalCSS() {
        return Mage::getStoreConfig('payment/boltpay/additional_css');
    }

    /**
     * Returns the Bolt Button Theme from configuration.
     * @return string
     */
    function getTheme() {
        return Mage::getStoreConfig('payment/boltpay/theme');
    }

    /**
     * Returns the Bolt Sandbox Mode configuration.
     * @return string
     */
    function isTestMode() {
        return Mage::getStoreConfig('payment/boltpay/test');
    }

    /**
     * Returns the Replace Button Selectors configuration.
     * @return string
     */
    function getConfigSelectors() {
        return json_encode(array_filter(explode(',', Mage::getStoreConfig('payment/boltpay/selectors'))));
    }

    /**
     * Returns the Skip Payment Method Step configuration.
     * @return string
     */
    function isBoltOnlyPayment() {
        Mage::getStoreConfig('payment/boltpay/skip_payment');
    }

    /**
     * Returns the Success Page Redirect configuration.
     * @return string
     */
    function getSuccessURL() {
        return $this->getUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
    }

    /**
     * Returns the Bolt Save Order Url.
     * @return string
     */
    function getSaveOrderURL() {
        return $this->getUrl('boltpay/order/save');
    }

    /**
     * Returns the Cart Url.
     * @return string
     */
    function getCartURL() {
        return $this->getUrl('checkout/cart');
    }

    /**
     * Returns the One Page / Multi-Page checkout Publishable key.
     * @return string
     */
    function getPaymentKey($multipage = true) {
       return $multipage
           ? Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/publishable_key_multipage'))
           : Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/publishable_key_onepage'));
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return string
     */
    function isBoltActive() {
        return Mage::getStoreConfig('payment/boltpay/active');
    }
}

