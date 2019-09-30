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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
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
    use Bolt_Boltpay_BoltGlobalTrait;

    const CHECKOUT_TYPE_ADMIN       = 'admin';
    const CHECKOUT_TYPE_MULTI_PAGE  = 'multi-page';
    const CHECKOUT_TYPE_ONE_PAGE    = 'one-page';
    const CHECKOUT_TYPE_FIRECHECKOUT = 'firecheckout';
    const CHECKOUT_TYPE_PRODUCT_PAGE = 'product-page';
    const CSS_SUFFIX = 'bolt-css-suffix';

    /**
     * Set the connect javascript url to production or sandbox based on store config settings
     */
    public function _construct()
    {
        parent::_construct();
        $this->_jsUrl = $this->boltHelper()->getJsUrl() . "/connect.js";
    }

    /**
     * Get the track javascript url, production or sandbox, based on store config settings
     */
    public function getTrackJsUrl()
    {
        return $this->boltHelper()->getJsUrl() . "/track.js";
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

            $hintData = $this->getAddressHints($sessionQuote, $checkoutType);

            // Call Bolt create order API
            try {

                $cartData = Mage::getModel('boltpay/boltOrder')->getBoltOrderTokenPromise($checkoutType);

                if (@!$cartData->error) {
                    ///////////////////////////////////////////////////////////////////////////////////////
                    // Merchant scope: get "bolt_user_id" if the user is logged in or should be registered,
                    // sign it and add to hints.
                    ///////////////////////////////////////////////////////////////////////////////////////
                    $reservedUserId = $this->getReservedUserId($sessionQuote);
                    if ($reservedUserId && $this->isEnableMerchantScopedAccount()) {
                        $signRequest = array(
                            'merchant_user_id' => $reservedUserId,
                        );

                        $signResponse = $this->boltHelper()->transmit('sign', $signRequest);

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
                $this->boltHelper()->logException($e,$metaData);
                $this->boltHelper()->notifyException(
                    new Exception($e),
                    $metaData
                );
            }

            return $this->buildBoltCheckoutJavascript($checkoutType, $sessionQuote, $hintData, $cartData);

        } catch (Exception $e) {
            $this->boltHelper()->logException($e);
            $this->boltHelper()->notifyException($e);
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
     * @return array
     */
    public function buildCartData($orderCreationResponse)
    {
        //////////////////////////////////////////////////////////////////////////
        // Generate JSON cart and hints objects for the javascript returned below.
        //////////////////////////////////////////////////////////////////////////
        $cartData = array(
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
     * @param Mage_Sales_Model_Quote $quote
     * @param $hintData
     * @param $cartData
     * @return string
     */
    public function buildBoltCheckoutJavascript($checkoutType, $quote, $hintData, $cartData)
    {
        $filterParameters = array(
          'checkoutType' => $checkoutType,
          'quote' => $quote,
          'hintData' => $hintData,
          'cartData' => $cartData
        );

        $jsonCart = (is_string($cartData)) ? $cartData : json_encode($cartData);
        $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);

        $callbacks = $this->boltHelper()->getBoltCallbacks($checkoutType, $quote);
        $checkCustom = $this->boltHelper()->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckCallback = $this->boltHelper()->buildOnCheckCallback($checkoutType, $quote);

        $hintsTransformFunction = $this->boltHelper()->getExtraConfig('hintsTransform');
        $shouldCloneImmediately = !$this->boltHelper()->getExtraConfig( 'cloneOnClick' );

        $boltConfigureCall =
        "
            BoltCheckout.configure(
                get_json_cart(),
                json_hints,
                $callbacks  
            );
        ";

        switch ($checkoutType) {
            case self::CHECKOUT_TYPE_MULTI_PAGE:
                // if it is a multipage checkout from the shopping cart,
                // we will call configure immediately unless extra config 'cloneOnClick' overrides this
                if ($this->boltHelper()->isShoppingCartPage() && $shouldCloneImmediately) break;
            case self::CHECKOUT_TYPE_ONE_PAGE:
                if ($shouldCloneImmediately) break;
            case self::CHECKOUT_TYPE_ADMIN:
            case self::CHECKOUT_TYPE_FIRECHECKOUT:
                // We postpone calling configure until Bolt button clicked and form is ready
                // This also allows us to save in cost of unnecessary quote creation
                $doChecks = 'var do_checks = 0;';
                $boltConfigureCall = "
                    BoltCheckout.configure(
                        new Promise( 
                            function (resolve, reject) {
                                // Store state must be validated prior to open                          
                            }
                        ),
                        json_hints,
                        {
                            check: function() {
                                $checkCustom
                                $onCheckCallback
                                $boltConfigureCall
                                return true;
                            }
                        }
                    ); 
                ";
        }

        if (!isset($doChecks)) $doChecks = 'var do_checks = 1;';

        $boltCheckoutJavascript = "
            var \$hints_transform = $hintsTransformFunction;
            
            var get_json_cart = function() { return $jsonCart };
            var json_hints = \$hints_transform($jsonHints);
            var quote_id = '{$quote->getId()}';
            var order_completed = false;
            $doChecks
                
            window.BoltModal = $boltConfigureCall   
        ";

        return $this->boltHelper()->doFilterEvent('bolt_boltpay_filter_bolt_checkout_javascript', $boltCheckoutJavascript, $filterParameters);
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
    public function getSessionQuote($checkoutType)
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
        $prefill = array();

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
                $prefill['email'] = $customer->getEmail();
            }
        }

        // If address value exists populate the hints array with existing address data.
        if ($address instanceof Mage_Customer_Model_Address_Abstract) {
            if ($address->getEmail())     $prefill['email']        = $address->getEmail();
            if ($address->getFirstname()) $prefill['firstName']    = $address->getFirstname();
            if ($address->getLastname())  $prefill['lastName']     = $address->getLastname();
            if ($address->getStreet1())   $prefill['addressLine1'] = $address->getStreet1();
            if ($address->getStreet2())   $prefill['addressLine2'] = $address->getStreet2();
            if ($address->getCity())      $prefill['city']         = $address->getCity();
            if ($address->getRegion())    $prefill['state']        = $address->getRegion();
            if ($address->getPostcode())  $prefill['zip']          = $address->getPostcode();
            if ($address->getTelephone()) $prefill['phone']        = $address->getTelephone();
            if ($address->getCountryId()) $prefill['country']      = $address->getCountryId();
        }

        if ($checkoutType === 'admin') {
            $prefill['email'] = Mage::getSingleton('admin/session')->getOrderShippingAddress()['email'];
            $hints['virtual_terminal_mode'] = true;
        }

        // Skip pre-fill for Apple Pay related data.
        if (!(@$prefill['email'] == 'fake@email.com' || @$prefill['phone'] == '1111111111')) {
            $hints['prefill'] = $prefill;
        }

        return $hints;
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
     * Additional JS that is to be added on any page where the Bolt Button is rendered
     *
     * @return string   Well formed Javascript to typically be placed within a <script> tag
     */
    public function getAdditionalJs()
    {
        return Mage::getStoreConfig('payment/boltpay/additional_js');
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
     * Return disabled customer groups for Bolt
     * @return array
     */
    public function disableCustomerGroups()
    {
        return explode(',', Mage::getStoreConfig('payment/boltpay/bolt_disabled_customer_groups'));
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
        return $this->boltHelper()->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
    }

    /**
     * Returns the Bolt Save Order Url.
     * @return string
     */
    public function getSaveOrderURL()
    {
        return $this->boltHelper()->getMagentoUrl('boltpay/order/save');
    }

    /**
     * Returns the Cart Url.
     * @return string
     */
    public function getCartURL()
    {
        return $this->boltHelper()->getMagentoUrl('checkout/cart');
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
        switch ($checkoutType) {
            case 'multi-page':
            case 'multipage':
                return $this->boltHelper()->getPublishableKeyMultiPage();
            case 'back-office':
            case 'backoffice':
            case 'admin':
                return $this->boltHelper()->getPublishableKeyBackOffice();
            case 'one-page':
            case 'onepage':
            default:
                return $this->boltHelper()->getPublishableKeyOnePage();
        }
    }

    /**
     * Returns the Enabled Bolt configuration option value.
     * @return bool
     */
    public function isBoltActive()
    {
        return $this->boltHelper()->isBoltPayActive();
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
        return $this->boltHelper()->canUseBolt($this->getQuote(), false);
    }

    /**
     * Checking if allows to insert connectjs or replace by route name
     *
     * @return bool
     * @throws Exception
     */
    private function isAllowedOnCurrentPageByRoute()
    {
        $isAllowed = false;
        $quote = $this->getQuote();
        $customerGroupId = $quote->getCustomerGroupId();
        $disabledGroups = $this->disableCustomerGroups();
        if ($customerGroupId && in_array($customerGroupId, $disabledGroups)) {
            return $isAllowed;
        }
        $routeName = $this->getRequest()->getRouteName();
        $controllerName = $this->getRequest()->getControllerName();

        $isEnabledProductPageCheckout = $this->boltHelper()->isEnabledProductPageCheckout();

        $customRoutes = $this->boltHelper()->getAllowedButtonByCustomRoutes();

        $isAllowed = (in_array($routeName, $customRoutes) || ($routeName === 'checkout' && $controllerName === 'cart'))
            || ($routeName === 'firecheckout')
            || ($isEnabledProductPageCheckout && $routeName === 'catalog' && $controllerName === 'product')
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
        $canAddEverywhere = $this->boltHelper()->canUseEverywhere();

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
