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


            $response = array(
                'shipping_options' => array(),
            );

            /*****************************************************************************************
             * Calculate tax
             *****************************************************************************************/
            $quote->getShippingAddress()->setShippingMethod(null);
            $quote->collectTotals();
            $totals = $quote->getTotals();

            $response['tax_result'] = array(
                "amount" => @$totals['tax'] ? round($totals['tax']->getValue() * 100) : 0
            );
            /*****************************************************************************************/


            /*****************************************************************************************
             * Calculate shipping and shipping tax
             *****************************************************************************************/
            $store = Mage::getModel('core/store')->load($quote->getStoreId());
            $taxCalculationModel = Mage::getSingleton('tax/calculation');
            $shipping_tax_class_id = Mage::getStoreConfig('tax/classes/shipping_tax_class', $quote->getStoreId());
            $rate_request = $taxCalculationModel->getRateRequest($quote->getShippingAddress(), $quote->getBillingAddress(), $quote->getCustomerTaxClassId(), $store);

            $shipping_address = $quote->getShippingAddress();
            $shipping_address->setCollectShippingRates(true)->collectShippingRates()->save();

            $rates = $shipping_address->getAllShippingRates();

            //////////////////////////////////////////////////////////////////////////////////
            //  Support for Onepica Avatax plugin
            //////////////////////////////////////////////////////////////////////////////////
            $onepica_avatax = null;
            foreach ($shipping_address->getTotalCollector()->getCollectors() as $model) {
                if (get_class($model) == 'OnePica_AvaTax_Model_Sales_Quote_Address_Total_Tax') {
                    $onepica_avatax = $model;
                }
            }

            if ($onepica_avatax) {
                $avatax_tax_rate = 0;
                $summary = Mage::getModel('avatax/avatax_estimate')->getSummary($shipping_address->getId());

                foreach ($summary as $tax) {
                    $avatax_tax_rate += $tax['rate'];
                }
                Mage::log('ShippingController.php: summary:'.var_export($summary, true), null, 'shipping_and_tax.log');
            }
            //////////////////////////////////////////////////////////////////////////////////


            foreach ($rates as $rate) {

                $shipping_address->setShippingMethod($rate->getMethod())->save();

                $price = $rate->getPrice();

                $is_tax_included = Mage::helper('tax')->shippingPriceIncludesTax();

                $tax_rate = @$avatax_tax_rate ? $avatax_tax_rate : $taxCalculationModel->getRate($rate_request->setProductClassId($shipping_tax_class_id));

                if ($is_tax_included) {

                    $price_excluding_tax = $price / (1 + $tax_rate / 100);

                    $tax_amount = 100 * ($price - $price_excluding_tax);

                    $price = $price_excluding_tax;

                } else {

                    $tax_amount = $price * $tax_rate;
                }

                $cost = round(100 * $price);

                $option = array(
                    "service" => $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(),
                    "cost" => $cost,
                    "tax_amount" => abs(round($tax_amount))
                );

                $response['shipping_options'][] = $option;
            }
            /*****************************************************************************************/



            $key = Mage::getStoreConfig('payment/boltpay/api_key');
            $key = Mage::helper('core')->decrypt($key);

            $response = json_encode($response, JSON_PRETTY_PRINT);

            //Mage::log('SHIPPING AND TAX RESPONSE: ' . $response, null, 'shipping_and_tax.log');

            $this->getResponse()->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Merchant-Key', $key, true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            $this->getResponse()->setBody($response);

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }
}