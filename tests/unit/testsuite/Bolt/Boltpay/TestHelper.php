<?php

class Bolt_Boltpay_TestHelper
{
    /**
     * @param $productId
     * @param $quantity
     * @return Mage_Checkout_Model_Cart
     * @throws Exception
     */
    public function addProduct($productId, $quantity)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($productId);
        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');
        $param = array(
            'product' => $productId,
            'qty' => $quantity
        );
        $cart->addProduct($product, $param);
        $cart->save();

        return $cart;
    }

    /**
     * @param array $addressData
     * @return Mage_Checkout_Model_Type_Onepage
     * @throws Exception
     */
    public function addTestBillingAddress($addressData = array())
    {
        if (!count($addressData)) {
            $addressData = array(
                'firstname' => 'Luke',
                'lastname' => 'Skywalker',
                'street' => 'Sample Street 10',
                'city' => 'Los Angeles',
                'postcode' => '90014',
                'telephone' => '+1 867 345 123 5681',
                'country_id' => 'US',
                'region_id' => 12
            );
        }
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getBillingAddress()->addData($addressData);
        $checkout->getQuote()->getBillingAddress()->save();

        return $checkout;
    }

    public function addTestFlatRateShippingAddress($addressData, $paymentMethod)
    {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $shippingAddress = $checkout->getQuote()->getShippingAddress()->addData($addressData);
        $shippingAddress
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate')
            ->collectShippingRates()
            ->setPaymentMethod($paymentMethod);
        $checkout->getQuote()->getShippingAddress()->save();
        return $checkout;
    }

    /**
     * @param $checkoutType
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function createCheckout($checkoutType)
    {
        Mage::unregister('_singleton/checkout/type_onepage');
        Mage::unregister('_singleton/checkout/cart');
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkoutSession = $checkout->getCheckout();
        $checkoutSession->clear();
        $checkout->initCheckout();
        $checkout->saveCheckoutMethod($checkoutType);

        return $checkout;
    }

    public function addPaymentToQuote($method)
    {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getPayment()->importData(array('method' => $method));
        $checkout->getQuote()->getPayment()->save();
        $checkout->getQuote()->collectTotals()->save();

        return $checkout;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getCheckoutQuote()
    {
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');

        return $checkout->getQuote();
    }

    /**
     * @return mixed
     */
    public function submitCart()
    {
        $checkoutQuote = $this->getCheckoutQuote();
        $service = Mage::getModel('sales/service_quote', $checkoutQuote);
        $service->submitAll();

        return $service->getOrder();
    }

    public function resetApp()
    {
        $_POST = array();
        $_REQUEST = array();
        $_GET = array();
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }

    public function buildCartDataJs($jsonCart, $immutableQuoteId, $jsonHints, $callbacks = array())
    {
        $checkCustom = (isset($callbacks['checkCustom'])) ? $callbacks['checkCustom'] : '';
        $onCheckCallback = (isset($callbacks['onCheckCallback'])) ? $callbacks['onCheckCallback'] : '';
        $onCheckoutStartCustom = (isset($callbacks['onCheckoutStartCustom'])) ? $callbacks['onCheckoutStartCustom'] : '';
        $onShippingDetailsCompleteCustom = (isset($callbacks['onShippingDetailsCompleteCustom'])) ? $callbacks['onShippingDetailsCompleteCustom'] : '';
        $onShippingOptionsCompleteCustom = (isset($callbacks['onShippingOptionsCompleteCustom'])) ? $callbacks['onShippingOptionsCompleteCustom'] : '';
        $onPaymentSubmitCustom = (isset($callbacks['onPaymentSubmitCustom'])) ? $callbacks['onPaymentSubmitCustom'] : '';
        $onSuccessCallback = (isset($callbacks['onSuccessCallback'])) ? $callbacks['onSuccessCallback'] : '';
        $onCloseCallback = (isset($callbacks['onCloseCallback'])) ? $callbacks['onCloseCallback'] : '';

        /* @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');
        $hintsTransformFunction = $boltHelper->getExtraConfig('hintsTransform');

        $boltConfigureCall =
         "
            BoltCheckout.configure(
                json_cart,
                json_hints,
                {
                  check: function() {
                    $checkCustom
                    $onCheckCallback
                    return true;
                  },
                  
                  onCheckoutStart: function() {
                    // This function is called after the checkout form is presented to the user.
                    $onCheckoutStartCustom
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
            );
        ";

        return
        ("
            var \$hints_transform = $hintsTransformFunction;
            
            var json_cart = $jsonCart;
            var json_hints = \$hints_transform($jsonHints);
            var order_completed = false;
            var configure_bolt = function() {
                $boltConfigureCall
            };

            BoltCheckout.open = function() {
                document.getElementsByClassName('bolt-checkout-button-button')[0].click();
            };
            
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
                        configure_bolt();
                        BoltCheckout.open();
                    }
                }
            );
        "
        );
    }
}
