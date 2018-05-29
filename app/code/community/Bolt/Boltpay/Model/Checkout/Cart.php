<?php

class Bolt_Boltpay_Model_Checkout_Cart extends Mage_Core_Model_Abstract
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    private $_quote;

    private function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    private function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        }

        return $this->_quote;
    }

    /**
     * @return Bolt_Boltpay_Helper_Api
     */
    private function getBoltApiHelper()
    {
        return Mage::helper('boltpay/api');
    }

    /**
     * @param $storeID
     * @return string
     * @throws Exception
     */
    private function fetchNewIncrementIdCustomer($storeID)
    {
        /** @var Mage_Eav_Model_Config $eavConfig */
        $eavConfig = Mage::getSingleton('eav/config');

        $customerId = $eavConfig->getEntityType("customer")
            ->fetchNewIncrementId($storeID);

        return $customerId;
    }

    /**
     * Gets the customer custom attribute, "bolt_user_id", if not set creates one by
     * fetching new Magento customer auto increment ID for the store.
     * Applies to logged in users or the users in the process of registration during the the checkout (checkout type is "register").
     *
     * @param $quote        - Magento quote object
     * @param $session  Mage_Customer_Model_Session     - Magento customer/session object
     * @return string|null  - the ID used for the Bolt user, or null if the user is not logged in and is not on the onepage checkout page
     * @throws Exception
     */
    private function getReservedUserId($quoteStoreId, $session)
    {
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');

        $checkoutMethod = $checkout->getCheckoutMethod();

        if ($session->isLoggedIn()) {
//            $customer = Mage::getModel('customer/customer')->load($session->getId());
            $customer = $session->getCustomer();

            if ($customer->getBoltUserId() == 0 || $customer->getBoltUserId() == null) {
                //Mage::log("Creating new user id for logged in user", null, 'bolt.log');

//                $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quote->getStoreId());
                $custId = $this->fetchNewIncrementIdCustomer($quoteStoreId);
                $customer->setBoltUserId($custId);
                $customer->save();
            }

            //Mage::log(sprintf("Using Bolt User Id: %s", $customer->getBoltUserId()), null, 'bolt.log');
            return $customer->getBoltUserId();
        } else if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            //Mage::log("Creating new user id for Register checkout", null, 'bolt.log');
            $custId = Mage::getSingleton('eav/config')->getEntityType("customer")->fetchNewIncrementId($quoteStoreId);
            $session->setBoltUserId($custId);
            return $custId;
        }
    }

    public function getCartData($multipage = true)
    {
        try {
            // Get customer and cart session objects
//            $customerSession = $this->getCustomerSession();
//            $session = $this->getCheckoutSession();

            // Get the session quote/cart
            /** @var Mage_Sales_Model_Quote $quote */
            $quote = $this->getQuote();
            $quote->setExtShippingInfo(Mage::getSingleton('core/session')->getSessionId());
            $quote->save();
            // Generate new increment order id and associate it with current quote, if not already assigned
            $quote->reserveOrderId()->save();

///////////////////////////////////////////////////////////////
            // Populate hints data from quote or customer shipping address.
            //////////////////////////////////////////////////////////////
//            $hint_data = $this->getAddressHints($customerSession, $quote);
            $hintData = $this->getHintsData($quote);
            ///////////////////////////////////////////////////////////////


            $authCapture = $this->isAuthCapture();

            $shippingMethod = '';
            if($multipage) {
                // Resets shipping rate
                $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
                $this->getBoltApiHelper()->applyShippingRate($quote, null);
            }

            // Call Bolt create order API
            try {
                $orderCreationResponse = $this->createBoltOrder($quote, $multipage);
            } catch (Exception $e) {
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception($e));
                $orderCreationResponse = json_decode('{"token" : ""}');
            }

            if($multipage && $shippingMethod) {
                $this->getBoltApiHelper()->applyShippingRate($quote, $shippingMethod);
            }

            //////////////////////////////////////////////////////////////////////////
            // Generate JSON cart and hints objects for the javascript returned below.
            //////////////////////////////////////////////////////////////////////////
            $cartData = array(
                'authcapture' => $authCapture,
                'orderToken' => $orderCreationResponse->token,
            );

            if (Mage::registry("api_error")) {
                $cartData['error'] = Mage::registry("api_error");
            }



            //////////////////////////////////////////////////////
            // Generate and return BoltCheckout javascript.
            //////////////////////////////////////////////////////
            return $this->generateParamsForCart($cartData, $hintData);
            //////////////////////////////////////////////////////
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * @param $cartData
     * @param $hintData
     * @return string
     */
    public function generateBoltCheckoutJavascript($cartData, $hintData)
    {
        $quoteId = $this->getQuote()->getId();

        $jsonCart = json_encode($cartData);
        $jsonHints = '{}';
        if (sizeof($cartData) != 0) {
            // Convert $hint_data to object, because when empty data it consists array not an object
            $jsonHints = json_encode($hintData, JSON_FORCE_OBJECT);
        }

        //////////////////////////////////////////////////////////////////////////
        // Format the success and save order urls for the javascript returned below.
        $success_url    = Mage::getUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $save_order_url = Mage::getUrl('boltpay/order/save');

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

        return ("
                var json_cart = $jsonCart;
                var quote_id = '{$quoteId}';
                var order_completed = false;
                
                BoltCheckout.configure(
                    json_cart,
                    $jsonHints,
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
    }

    /**
     * @param $cartData
     * @param $hintData
     * @return array
     */
    public function generateParamsForCart($cartData, $hintData)
    {
        $quoteId = $this->getQuote()->getId();

        $cartDataResult = array(
            'boltCart' => $cartData,
            'quoteId' => $quoteId,
            'orderCompleted' => false,
            'hintData' => $hintData
        );

        return $cartDataResult;
    }

    /**
     * @param $quote
     * @throws Exception
     */
    public function getHintsData($quote)
    {
        $customerSession = $this->getCustomerSession();
        $quoteStoreID = $quote->getStoreId();

        ///////////////////////////////////////////////////////////////////////////////////////
        // Merchant scope: get "bolt_user_id" if the user is logged in or should be registered,
        // sign it and add to hints.
        ///////////////////////////////////////////////////////////////////////////////////////
        $reservedUserId = $this->getReservedUserId($quoteStoreID, $customerSession);
        $signResponse = null;

        if ($reservedUserId) {
            $signRequest = array(
                'merchant_user_id' => $reservedUserId,
            );
            $signResponse = $this->getBoltApiHelper()->transmit('sign', $signRequest);
        }

        if ($signResponse != null) {
            $hint_data['signed_merchant_user_id'] = array(
                "merchant_user_id" => $signResponse->merchant_user_id,
                "signature" => $signResponse->signature,
                "nonce" => $signResponse->nonce,
            );
        }
    }

    /**
     * Creates an order on Bolt end
     *
     * @param Mage_Sales_Model_Quote $quote     Magento quote object which represents order/cart data
     * @param bool                   $multipage Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return mixed json based PHP object
     * @throws Mage_Core_Exception
     */
    private function createBoltOrder($quote, $multipage)
    {
        $items = $quote->getAllVisibleItems();
        if (empty($items)) return json_decode('{"token" : ""}');

        // Generates order data for sending to Bolt create order API.
        $order_request = $this->getBoltApiHelper()->buildOrder($quote, $items, $multipage);

        //Mage::log("order_request: ". var_export($order_request, true), null,"bolt.log");

        // Calls Bolt create order API
        return  $this->getBoltApiHelper()->transmit('orders', $order_request);
    }

    /**
     * @return bool
     */
    public function isAuthCapture()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/auto_capture');
    }
}
