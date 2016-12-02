<?php

class Bolt_Boltpay_Block_Checkout_Boltpay
    extends Mage_Checkout_Block_Onepage_Review_Info {

    const JS_URL_TEST = 'https://cdn-connect-staging.boltapp.com/connect.js';
    const JS_URL_PROD = 'https://connect.boltapp.com/connect.js';
    const AUTO_CAPTURE_ENABLED = 1;

    public function _construct() {
        parent::_construct();
        $this->_jsUrl = Mage::getStoreConfig('payment/boltpay/test') ?
            self::JS_URL_TEST:
            self::JS_URL_PROD;
    }

    public function getCartDataJs() {
        $customerSession = Mage::getSingleton('customer/session');
        $session = Mage::getSingleton('checkout/session');
        $onepage = Mage::getSingleton('checkout/type_onepage');
        $quote = $session->getQuote();

        $quote->reserveOrderId()->save();
        $reservedUserId = $this->getReservedUserId($quote, $customerSession, $onepage);
        $signResponse = null;

        if ($reservedUserId != null) {
            $boltHelper = Mage::helper('boltpay/api');
            $signRequest = array(
                'merchant_user_id' => $reservedUserId,
            );
            $signResponse = $boltHelper->handleErrorResponse($boltHelper->transmit('sign', $signRequest));
        }

        $billing = $quote->getBillingAddress();
        $shipping = $quote->getShippingAddress();
        $items = $this->getItems();
        $totals = $quote->getTotals();
        $shippingAddress = $quote->getShippingAddress();

        if (Mage::getStoreConfig('payment/boltpay/auto_capture') == self::AUTO_CAPTURE_ENABLED) {
            $authCapture = 'true';
        } else {
            $authCapture = 'false';
        }

        $productMediaConfig = Mage::getModel('catalog/product_media_config');

        $cart_data = array(
            'id' => $quote->getId(),
            'displayId' => $quote->getReservedOrderId(),
            'authcapture' => $authCapture,
            'items' => array_map(function($item) use($quote, $productMediaConfig) {
                $image_url = $productMediaConfig->getMediaUrl($item->getProduct()->getThumbnail());
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                $coreHelper = Mage::helper('core');
                return array(
                    'reference' => $quote->getId(),
                    'image' => $image_url,
                    'name' => $item->getName(),
                    'desc' => $product->getDescription(),
                    'price' => $coreHelper->currency($item->getPrice(), true, false),
                    'quantity' => $item->getQty()
                );
            }, $items),
        );

        if ($shippingAddress != null) {
            $tax = null;
            // WeltPixel has custom tax calculator which writes into a field field called taxjar_fee in shipping address
            if ($shippingAddress->getTaxjarFee() != 0) {
                $tax = $this->asMoney($shippingAddress->getTaxjarFee());
            } elseif ($shippingAddress->getTaxAmount() != 0) {
                $tax = $this->asMoney($shippingAddress->getTaxAmount());
            }

            if ($tax != null) {
                $cart_data['tax'] = array(
                    'name' => 'Tax',
                    'price' => $tax,
                );
            }
        }

        if (array_key_exists('shipping', $totals)) {
            $cart_data['shipping'] = array(
                'name' => 'Shipping',
                'price' => $this->asMoney($totals['shipping']->getValue()),
            );
        }

        if (array_key_exists('discount', $totals)) {
            $cart_data['discounts'] = array(array(
                'amount' => $this->asMoney($totals['discount']->getValue()),
                'description' => $totals['discount']->getTitle(),
            ));
        }

        if (array_key_exists('grand_total', $totals)) {
            $cart_data['total'] = $this->asMoney($totals['grand_total']->getValue());
        }

        $billingAddress = $billing->getStreet();
        $shippingAddress = $shipping->getStreet();

        $hint_data = array(
            'first_name' => $billing->getFirstname(),
            'last_name' => $billing->getLastname(),
            'phone' => $billing->getTelephone(),
            'email' => $billing->getEmail(),
            'billing' => array(
                'AddressLine1' => array_key_exists(0, $billingAddress) ? $billingAddress[0] : '',
                'AddressLine2' => array_key_exists(1, $billingAddress) ? $billingAddress[1] : '',
                'AddressLine3' => array_key_exists(2, $billingAddress) ? $billingAddress[2] : '',
                'AddressLine4' => array_key_exists(3, $billingAddress) ? $billingAddress[2] : '',
                'FirstName' => $billing->getFirstname(),
                'LastName' => $billing->getLastname(),
                'City' => $billing->getCity(),
                'State' => $billing->getRegion(),
                'Zip' => $billing->getPostcode(),
                'CountryCode' => $billing->getCountry(),
            ),
            'shipping' => array(
                'AddressLine1' => array_key_exists(0, $shippingAddress) ? $shippingAddress[0] : '',
                'AddressLine2' => array_key_exists(1, $shippingAddress) ? $shippingAddress[1] : '',
                'AddressLine3' => array_key_exists(2, $shippingAddress) ? $shippingAddress[2] : '',
                'AddressLine4' => array_key_exists(3, $shippingAddress) ? $shippingAddress[3] : '',
                'FirstName' => $shipping->getFirstname(),
                'LastName' => $shipping->getLastname(),
                'City' => $shipping->getCity(),
                'State' => $shipping->getRegion(),
                'Zip' => $shipping->getPostcode(),
                'CountryCode' => $shipping->getCountry(),
            ),
        );

        if ($signResponse != null) {
            $hint_data['signed_merchant_user_id'] = array(
                "merchant_user_id" => $signResponse->merchant_user_id,
                "signature" => $signResponse->signature,
                "nonce" => $signResponse->nonce,
            );
        }

        $key = $this->getUrl(
            'checkout/onepage/saveOrder',
            array('form_key' => Mage::getSingleton('core/session')->getFormKey())
        );
        $json_cart = json_encode($cart_data);
        $json_hints = json_encode($hint_data);
        $url = $this->getUrl('checkout/onepage/success');
        return (
            "var boltreview = new Review(
                '$key',
                '$url',
                $('checkout-agreements'),
                false
            );
            BoltConnect.process(
                $json_cart,
                $json_hints,
                {
                    close: function() {
                        boltreview.redirect();
                    },
                    success: function(transaction, callback) {
                        $('p_method_boltpay_reference').value = transaction.reference;
                        $('p_method_boltpay_transaction_status').value = transaction.status;
                        boltreview.save(callback);
                    }
                }
            );"
        );
    }

    public function asMoney($amount) {
        return Mage::helper('core')->currency($amount, true, false);
    }

    function getReservedUserId($quote, $session, $onepage) {
        $checkoutMethod = $onepage->getCheckoutMethod();

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

        return null;
    }
}
