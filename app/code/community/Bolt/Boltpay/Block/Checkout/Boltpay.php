<?php
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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
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
    const CHECKOUT_TYPE_ADMIN       = 'admin';
    const CHECKOUT_TYPE_MULTI_PAGE  = 'multi-page';
    const CHECKOUT_TYPE_ONE_PAGE    = 'one-page';
    const CHECKOUT_TYPE_FIRECHECKOUT = 'firecheckout';

    const CSS_SUFFIX = 'bolt-css-suffix';

    /**
     * Set the connect javascript url to production or sandbox based on store config settings
     */
    public function _construct()
    {
        parent::_construct();
        $this->_jsUrl = Mage::helper('boltpay/url')->getJsUrl() . "/connect.js";
    }

    /**
     * Get the track javascript url, production or sandbox, based on store config settings
     */
    public function getTrackJsUrl()
    {
        return Mage::helper('boltpay/url')->getJsUrl() . "/track.js";
    }

    /**
     * Get an order token for a Bolt order either by creating it or making a Promise to create it
     *
     * @param Mage_Sales_Model_Quote $quote         Magento quote object which represents order/cart data
     * @param string                 $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     *
     * @return mixed json based PHP object or a javascript Promise string when initializing firecheckout
     */
    public function getBoltOrderToken($quote, $checkoutType)
    {
        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');
        $isMultiPage = $checkoutType === self::CHECKOUT_TYPE_MULTI_PAGE;

        if ($checkoutType === self::CHECKOUT_TYPE_FIRECHECKOUT) {
            $firecheckoutTokenUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/firecheckoutcreate');
            return <<<PROMISE
                        new Promise( 
                            function (resolve, reject) {
                                var firecheckoutAjaxId = setInterval(
                                    function() {
                                         if (isFireCheckoutFormValid) {
                                            new Ajax.Request('$firecheckoutTokenUrl', {
                                                method:'post',
                                                parameters: checkout.getFormData ? checkout.getFormData() : Form.serialize(checkout.form, true),
                                                onSuccess: function(response) {
                                                    if(response.responseJSON.error) {                                                        
                                                        reject(response.responseJSON.error_messages);
                                                        
                                                        // BoltCheckout is currently not doing anything reasonable to alert the user of a problem, so we will do something as a backup
                                                        alert(response.responseJSON.error_messages);
                                                        location.reload();
                                                    } else {
                                                        resolve(response.responseJSON.cart_data);
                                                    }                   
                                                },
                                                 onFailure: function(error) { reject(error) }
                                            });
                                            clearInterval(firecheckoutAjaxId);
                                         }
                                    }, 300
                                );
                            }
                        )
PROMISE;
        }

        $items = $quote->getAllVisibleItems();

        $hasAdminShipping = false;
        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
            $shippingMethodBlock = Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_shipping_method_form");
            $hasAdminShipping = $shippingMethodBlock->getActiveMethodRate();
        }

        if (empty($items)) {

            return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('Your shopping cart is empty. Please add products to the cart.').'"}');

        } else if (
            !$isMultiPage
            && !$quote->getShippingAddress()->getShippingMethod()
            && !$hasAdminShipping
        ) {

            if (!$quote->isVirtual()){
                return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.').'"}');
            }

            if (!$this->validateVirtualQuote($quote)){
                return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('Billing address is required.').'"}');
            }
        }

        // Generates order data for sending to Bolt create order API.
        $orderRequest = Mage::getModel('boltpay/boltOrder')->buildOrder($quote, $items, $isMultiPage);

        // Calls Bolt create order API
        return $boltHelper->transmit('orders', $orderRequest);
    }

    /**
     * Validate virtual quote
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    protected function validateVirtualQuote($quote)
    {
       if (!$quote->isVirtual()){
           return true;
       }

       $address = $quote->getBillingAddress();

        if (
            !$address->getLastname() ||
            !$address->getStreet1() ||
            !$address->getCity() ||
            !$address->getPostcode() ||
            !$address->getTelephone() ||
            !$address->getCountryId()
        ){
            return false;
        }

        return true;
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($checkoutType = self::CHECKOUT_TYPE_MULTI_PAGE)
    {
        try {
            /* @var Mage_Sales_Model_Quote $sessionQuote */
            $sessionQuote = $this->getSessionQuote($checkoutType);

            /* @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');

            /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
            $shippingAndTaxModel = Mage::getModel('boltpay/shippingAndTax');

            $hintData = $this->getAddressHints($sessionQuote, $checkoutType);

            $orderCreationResponse = json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('Unexpected error.  Please contact support for assistance.').'"}');

            $isMultiPage = ($checkoutType === self::CHECKOUT_TYPE_MULTI_PAGE);
            // For multi-page, remove shipping that may have been added by Magento shipping and tax estimate interface
            if ($isMultiPage) {
                // Resets shipping rate
                $shippingMethod = $sessionQuote->getShippingAddress()->getShippingMethod();
                $shippingAndTaxModel->applyShippingRate($sessionQuote, null);
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
                /** @var Mage_Sales_Model_Quote $immutableQuote */
                $immutableQuote = $boltHelper->cloneQuote($sessionQuote, $checkoutType);
                ////////////////////////////////////////////////////////////////////////////////

                $orderCreationResponse = $this->getBoltOrderToken($immutableQuote, $checkoutType);

                if (@!$orderCreationResponse->error) {
                    ///////////////////////////////////////////////////////////////////////////////////////
                    // Merchant scope: get "bolt_user_id" if the user is logged in or should be registered,
                    // sign it and add to hints.
                    ///////////////////////////////////////////////////////////////////////////////////////
                    $reservedUserId = $this->getReservedUserId($sessionQuote);
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
                }
            } catch (Exception $e) {
                $metaData = array('quote' => var_export($sessionQuote->debug(), true));
                Mage::helper('boltpay/bugsnag')->notifyException(
                    new Exception($e),
                    $metaData
                );
            }

            // For multi-page, reapply shipping to quote that may be used for shipping and tax estimate
            if ($isMultiPage) {
                $shippingAndTaxModel->applyShippingRate($sessionQuote, $shippingMethod);
            }

            $cartData = ($checkoutType === self::CHECKOUT_TYPE_FIRECHECKOUT) ? $orderCreationResponse : $this->buildCartData($orderCreationResponse, $checkoutType);

            return $this->buildBoltCheckoutJavascript($checkoutType, $immutableQuote, $hintData, $cartData);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * @param $checkoutType
     * @return bool
     */
    protected function isAdminAndUseJsInAdmin($checkoutType)
    {
        return ($checkoutType === self::CHECKOUT_TYPE_ADMIN) && !Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin');
    }

    /**
     * Generate cart data
     *
     * @param      $orderCreationResponse
     * @param null $checkoutType
     * @return array
     */
    public function buildCartData($orderCreationResponse, $checkoutType = null)
    {
        $authCapture = $checkoutType === 'admin' ? true : Mage::getStoreConfigFlag('payment/boltpay/auto_capture');

        //////////////////////////////////////////////////////////////////////////
        // Generate JSON cart and hints objects for the javascript returned below.
        //////////////////////////////////////////////////////////////////////////
        $cartData = array(
            'authcapture' => $authCapture,
            'orderToken' => ($orderCreationResponse) ? $orderCreationResponse->token: '',
        );

        // If there was an unexpected API error, then it was stored in the registry
        if (Mage::registry("bolt_api_error")) {
            $cartData['error'] = Mage::registry("bolt_api_error");
        } else if (@$orderCreationResponse->error) {
            $cartData['error'] = $orderCreationResponse->error;
        }

        return $cartData;
    }

    /**
     * Generate BoltCheckout Javascript for output.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     * @param $quote
     * @param $hintData
     * @param $cartData
     * @return string
     */
    public function buildBoltCheckoutJavascript($checkoutType, $quote, $hintData, $cartData)
    {
        /* @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');

        $jsonCart = (is_string($cartData)) ? $cartData : json_encode($cartData);
        $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);

        //////////////////////////////////////////////////////
        // Collect the event Javascripts
        // We execute these events as early as possible, typically
        // before Bolt defined event JS to give merchants the
        // opportunity to do full overrides
        //////////////////////////////////////////////////////
        $checkCustom = $boltHelper->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckoutStartCustom = $boltHelper->getPaymentBoltpayConfig('on_checkout_start', $checkoutType);
        $onEmailEnterCustom = $boltHelper->getPaymentBoltpayConfig('on_email_enter', $checkoutType);
        $onShippingDetailsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_details_complete', $checkoutType);
        $onShippingOptionsCompleteCustom = $boltHelper->getPaymentBoltpayConfig('on_shipping_options_complete', $checkoutType);
        $onPaymentSubmitCustom = $boltHelper->getPaymentBoltpayConfig('on_payment_submit', $checkoutType);
        $successCustom = $boltHelper->getPaymentBoltpayConfig('success', $checkoutType);
        $closeCustom = $boltHelper->getPaymentBoltpayConfig('close', $checkoutType);

        $onCheckCallback = $this->buildOnCheckCallback($checkoutType, $quote);
        $onSuccessCallback = $this->buildOnSuccessCallback($successCustom, $checkoutType);
        $onCloseCallback = $this->buildOnCloseCallback($closeCustom, $checkoutType);

        $requiredCheck = ($checkoutType === self::CHECKOUT_TYPE_FIRECHECKOUT)
            ? ""
            : "
                    if (!json_cart.orderToken) {
                        if (typeof BoltPopup !== \"undefined\") {
                            BoltPopup.addMessage(json_cart.error).show();
                        } else {
                            alert(json_cart.error);
                        }
                        return false;
                    }
            "
        ;

        $hintsTransformFunction = $boltHelper->getExtraConfig('hintsTransform');

        return ("
            var \$hints_transform = $hintsTransformFunction;
            
            var json_cart = $jsonCart;
            var json_hints = \$hints_transform($jsonHints);
            var quote_id = '{$quote->getId()}';
            var order_completed = false;

            BoltCheckout.configure(
                json_cart,
                json_hints,
                {
                  check: function() {
                    $requiredCheck
                    $checkCustom
                    $onCheckCallback
                    return true;
                  },
                  
                  onCheckoutStart: function() {
                    // This function is called after the checkout form is presented to the user.
                    $onCheckoutStartCustom
                  },
                  
                  onEmailEnter: function(email) {
                    // This function is called after the user enters their email address.
                    $onEmailEnterCustom
                  },
                  
                  onShippingDetailsComplete: function() {
                    // This function is called when the user proceeds to the shipping options page.
                    // This is applicable only to multi-step checkout.
                    $onShippingDetailsCompleteCustom
                  },
                  
                  onShippingOptionsComplete: function() {
                    // This function is called when the user proceeds to the payment details page.
                    // This is applicable only to multi-step checkout.
                    $onShippingOptionsCompleteCustom
                  },
                  
                  onPaymentSubmit: function() {
                    // This function is called after the user clicks the pay button.
                    $onPaymentSubmitCustom
                  },
                  
                  success: $onSuccessCallback,

                  close: function() {
                     $onCloseCallback
                  }
                }
        );");
    }

    /**
     * @param $checkoutType
     * @param $quote
     *
     * @return string
     */
    public function buildOnCheckCallback($checkoutType, $quote)
    {
        switch ($checkoutType) {
            case self::CHECKOUT_TYPE_ADMIN:
                return
                    "
                    if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
        
                        var is_valid = true;
        
                        if (!editForm.validate()) {
                            is_valid = false;
                        } ". ($quote->isVirtual() ? "" : " else {
                            var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                            if (typeof shipping_method === 'undefined') {
                                alert('".Mage::helper('boltpay')->__('Please select a shipping method.')."');
                                is_valid = false;
                            }
                        } "). "
        
                        bolt_hidden.classList.add('required-entry');
                        return is_valid;
                    }
                    ";
            case self::CHECKOUT_TYPE_FIRECHECKOUT:
                return
                    "
                    return (isFireCheckoutFormValid = checkout.validate());
                    ";
            default:
                return '';
        }
    }

    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($successCustom = '', $checkoutType)
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        return ($checkoutType === self::CHECKOUT_TYPE_ADMIN) ?
            "function(transaction, callback) {
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
            }"
            : "function(transaction, callback) {
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
    }

    /**
     * @param $closeCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnCloseCallback($closeCustom, $checkoutType)
    {
        $successUrl = Mage::helper('boltpay/url')->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $javascript = "";
        switch ($checkoutType) {
            case self::CHECKOUT_TYPE_ADMIN:
                return
                    "
                    if (order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                        $closeCustom
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
                        order.submit();
                    }
                    ";
            case self::CHECKOUT_TYPE_FIRECHECKOUT:
                $javascript =
                    "
                    isFireCheckoutFormValid = false;
                    initBoltButtons();
                    ";
            default:
                return $javascript.
                    "
                    if (order_completed) {
                        location.href = '$successUrl';
                    }
                    ";
        }
    }

    /**
     * Return the session object depending of checkout type.
     *
     * @param $checkoutType
     * @return Mage_Adminhtml_Model_Session_Quote|Mage_Checkout_Model_Session
     */
    protected function getSessionObject($checkoutType)
    {
        return ($checkoutType === self::CHECKOUT_TYPE_ADMIN) ?
            Mage::getSingleton('adminhtml/session_quote') :
            Mage::getSingleton('checkout/session')
        ;
    }

    /**
     * Get session quote regarding the checkout type
     *
     * @param $checkoutType
     * @return Mage_Sales_Model_Quote
     */
    protected function getSessionQuote($checkoutType)
    {
        // Admin and Store front use different session objects.  We get the appropriate one here.
        $session = $this->getSessionObject($checkoutType);

        return $session->getQuote();
    }

    /**
     * Get address data formatted as hints from the current quote or, if not set in quote, the
     * current customer's data.
     *
     * @param Mage_Sales_Model_Quote    $quote         Quote used to get customer data
     * @param string                    $checkoutType  'multi-page' | 'one-page' | 'admin'
     *
     * @return array        hints data
     */
    private function getAddressHints($quote, $checkoutType)
    {

        $session =  Mage::getSingleton('customer/session');
        $hints = array();

        /////////////////////////////////////////////////////////////////////////
        // Check if the quote shipping address is set,
        // otherwise use customer shipping address for logged in users.
        /////////////////////////////////////////////////////////////////////////
        $address = $quote->getShippingAddress();
        if (!$address || !$address->getStreet1()) {
            if ( $session && $session->isLoggedIn()) {
                /** @var Mage_Customer_Model_Customer $customer */
                $customer = Mage::getModel('customer/customer')->load($session->getId());
                $address = $customer->getPrimaryShippingAddress();
                $hints['email'] = $customer->getEmail();
            }
        }

        // If address value exists populate the hints array with existing address data.
        if ( $address instanceof Mage_Sales_Model_Quote_Address) {
            if ($address->getEmail())     $hints['email']        = $address->getEmail();
            if ($address->getFirstname()) $hints['firstName']    = $address->getFirstname();
            if ($address->getLastname())  $hints['lastName']     = $address->getLastname();
            if ($address->getStreet1())   $hints['addressLine1'] = $address->getStreet1();
            if ($address->getStreet2())   $hints['addressLine2'] = $address->getStreet2();
            if ($address->getCity())      $hints['city']         = $address->getCity();
            if ($address->getRegion())    $hints['state']        = $address->getRegion();
            if ($address->getPostcode())  $hints['zip']          = $address->getPostcode();
            if ($address->getTelephone()) $hints['phone']        = $address->getTelephone();
            if ($address->getCountryId()) $hints['country']      = $address->getCountryId();
        }

        if ($checkoutType === 'admin') {
            $hints['email'] = Mage::getSingleton('admin/session')->getOrderShippingAddress()['email'];
            $hints['virtual_terminal_mode'] = true;
        }

        return array( "prefill" => $hints );
    }

    /**
     * Gets the customer custom attribute, "bolt_user_id", if not set creates one by
     * fetching new Magento customer auto increment ID for the store.
     * Applies to logged in users or the users in the process of registration during the the checkout (checkout type is "register").
     *
     * @param Mage_Sales_Model_Quote  $quote   current Magento quote
     *
     * @return string|null  the ID used for the Bolt user, or null if the user is not logged in and is not on the onepage checkout page
     */
    public function getReservedUserId($quote)
    {

        $session = Mage::getSingleton('customer/session');
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
    public function getCssSuffix()
    {
        return self::CSS_SUFFIX;
    }

    /**
     * Reads the Replace Buttons Style config and generates selectors CSS
     * @return string
     */
    public function getSelectorsCSS()
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
    public function getAdditionalCSS()
    {
        return Mage::getStoreConfig('payment/boltpay/additional_css');
    }

    /**
     * Returns the Bolt Sandbox Mode configuration.
     * @return string
     */
    public function isTestMode()
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
    public function getSuccessURL()
    {
        return Mage::helper('boltpay/url')->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
    }

    /**
     * Returns the Bolt Save Order Url.
     * @return string
     */
    public function getSaveOrderURL()
    {
        return Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');
    }

    /**
     * Returns the Cart Url.
     * @return string
     */
    public function getCartURL()
    {
        return Mage::helper('boltpay/url')->getMagentoUrl('checkout/cart');
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
            //To receive the API results in the old freegeoip format, we need ipstack Access Key
            $ipstackAccessKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/ipstack_key'));
            if(!empty($ipstackAccessKey)){
               $locationInfo = $this->url_get_contents("http://api.ipstack.com/".$this->getIpAddress()."?access_key=".$ipstackAccessKey."&output=json&legacy=1");
               Mage::getSingleton('core/session')->setLocationInfo($locationInfo);
            }
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

        $isAllowed = ($routeName === 'checkout' && $controllerName === 'cart')
            || ($routeName == 'firecheckout')
            || ($routeName === 'adminhtml' && in_array($controllerName, array('sales_order_create', 'sales_order_edit')));

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

        $checkoutType = static::CHECKOUT_TYPE_MULTI_PAGE;
        if ($routeName === 'adminhtml') {
            $checkoutType = static::CHECKOUT_TYPE_ADMIN;
        } else if ( ($routeName === 'firecheckout') || ($routeName === 'checkout' && $controllerName === 'onepage') ) {
            $checkoutType = static::CHECKOUT_TYPE_ONE_PAGE;
        }

        return $this->getPublishableKey($checkoutType);
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
