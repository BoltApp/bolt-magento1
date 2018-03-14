<?php

/**
 * Class Bolt_Boltpay_ShippingController
 *
 * Shipping And Tax endpoint.
 */
class Bolt_Boltpay_ShippingController extends Mage_Core_Controller_Front_Action {

    /**
     * Receives json formated request from Bolt,
     * containing cart identifier and the address entered in the checkout popup.
     * Responds with available shipping options and calculated taxes
     * for the cart and address specified.
     */
    public function indexAction() {

        try {
            $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json);

            //Mage::log('SHIPPING AND TAX REQUEST: ' . json_encode($request_data, JSON_PRETTY_PRINT), null, 'shipping_and_tax.log');

            $boltHelper = Mage::helper('boltpay/api');
            if (!$boltHelper->verify_hook($request_json, $hmac_header)) exit;

            $shipping_address = $request_data->shipping_address;

            $region = Mage::getModel('directory/region')->loadByName($shipping_address->region, $shipping_address->country_code)->getCode();

            $address_data = array(
                'email' => $shipping_address->email,
                'firstname' => $shipping_address->first_name,
                'lastname' => $shipping_address->last_name,
                'street' => $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
                'company' => $shipping_address->company,
                'city' => $shipping_address->locality,
                'region' => $region,
                'postcode' => $shipping_address->postal_code,
                'country_id' => $shipping_address->country_code,
                'telephone' => $shipping_address->phone
            );

            $display_id = $request_data->cart->display_id;

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $display_id)
                ->getFirstItem();

            /***********************/
            # Set session quote to real customer quote
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($quote->getId());
            /**************/

            if ($quote->getCustomerId()) {

                $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
                $address = $customer->getPrimaryShippingAddress();

                if (!$address) {
                    $address = Mage::getModel('customer/address');

                    $address->setCustomerId($customer->getId())
                        ->setCustomer($customer)
                        ->setIsDefaultShipping('1')
                        ->setSaveInAddressBook('1')
                        ->save();


                    $address->addData($address_data);
                    $address->save();

                    $customer->addAddress($address)
                        ->setDefaultShippingg($address->getId())
                        ->save();
                }
            }

            $quote->removeAllAddresses();
            $quote->save();
            $quote->getShippingAddress()->addData($address_data)->save();

            $billingAddress = $quote->getBillingAddress();

            $quote->getBillingAddress()->addData(array(
                'email' => $billingAddress->getEmail() ?: $shipping_address->email,
                'firstname' => $billingAddress->getFirstname() ?: $shipping_address->first_name,
                'lastname' => $billingAddress->getLastname() ?: $shipping_address->last_name,
                'street' => implode("\n", $billingAddress->getStreet()) ?: $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
                'company' => $billingAddress->getCompany() ?: $shipping_address->company,
                'city' => $billingAddress->getCity() ?: $shipping_address->locality,
                'region' => $billingAddress->getRegion() ?: $region,
                'postcode' => $billingAddress->getPostcode() ?: $shipping_address->postal_code,
                'country_id' => $billingAddress->getCountryId() ?: $shipping_address->country_code,
                'telephone' => $billingAddress->getTelephone() ?: $shipping_address->phone
            ))->save();

            ////////////////////////////////////////////////////////////////////////////////////////
            // Check session cache for estimate.  If the shipping city or postcode, and the country code match,
            // then use the cached version.  Otherwise, we have to do another calculation
            ////////////////////////////////////////////////////////////////////////////////////////
            $cached_address = unserialize(Mage::app()->getCache()->load("quote_location_".$quote->getId()));

            if ( $cached_address && ((strtoupper($cached_address["city"]) == strtoupper($address_data["city"])) || ($cached_address["postcode"] == $address_data["postcode"])) && ($cached_address["country_id"] == $address_data["country_id"])) {
                //Mage::log('Using cached address: '.var_export($cached_address, true), null, 'shipping_and_tax.log');
                $response = unserialize(Mage::app()->getCache()->load("quote_shipping_and_tax_estimate_".$quote->getId()));
            } else {
                //Mage::log('Generating address from quote', null, 'shipping_and_tax.log');
                //Mage::log('Live address: '.var_export($address_data, true), null, 'shipping_and_tax.log');
                $response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
            }
            ////////////////////////////////////////////////////////////////////////////////////////

            $response = json_encode($response, JSON_PRETTY_PRINT);

            //Mage::log('SHIPPING AND TAX RESPONSE: ' . $response, null, 'shipping_and_tax.log');

            $this->getResponse()->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            $this->getResponse()->setBody($response);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Prefetches and stores the shipping and tax estimate and stores it in the session
     *
     * This expects to receive JSON with the values:
     *        city, region_code, zip_code, country_code
     */
    function prefetchEstimateAction() {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $cached_quote_location = Mage::app()->getCache()->load("quote_location_".$quote->getId());
        ////////////////////////////////////////////////////////////////////////
        // Clear previously set estimates.  This helps if this
        // process fails or is aborted, which will force the actual index action
        // to get fresh data instead of reading from the session cache
        ////////////////////////////////////////////////////////////////////////
        Mage::app()->getCache()->remove("quote_location_".$quote->getId());
        Mage::app()->getCache()->remove("quote_shipping_and_tax_estimate_".$quote->getId());
        ////////////////////////////////////////////////////////////////////////

        $request_json = file_get_contents('php://input');
        $request_data = json_decode($request_json);

        $address_data = array(
            'city' => $request_data->city,
            'region' => $request_data->region_code,
            'postcode' => $request_data->zip_code,
            'country_id' => $request_data->country_code
        );
        $quote->getShippingAddress()->addData($address_data);
        $quote->getShippingAddress()->addData($address_data);

        try {
            $estimate_response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
        } catch (Exception $e) {
            $estimate_response = null;
        }

        Mage::app()->getCache()->save(serialize($address_data), "quote_location_".$quote->getId());
        Mage::app()->getCache()->save(serialize($estimate_response), "quote_shipping_and_tax_estimate_".$quote->getId());
    }
}