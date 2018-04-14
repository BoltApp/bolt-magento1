<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
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
class Bolt_Boltpay_Block_Checkout_Boltpay
    extends Mage_Checkout_Block_Onepage_Review_Info
{

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
    public function _construct() 
    {
        parent::_construct();
        $this->_jsUrl = Mage::getStoreConfig('payment/boltpay/test') ?
            self::JS_URL_TEST . "/connect.js":
            self::JS_URL_PROD . "/connect.js";
    }

    /**
     * Get the track javascript url, production or sandbox, based on store config settings
     */
    public function getTrackJsUrl() 
    {
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
    private function createBoltOrder($quote, $multipage) 
    {

        // Load the required helper class
        $boltHelper = Mage::helper('boltpay/api');

        $items = $this->getItems();
        if (empty($items)) return json_decode('{"token" : ""}');

        // Generates order data for sending to Bolt create order API.
        $order_request = $boltHelper->buildOrder($quote, $items, $multipage);

        //Mage::log("order_request: ". var_export($order_request, true), null,"bolt.log");

        // Calls Bolt create order API
        return $boltHelper->transmit('orders', $order_request);
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param bool $multipage       Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($multipage = true) 
    {

        try {
            // Get customer and cart session objects
            $customerSession = Mage::getSingleton('customer/session');
            $session = Mage::getSingleton('checkout/session');

            // Get the session quote/cart
            $quote = $session->getQuote();
            $quote->setExtShippingInfo(Mage::getSingleton('core/session')->getSessionId());
            $quote->save();
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
                $signResponse = $boltHelper->transmit('sign', $signRequest);
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
            try {
                $orderCreationResponse = $this->createBoltOrder($quote, $multipage);
            } catch (Exception $e) {
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception($e));
                $orderCreationResponse = json_decode('{"token" : ""}');
            }


            //////////////////////////////////////////////////////////////////////////
            // Generate JSON cart and hints objects for the javascript returned below.
            //////////////////////////////////////////////////////////////////////////
            $cart_data = array(
                'authcapture' => $authCapture,
                'orderToken' => $orderCreationResponse->token,
            );

            if (Mage::registry("api_error")) {
                $cart_data['error'] = Mage::registry("api_error");
            }

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
            // Collect the event Javascripts
            //////////////////////////////////////////////////////
            $check = Mage::getStoreConfig('payment/boltpay/check');
            $on_checkout_start = Mage::getStoreConfig('payment/boltpay/on_checkout_start');
            $on_shipping_details_complete = Mage::getStoreConfig('payment/boltpay/on_shipping_details_complete');
            $on_shipping_options_complete = Mage::getStoreConfig('payment/boltpay/on_shipping_options_complete');
            $on_payment_submit = Mage::getStoreConfig('payment/boltpay/on_payment_submit');
            $success = Mage::getStoreConfig('payment/boltpay/success');
            $close = Mage::getStoreConfig('payment/boltpay/close');
            //////////////////////////////////////////////////////

            //////////////////////////////////////////////////////
            // Generate and return BoltCheckout javascript.
            //////////////////////////////////////////////////////
            return ("
                var json_cart = $json_cart;
                var quote_id = '{$quote->getId()}';
                var order_completed = false;
                
                BoltCheckout.configure(
                    json_cart,
                    $json_hints,
                    {
                      check: function() {
                        if (!json_cart.orderToken) {
                            alert(json_cart.error);
                            return false;
                        }
                        $check
                        return true;
                      },
                      
                      onCheckoutStart: function() {
                        // This function is called after the checkout form is presented to the user.
                        $on_checkout_start
                      },
                      
                      onShippingDetailsComplete: function() {
                        // This function is called when the user proceeds to the shipping options page.
                        // This is applicable only to multi-step checkout.
                        $on_shipping_details_complete
                      },
                      
                      onShippingOptionsComplete: function() {
                        // This function is called when the user proceeds to the payment details page.
                        // This is applicable only to multi-step checkout.
                        $on_shipping_options_complete
                      },
                      
                      onPaymentSubmit: function() {
                        // This function is called after the user clicks the pay button.
                        $on_payment_submit
                      },
                      
                      success: function(transaction, callback) {
                        new Ajax.Request(
                            '$save_order_url',
                            {
                                method:'post',
                                onSuccess: 
                                    function() {
                                        $success
                                        order_completed = true;
                                        callback();  
                                    },
                                parameters: 'reference='+transaction.reference
                            }
                        );
                      },
                      
                      close: function() {
                         $close
                         if (typeof bolt_checkout_close === 'function') {
                            // used internally to set overlay in firecheckout
                            bolt_checkout_close();
                         }
                         if (order_completed) {   
                            location.href = '$success_url';
                         }
                      }
                    }
                );"
            );
            //////////////////////////////////////////////////////
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * Get address data for sending as hints.
     *
     * @param $session      Customer session
     * @return array        hints data
     */
    private function getAddressHints($session, $quote) 
    {

        $hints = array();

        ///////////////////////////////////////////////////////////////
        // Check if the quote shipping address is set,
        // otherwise use customer shipping address for logged in users.
        ///////////////////////////////////////////////////////////////
        $address = $quote->getShippingAddress();

        if ($session && $session->isLoggedIn()) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel('customer/customer')->load($session->getId());
            $address = $customer->getPrimaryShippingAddress();
            $email = $customer->getEmail();
        }

        //////////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // If address exists populate the hints array with existing address data.
        /////////////////////////////////////////////////////////////////////////
        if ($address) {
            if (@$email)                   $hints['email']        = $email;
            if (@$address->getFirstname()) $hints['firstName']    = $address->getFirstname();
            if (@$address->getLastname())  $hints['lastName']     = $address->getLastname();
            if (@$address->getStreet1())   $hints['addressLine1'] = $address->getStreet1();
            if (@$address->getStreet2())   $hints['addressLine2'] = $address->getStreet2();
            if (@$address->getCity())      $hints['city']         = $address->getCity();
            if (@$address->getRegion())    $hints['state']        = $address->getRegion();
            if (@$address->getPostcode())  $hints['zip']          = $address->getPostcode();
            if (@$address->getTelephone()) $hints['phone']        = $address->getTelephone();
            if (@$address->getCountryId()) $hints['country']      = $address->getCountryId();
        }

        /////////////////////////////////////////////////////////////////////////

        return array( "prefill" => $hints );
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
    function getReservedUserId($quote, $session) 
    {

        $checkout = Mage::getSingleton('checkout/type_onepage');

        $checkoutMethod = $checkout->getCheckoutMethod();

        if ($session->isLoggedIn()) {
            $customer = Mage::getModel('customer/customer')->load($session->getId());

            if ($customer->getBoltUserId() == 0 || $customer->getBoltUserId() == null) {
                //Mage::log("Creating new user id for logged in user", null, 'bolt.log');

                $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
                $customer->setBoltUserId($custId);
                $customer->save();
            }

            //Mage::log(sprintf("Using Bolt User Id: %s", $customer->getBoltUserId()), null, 'bolt.log');
            return $customer->getBoltUserId();
        } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            //Mage::log("Creating new user id for Register checkout", null, 'bolt.log');
            $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
            $session->setBoltUserId($custId);
            return $custId;
        }
    }

    /**
     * Returns CSS_SUFFIX constant to be added to selector identifiers
     * @return string
     */
    function getCssSuffix() 
    {
        return self::CSS_SUFFIX;
    }

    /**
     * Reads the Replace Buttons Style config and generates selectors CSS
     * @return string
     */
    function getSelectorsCSS() 
    {

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
    function getAdditionalCSS() 
    {
        return Mage::getStoreConfig('payment/boltpay/additional_css');
    }

    /**
     * Returns the Bolt Button Theme from configuration.
     * @return string
     */
    function getTheme() 
    {
        return Mage::getStoreConfig('payment/boltpay/theme');
    }

    /**
     * Returns the Bolt Sandbox Mode configuration.
     * @return string
     */
    function isTestMode() 
    {
        return Mage::getStoreConfig('payment/boltpay/test');
    }

    /**
     * Returns the Replace Button Selectors configuration.
     * @return string
     */
    function getConfigSelectors() 
    {
        return json_encode(array_filter(explode(',', Mage::getStoreConfig('payment/boltpay/selectors'))));
    }

    /**
     * Returns the Skip Payment Method Step configuration.
     * @return string
     */
    function isBoltOnlyPayment() 
    {
        Mage::getStoreConfig('payment/boltpay/skip_payment');
    }

    /**
     * Returns the Success Page Redirect configuration.
     * @return string
     */
    function getSuccessURL() 
    {
        return $this->getUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
    }

    /**
     * Returns the Bolt Save Order Url.
     * @return string
     */
    function getSaveOrderURL() 
    {
        return $this->getUrl('boltpay/order/save');
    }

    /**
     * Returns the Cart Url.
     * @return string
     */
    function getCartURL() 
    {
        return $this->getUrl('checkout/cart');
    }

    /**
     * Returns the One Page / Multi-Page checkout Publishable key.
     * @return string
     */
    function getPaymentKey($multipage = true) 
    {
        return $multipage
            ? Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/publishable_key_multipage'))
            : Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/publishable_key_onepage'));
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return string
     */
    function isBoltActive() 
    {
        return Mage::getStoreConfig('payment/boltpay/active');
    }

    /**
     * Gets the IP address of the requesting customer.  This is used instead of simply $_SERVER['REMOTE_ADDR'] to give more accurate IPs if a
     * proxy is being used.
     *
     * @return string  The IP address of the customer
     */
    function getIpAddress()
    {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip){
                    $ip = trim($ip); // just to be safe

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                        return $ip;
                    }
                }
            }
        }
    }

    /**
     * Gets the estimated location of the client based on client's IP address
     * This currently uses http://freegeoip.net to obtain this data which has a
     * limit of 15000 queries per hour from the store.
     *
     * When there is a need to increase this limit, it can be downloaded and hosted
     * on Bolt to remove this limit.
     *
     * @return bool|string  JSON containing geolocation info of the client, or false if the ip could not be obtained.
     */
    function getLocationEstimate() 
    {
        $location_info = Mage::getSingleton('core/session')->getLocationInfo();

        if (empty($location_info)) {
            $location_info = $this->url_get_contents("http://freegeoip.net/json/".$this->getIpAddress());
            Mage::getSingleton('core/session')->setLocationInfo($location_info);
        }

        return $location_info;
    }

    public function url_get_contents($url) 
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * Return the current quote used in the session
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() 
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Get PaymentKey depending the other checkout modules.
     *
     * @return string
     */
    public function getPaymentKeyDependingTheModule()
    {
        $routeName = Mage::app()->getRequest()->getRouteName();

        // If exist 'firecheckout' route we should send false.
        $param = ($routeName === 'firecheckout') ? false : true;

        return $this->getPaymentKey($param);
    }
}
