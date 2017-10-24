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

        $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

        $request_json = file_get_contents('php://input');

        $request_pretty = json_encode(json_decode($request_json), JSON_PRETTY_PRINT);

        //Mage::log("SHIPPING REQUEST: " . $request_pretty, null, 'shipping_and_tax.log');

        $boltHelper = Mage::helper('boltpay/api');

        if (! $boltHelper->verify_hook($request_json, $hmac_header)) exit;

        $request_data = json_decode($request_json);

        $shipping_address = $request_data->shipping_address;

        $region = Mage::getModel('directory/region')->loadByName($shipping_address->region, $shipping_address->country_code)->getCode();

        $address_data = array(
            'email'      => $shipping_address->email,
            'firstname'  => $shipping_address->first_name,
            'lastname'   => $shipping_address->last_name,
            'street'     => $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
            'company'    => $shipping_address->company,
            'city'       => $shipping_address->locality,
            'region'     => $region,
            'postcode'   => $shipping_address->postal_code,
            'country_id' => $shipping_address->country_code,
            'telephone'  => $shipping_address->phone
        );

        $display_id = $request_data->cart->display_id;

        $quote = Mage::getModel('sales/quote')
            ->getCollection()
            ->addFieldToFilter('reserved_order_id', $display_id)
            ->getFirstItem();

        if ($quote->getCustomerId()) {

            $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
            $address =$customer->getPrimaryShippingAddress();

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

        //Mage::log("SHIPPING ADDRESS: " . print_r($quote->getShippingAddress()->getData(), true), null, 'shipping_and_tax.log');

        $billingAddress = $quote->getBillingAddress();

        $quote->getBillingAddress()->addData(array(
            'email'      => $billingAddress->getEmail() ?: $shipping_address->email,
            'firstname'  => $billingAddress->getFirstname() ?: $shipping_address->first_name,
            'lastname'   => $billingAddress->getLastname()  ?: $shipping_address->last_name,
            'street'     => $billingAddress->getStreet()    ?: $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
            'company'    => $billingAddress->getCompany()   ?: $shipping_address->company,
            'city'       => $billingAddress->getCity()      ?: $shipping_address->locality,
            'region'     => $billingAddress->getRegion()    ?: $region,
            'postcode'   => $billingAddress->getPostcode()  ?: $shipping_address->postal_code,
            'country_id' => $billingAddress->getCountryId() ?: $shipping_address->country_code,
            'telephone'  => $billingAddress->getTelephone() ?: $shipping_address->phone
        ))->save();

        $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()->save();

        $response = array(
            'shipping_options' => array(),
        );

        /*****************************************************************************************
         * Calculate tax
         *****************************************************************************************/
        $store = Mage::getModel('core/store')->load($quote->getStoreId());
        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        $shipping_tax_class_id = Mage::getStoreConfig('tax/classes/shipping_tax_class',$quote->getStoreId());
        $rate_request = $taxCalculationModel->getRateRequest($quote->getShippingAddress(), $quote->getBillingAddress(), $quote->getCustomerTaxClassId(), $store);

        $items = $quote->getAllItems();

        $total_tax = 0;  // this number is in BOLT format (i.e. in cents)

        foreach($items as $item) {
            $item_tax = $item->getPrice()*$item->getQty() * $taxCalculationModel->getRate($rate_request->setProductClassId($item->getProduct()->getTaxClassId()));
            $total_tax += $item_tax;
        }

        $tax_remain = $total_tax - round($total_tax);

        $total_tax = round($total_tax);

        $response['tax_result'] = array(
            "amount" => $total_tax
        );
        /*****************************************************************************************/


        /*****************************************************************************************
         * Calculate shipping and shipping tax
         *****************************************************************************************/
        $rates = $quote->getShippingAddress()->getAllShippingRates();

        foreach ($rates as $rate) {

            $price = $rate->getPrice();

            $is_tax_included = Mage::helper('tax')->shippingPriceIncludesTax();

            $tax_rate = $taxCalculationModel->getRate($rate_request->setProductClassId($shipping_tax_class_id));

            if ($is_tax_included) {

                $price_excluding_tax = $price / ( 1 +  $tax_rate / 100);

                $tax_amount = 100 * ($price - $price_excluding_tax);

                $price = $price_excluding_tax;

            } else {

                $tax_amount = $price * $tax_rate;
            }

            $option = array(
                "service"    => $rate->getCarrierTitle().' - '.$rate->getMethodTitle(),
                "cost"       => round(100 *  $price),
                "tax_amount" => round($tax_amount + $tax_remain)
            );

            $response['shipping_options'][] = $option;
        }
        /*****************************************************************************************/

        $key = Mage::getStoreConfig('payment/boltpay/management_key');
        $key = Mage::helper('core')->decrypt($key);

        $this->getResponse()->clearHeaders()
            ->setHeader('Content-type','application/json',true)
            ->setHeader('X-Merchant-Key', $key,true)
            ->setHeader('X-Nonce', rand(100000000, 999999999),true);

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}