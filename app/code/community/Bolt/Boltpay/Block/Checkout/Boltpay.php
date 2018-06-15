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
class Bolt_Boltpay_Block_Checkout_Boltpay extends Mage_Checkout_Block_Onepage_Review_Info
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

        $items = $quote->getAllVisibleItems();

        if (empty($items)) return json_decode('{"token" : ""}');

        // Generates order data for sending to Bolt create order API.
        $orderRequest = $boltHelper->buildOrder($quote, $items, $multipage);

        //Mage::log("order_request: ". var_export($order_request, true), null,"bolt.log");

        // Calls Bolt create order API
        return $boltHelper->transmit('orders', $orderRequest);
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($checkoutType = 'multi-page')
    {
        try {
            // Get customer and cart session objects
            $customerSession = Mage::getSingleton('customer/session');
            $session = ($checkoutType === 'admin') ? Mage::getSingleton('adminhtml/session_quote') : Mage::getSingleton('checkout/session');

            /* @var Mage_Sales_Model_Quote $sessionQuote */
            $sessionQuote =  $session->getQuote();

            // Load the required helper class
            $boltHelper = Mage::helper('boltpay/api');

            ///////////////////////////////////////////////////////////////
            // Populate hints data from quote or customer shipping address.
            //////////////////////////////////////////////////////////////
            $hintData = $this->getAddressHints($customerSession, $sessionQuote);
            ///////////////////////////////////////////////////////////////

            $orderCreationResponse = '';
            /* @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getSingleton('sales/quote');

            // Check if cart contains at least one item.
            $isEmptyQuote = (!($sessionQuote->getItemsCollection()->count())) ? true : false;

            if (!$isEmptyQuote) {
                ///////////////////////////////////////////////////////////////////////////////////////
                // Merchant scope: get "bolt_user_id" if the user is logged in or should be registered,
                // sign it and add to hints.
                ///////////////////////////////////////////////////////////////////////////////////////
                $reservedUserId = $this->getReservedUserId($sessionQuote, $customerSession);
                if ($reservedUserId && $this->isEnableMerchantScopedAccount()) {
                    $signRequest = array(
                        'merchant_user_id' => $reservedUserId,
                    );
                    $signResponse = $boltHelper->transmit('sign', $signRequest);

                    if ($signResponse != null) {
                        $hintData['signed_merchant_user_id'] = array(
                            "merchant_user_id" => $signResponse->merchant_user_id,
                            "signature" => $signResponse->signature,
                            "nonce" => $signResponse->nonce,
                        );
                    }
                }
                ///////////////////////////////////////////////////////////////////////////////////////

                if($checkoutType === 'multi-page') {
                    // Resets shipping rate
                    $shippingMethod = $sessionQuote->getShippingAddress()->getShippingMethod();
                    $boltHelper->applyShippingRate($sessionQuote, null);
                }

                // Call Bolt create order API
                try {
                    /////////////////////////////////////////////////////////////////////////////////
                    // We create a copy of the quote that is immutable by the customer/frontend
                    // Bolt saves this quote to the database at Magento-side order save time.
                    // This assures that the quote saved to Magento matches what is stored on Bolt
                    // Only shipping, tax and discounts can change, and only if the shipping, tax
                    // and discount calculations change on the Magento server
                    ////////////////////////////////////////////////////////////////////////////////

                    /*********************************************************/
                    /* Clean up resources that may have previously been saved
                    /* @var Mage_Sales_Model_Quote[] $expiredQuotes */
                    $expiredQuotes = Mage::getModel('sales/quote')
                        ->getCollection()
                        ->addFieldToFilter('parent_quote_id', $sessionQuote->getId());

                    foreach ($expiredQuotes as $expiredQuote) {
                        $expiredQuote->delete();
                    }
                    /*********************************************************/

                    try {
                        $immutableQuote->merge($sessionQuote);
                    } catch (Exception $e) {
                        Mage::helper('boltpay/bugsnag')->notifyException($e);
                    }

                    if ($checkoutType === 'one-page') {
                        // For one-page checkout page we want to set the
                        // billing and shipping, and shipping method at this time.
                        // For multi-page, we add the addresses during the shipping and tax hook
                        // and the chosen shipping method at order save time.
                        $immutableQuote
                            ->setBillingAddress($sessionQuote->getBillingAddress())
                            ->setShippingAddress($sessionQuote->getShippingAddress())
                            ->getShippingAddress()
                            ->setShippingMethod($sessionQuote->getShippingAddress()->getShippingMethod())
                            ->save();
                    }

                    /*
                     *  Attempting to reset some of the values already set by merge affects the totals passed to
                     *  Bolt in such a way that the grand total becomes 0.  Since we do not need to reset these values
                     *  we ignore them all.
                     */
                    $fieldsSetByMerge = array(
                        'coupon_code',
                        'subtotal',
                        'base_subtotal',
                        'subtotal_with_discount',
                        'base_subtotal_with_discount',
                        'grand_total',
                        'base_grand_total',
                        'auctaneapi_discounts',
                        'applied_rule_ids',
                        'items_count',
                        'items_qty',
                        'virtual_items_qty',
                        'trigger_recollect',
                        'can_apply_msrp',
                        'totals_collected_flag',
                        'global_currency_code',
                        'base_currency_code',
                        'store_currency_code',
                        'quote_currency_code',
                        'store_to_base_rate',
                        'store_to_quote_rate',
                        'base_to_global_rate',
                        'base_to_quote_rate',
                        'is_changed',
                        'created_at',
                        'updated_at',
                        'entity_id'
                    );

                    // Add all previously saved data that may have been added by other plugins
                    foreach ($sessionQuote->getData() as $key => $value) {
                        if (!in_array($key, $fieldsSetByMerge)) {
                            $immutableQuote->setData($key, $value);
                        }
                    }

                    /////////////////////////////////////////////////////////////////
                    // Generate new increment order id and associate it with current quote, if not already assigned
                    // Save the reserved order ID to the session to check order existence at frontend order save time
                    /////////////////////////////////////////////////////////////////
                    $reservedOrderId = $sessionQuote->reserveOrderId()->save()->getReservedOrderId();
                    Mage::getSingleton('core/session')->setReservedOrderId($reservedOrderId);

                    $immutableQuote
                        ->setCustomer($sessionQuote->getCustomer())
                        ->setCustomerGroupId($sessionQuote->getCustomerGroupId())
                        ->setCustomerIsGuest((($sessionQuote->getCustomerId()) ? false : true))
                        ->setReservedOrderId($reservedOrderId)
                        ->setStoreId($sessionQuote->getStoreId())
                        ->setParentQuoteId($sessionQuote->getId())
                        ->save();

                    $orderCreationResponse = $this->createBoltOrder($immutableQuote, $checkoutType === 'multi-page');
                } catch (Exception $e) {
                    Mage::helper('boltpay/bugsnag')->notifyException(new Exception($e));
                    $orderCreationResponse = json_decode('{"token" : ""}');
                }

                if ($checkoutType === 'multi-page') {
                    $boltHelper->applyShippingRate($sessionQuote, $shippingMethod);
                }

            }

            $authCapture = (Mage::getStoreConfig('payment/boltpay/auto_capture') == self::AUTO_CAPTURE_ENABLED);

            //////////////////////////////////////////////////////////////////////////
            // Generate JSON cart and hints objects for the javascript returned below.
            //////////////////////////////////////////////////////////////////////////
            $cartData = array(
                'authcapture' => $authCapture,
                'orderToken' => ($orderCreationResponse) ? $orderCreationResponse->token: '',
            );

            if (Mage::registry("api_error")) {
                $cartData['error'] = Mage::registry("api_error");
            }

            $jsonCart = json_encode($cartData);
            $jsonHints = '{}';
            if (sizeof($hintData) != 0) {
                // Convert $hint_data to object, because when empty data it consists array not an object
                $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);
            }

            //////////////////////////////////////////////////////////////////////////
            // Format the success and save order urls for the javascript returned below.
            $successUrl   = $this->getUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
            $saveOrderUrl = $this->getUrl('boltpay/order/save');

            //////////////////////////////////////////////////////
            // Collect the event Javascripts
            // We execute these events as early as possible, typically
            // before Bolt defined event JS to give merchants the
            // opportunity to do full overrides
            //////////////////////////////////////////////////////
            $check = Mage::getStoreConfig('payment/boltpay/check');
            $onCheckoutStart = Mage::getStoreConfig('payment/boltpay/on_checkout_start');
            $onShippingDetailsComplete = Mage::getStoreConfig('payment/boltpay/on_shipping_details_complete');
            $onShippingOptionsComplete = Mage::getStoreConfig('payment/boltpay/on_shipping_options_complete');
            $onPaymentSubmit = Mage::getStoreConfig('payment/boltpay/on_payment_submit');
            $success = Mage::getStoreConfig('payment/boltpay/success');
            $close = Mage::getStoreConfig('payment/boltpay/close');

            //////////////////////////////////////////////////////
            // Generate and return BoltCheckout javascript.
            //////////////////////////////////////////////////////
            $immutableQuoteId = $immutableQuote->getId();

            return ("
                var json_cart = $jsonCart;
                var quote_id = '{$immutableQuoteId}';
                var order_completed = false;
                var isEmptyQuote = '".$isEmptyQuote."';
                
                BoltCheckout.configure(
                    json_cart,
                    $jsonHints,
                    {
                      check: function() {           
                        $check
                        "
                        .
                        (($checkoutType === 'admin')
                            ?
                                "if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                                    var bolt_hidden = document.getElementById('boltpay_payment_button');
                                    bolt_hidden.classList.remove('required-entry');
                                    
                                    var is_valid = true;
                                    
                                    if (!editForm.validate()) {
                                        is_valid = false;
                                    } else {        
                                        var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0];
                                        if (typeof shipping_method === 'undefined') {
                                            alert('Please select a shipping method.');
                                            is_valid = false;
                                        }
                                    }
                                
                                    bolt_hidden.classList.add('required-entry');  
                                    return is_valid;   
                                }"
                            :
                                ""
                        )
                        .
                        "
                        if (isEmptyQuote) {
                            alert('{$boltHelper->__('Your shopping cart is empty. Please add products to the cart.')}');
                            return false;
                        }
                        if (!json_cart.orderToken) {
                            alert(json_cart.error);
                            return false;
                        }
                        return true;
                      },
                      
                      onCheckoutStart: function() {
                        // This function is called after the checkout form is presented to the user.
                        $onCheckoutStart
                      },
                      
                      onShippingDetailsComplete: function() {
                        // This function is called when the user proceeds to the shipping options page.
                        // This is applicable only to multi-step checkout.
                        $onShippingDetailsComplete
                      },
                      
                      onShippingOptionsComplete: function() {
                        // This function is called when the user proceeds to the payment details page.
                        // This is applicable only to multi-step checkout.
                        $onShippingOptionsComplete
                      },
                      
                      onPaymentSubmit: function() {
                        // This function is called after the user clicks the pay button.
                        $onPaymentSubmit
                      },
                      
                      success: function(transaction, callback) {
                      "
                      .
                      (($checkoutType === 'admin')
                        ?
                            "// order and order.submit will exist for admin
                            if ((typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                                order_completed = true;
                                callback();
                            }"
                        :
                            "new Ajax.Request(
                                '$saveOrderUrl',
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
                            );"
                      )
                      .
                      "
                      },
                      
                      close: function() {
                         $close
                         "
                         .
                         (($checkoutType === 'admin')
                            ?
                                "//////////////////
                                 // admin logic
                                 //////////////////
                                 if (order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                                    var bolt_hidden = document.getElementById('boltpay_payment_button');
                                    bolt_hidden.classList.remove('required-entry');
                                    order.submit();
                                 }"
                            :
                         
                                "//////////////////
                                 // frontend logic
                                 //////////////////
                                 if (typeof bolt_checkout_close === 'function') {
                                    // used internally to set overlay in firecheckout
                                    bolt_checkout_close();
                                 }
                                 if (order_completed) {   
                                    location.href = '$successUrl';
                                 }"
                         )
                         .
                         "
                      }
                    }
                );"
            );

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

        return array( "prefill" => $hints );
    }

    /**
     * Gets the customer custom attribute, "bolt_user_id", if not set creates one by
     * fetching new Magento customer auto increment ID for the store.
     * Applies to logged in users or the users in the process of registration during the the checkout (checkout type is "register").
     *
     * @param $quote   - Magento quote object
     * @param $session - Magento customer/session object
     * @return string|null  the ID used for the Bolt user, or null if the user is not logged in and is not on the onepage checkout page
     * @throws Exception
     */
    public function getReservedUserId($quote, $session)
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

        $selectorStyles = Mage::getStoreConfig('payment/boltpay/selector_styles');

        $selectorStyles = array_map('trim', explode('||', trim($selectorStyles)));

        $selectorsCss = '';

        foreach ($selectorStyles as $selector) {
            preg_match('/[^{}]+/', $selector, $selectorIdentifier);

            $boltSelector  = trim($selectorIdentifier[0]) . "-" . self::CSS_SUFFIX;

            preg_match_all('/[^{}]+{[^{}]*}/', $selector, $matches);

            foreach ($matches as $matchArray) {
                foreach ($matchArray as $match) {
                    preg_match('/{[^{}]*}/', $match, $css);
                    $css = $css[0];

                    preg_match('/[^{}]+/', $match, $identifiers);

                    foreach ($identifiers as $commaDelimited) {
                        $commaDelimited = trim($commaDelimited);
                        $singleIdentifiers = array_map('trim', explode(',', $commaDelimited));

                        foreach ($singleIdentifiers as $identifier) {
                            $selectorsCss .= $identifier . $boltSelector . $css;
                            $selectorsCss .= $boltSelector . " " . $identifier . $css;
                        }
                    }
                }
            }
        }

        return $selectorsCss;
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
        return Mage::getStoreConfigFlag('payment/boltpay/test');
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
        return Mage::getStoreConfig('payment/boltpay/skip_payment');
    }
    
    /**
     * Returns whether enable merchant scoped account.
     * @return string
     */
    function isEnableMerchantScopedAccount()
    {
        return Mage::getStoreConfig('payment/boltpay/enable_merchant_scoped_account');
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
     *
     * @param  string $checkoutType  'multi-page' | 'one-page' | 'admin'
     *
     * @return string the publishable key associated with the type of checkout requested
     */
    function getPublishableKey($checkoutType = 'multi-page')
    {
        /** @var Bolt_Boltpay_Helper_Data $hlp */
        $hlp = $this->helper('boltpay');

        switch ($checkoutType) {
            case 'multi-page':
            case 'multipage':
                return $hlp->getPublishableKeyMultiPage();
            case 'back-office':
            case 'backoffice':
            case 'admin':
                return $hlp->getPublishableKeyBackOffice();
            case 'one-page':
            case 'onepage':
            default:
                return $hlp->getPublishableKeyOnePage();
        }
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return bool
     */
    public function isBoltActive()
    {
        return $this->helper('boltpay')->isBoltPayActive();
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
        $locationInfo = Mage::getSingleton('core/session')->getLocationInfo();

        if (empty($locationInfo)) {
            $locationInfo = $this->url_get_contents("http://freegeoip.net/json/".$this->getIpAddress());
            Mage::getSingleton('core/session')->setLocationInfo($locationInfo);
        }

        return $locationInfo;
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
     * Check quote can use bolt.
     *
     * @return bool
     * @throws Mage_Core_Model_Store_Exception
     */
    public function canUseBolt()
    {
        /** @var Bolt_Boltpay_Helper_Data $hlp */
        $hlp = $this->helper('boltpay');

        return $hlp->canUseBolt($this->getQuote(), false);
    }

    /**
     * Checking if allows to insert connectjs or replace by route name
     *
     * @return bool
     * @throws Exception
     */
    private function isAllowedOnCurrentPageByRoute()
    {
        $routeName = $this->getRequest()->getRouteName();
        $controllerName = $this->getRequest()->getControllerName();

        $isAllowed = ($routeName === 'checkout' && $controllerName === 'cart');

        return $isAllowed;
    }

    /**
     * Gets Publishable Key depending the other checkout type.
     * -  shopping cart uses multi-step publishable keys
     * -  firecheckout and onepage checkout uses a payment only publishable key
     *
     * @return string
     * @throws Exception
     */
    public function getPublishableKeyForRoute()
    {
        $routeName = $this->getRequest()->getRouteName();
        $controllerName = $this->getRequest()->getControllerName();

        $isForMultiPage = !($routeName === 'firecheckout') && !($routeName === 'checkout' && $controllerName !== 'cart');

        return $this->getPublishableKey($isForMultiPage);
    }

    /**
     * Checking config setting and is allow the connectjs script
     *
     * @return bool
     * @throws Exception
     */
    public function isAllowedConnectJsOnCurrentPage()
    {
        /** @var Bolt_Boltpay_Helper_Data $hlp */
        $hlp = $this->helper('boltpay');
        $canAddEverywhere = $hlp->canUseEverywhere();

        $isAllowedOnCurrentPage = $this->isAllowedOnCurrentPageByRoute();

        return ($canAddEverywhere || $isAllowedOnCurrentPage);
    }

    /**
     * Checking if allow the replace script.
     *
     * @return bool
     * @throws Exception
     */
    public function isAllowedReplaceScriptOnCurrentPage()
    {
        $isFireCheckoutPage = ($this->getRequest()->getRouteName() === 'firecheckout');

        return (!$isFireCheckoutPage && $this->isAllowedConnectJsOnCurrentPage());
    }
}
