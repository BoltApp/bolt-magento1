<?php

class Bolt_Boltpay_TestHelper{
    public function addProduct($productId, $quantity) {
        $product = Mage::getModel('catalog/product')->load($productId);
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
     * $addressData = array(
     *     'firstname' => 'Vagelis',
     *     'lastname' => 'Bakas',
     *     'street' => 'Sample Street 10',
     *     'city' => 'Somewhere',
     *     'postcode' => '123456',
     *     'telephone' => '123456',
     *     'country_id' => 'US',
     *     'region_id' => 12, // id from directory_country_region table
     * );
     */
    public function addTestBillingAddress($addressData) {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getBillingAddress()->addData($addressData);
        $checkout->getQuote()->getBillingAddress()->save();
        return $checkout;
    }

    public function addTestFlatRateShippingAddress($addressData, $paymentMethod) {
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

    public function createCheckout($checkoutType) {
        Mage::unregister('_singleton/checkout/type_onepage');
        Mage::unregister('_singleton/checkout/cart');
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkoutSession = $checkout->getCheckout();
        $checkoutSession->clear();
        $checkout->initCheckout();
        $checkout->saveCheckoutMethod($checkoutType);
        return $checkout;
    }

    public function addPaymentToQuote($method) {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getPayment()->importData(array('method' => $method));
        $checkout->getQuote()->getPayment()->save();
        $checkout->getQuote()->collectTotals()->save();
        return $checkout;
    }


    public function submitCart() {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $service = Mage::getModel('sales/service_quote', $checkout->getQuote());
        $service->submitAll();
        return $service->getOrder();
    }

    public function resetApp() {
        $_POST = array();
        $_REQUEST = array();
        $_GET = array();
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }
}