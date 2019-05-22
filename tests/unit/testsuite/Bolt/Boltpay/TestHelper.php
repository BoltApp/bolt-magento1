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
        //Mage::init('default');
        /** @var Mage_Catalog_Model_Product $product */
        $product = Bolt_Boltpay_ProductProvider::getProductById($productId);//Mage::getModel('catalog/product')->load($productId);
        // Validate product
        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');
        $cart->init();
        $session = Mage::getSingleton('customer/session'); 
        $formKey = $session->getFormKey();
        
//         echo $product->getSku()."\n";
//         var_dump($formKey);
        $param = array(
            'product' => $productId,
            'qty' => $quantity,
            'form_key' => $formKey
        );
        $request = new Varien_Object();
        $request->setData($param);
        $cart->addProduct($product, $request);
//         $cart->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
//         $cart->save();

        
        
        $quote = Mage::getModel('checkout/session')->getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $quote->collectTotals()->save();
        $session->setCartWasUpdated(true);
        
        $cart->save();
        
        
        //Mage::getSingleton('checkout/session')->setCartWasUpdated(true);
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
        Mage::app('default')->getStore()->setConfig('carriers/flatrate/active', 1);

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

    /**
     * @param $checkoutType
     * @param $jsonCart
     * @param $quote
     * @param $jsonHints
     * @return string
     */
    public function buildCartDataJs($checkoutType, $jsonCart, $quote, $jsonHints)
    {
        /* @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');
        $quote->setIsVirtual(false);

        $hintsTransformFunction = $boltHelper->getExtraConfig('hintsTransform');
        $configCallbacks = $boltHelper->getBoltCallbacks($checkoutType, $quote);

        return ("
            var \$hints_transform = $hintsTransformFunction;
            
            var get_json_cart = function() { return $jsonCart };
            var json_hints = \$hints_transform($jsonHints);
            var quote_id = '{$quote->getId()}';
            var order_completed = false;
            var do_checks = 1;

            window.BoltModal = BoltCheckout.configure(
                get_json_cart(),
                json_hints,
                $configCallbacks
        );");
    }
}
