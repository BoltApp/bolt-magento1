<?php
/**
 * Class Bolt_Boltpay_Helper_Api
 *
 * The Magento Helper class that provides utility methods for the following operations:
 *
 * 1. Fetching the transaction info by calling the Fetch Bolt API endpoint.
 * 2. Verifying Hook Requests.
 * 3. Saves the order in Magento system.
 * 4. Makes the calls towards Bolt API.
 * 5. Generates Bolt order submission data.
 */
class Bolt_Boltpay_Helper_Api extends Bolt_Boltpay_Helper_Data {

    const API_URL_TEST = 'https://api-sandbox.bolt.com/';
    const API_URL_PROD = 'https://api.bolt.com/';

    ///////////////////////////////////////////////////////
    // Store discount types, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    ///////////////////////////////////////////////////////
    private $discount_types = array(
        'discount',
        'giftcardcredit',
        'giftcardcredit_after_tax',
        'giftvoucher',
        'giftvoucher_after_tax',
        'aw_storecredit',
    );
    ///////////////////////////////////////////////////////

    /**
     * A call to Fetch Bolt API endpoint. Gets the transaction info.
     *
     * @param string $reference        Bolt transaction reference
     * @param int $tries
     *
     * @throws Mage_Core_Exception     thrown if multiple (3) calls fail
     * @return bool|mixed Transaction info
     */
    public function fetchTransaction($reference, $tries = 3) {
        try {
            return $this->transmit($reference, null);
        } catch (Exception $e) {
            if (--$tries == 0) {
                $message = "BoltPay Gateway error: Fetch Transaction call failed multiple times for transaction referenced: $reference";
                Mage::helper('boltpay/bugsnag')->notifyException($e);
                throw $e;
            }
            return $this->fetchTransaction($reference, $tries);
        }
    }

    /**
     * Verifying Hook Requests using pre-exchanged signing secret key.
     *
     * @param $payload
     * @param $hmac_header
     * @return bool
     */
    private function verify_hook_secret($payload, $hmac_header) {

        $signing_secret = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/signing_key'));
        $computed_hmac  = trim(base64_encode(hash_hmac('sha256', $payload, $signing_secret, true)));

        return $hmac_header == $computed_hmac;
    }

    /**
     * Verifying Hook Requests via API call.
     *
     * @param $payload
     * @param $hmac_header
     * @return bool
     * @throws Exception
     */
    private function verify_hook_api($payload, $hmac_header) {

        try {

            $url = $this->getApiUrl() . "/v1/merchant/verify_signature";

            $key = Mage::helper('core')->decrypt( Mage::getStoreConfig('payment/boltpay/api_key') );

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "X-Api-Key: $key",
                "X-Bolt-Hmac-Sha256: $hmac_header",
                "Content-type: application/json",
            ));

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            curl_exec($ch);

            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $response == 200;

        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            return false;
        }

    }

    /**
     * Verifying Hook Requests. If signing secret is not defined fallback to api call.
     *
     * @param $payload
     * @param $hmac_header
     * @return bool
     */
    public function verify_hook($payload, $hmac_header) {

        return $this->verify_hook_secret($payload, $hmac_header) || $this->verify_hook_api($payload, $hmac_header);
    }

    /**
     * Processes order creation. Called from both frontend and API.
     *
     * @param $reference                Bolt transaction reference
     * @param null $session_quote_id    Session quote id, if triggered from frontend
     * @return mixed                    Order on successful creation
     * @throws Exception
     */
    public function createOrder($reference, $session_quote_id = null) {

        if (empty($reference)) {
          throw new Exception("Bolt transaction reference is missing in the Magento order creation process.");
        }

        // fetch transaction info
        $transaction = $this->fetchTransaction($reference);

        $transactionStatus = $transaction->status;

        // shipping carrier set up during checkout
        $service = $transaction->order->cart->shipments[0]->service;

        $quote_id = $transaction->order->cart->order_reference;

        // check if the quotes matches, frontend only
        if ($session_quote_id && $session_quote_id != $quote_id) {
            return false;
        }

        $display_id = $transaction->order->cart->display_id;

        $quote = Mage::getModel('sales/quote')
            ->getCollection()
            ->addFieldToFilter('reserved_order_id', $display_id)
            ->getFirstItem();

        // adding guest user email to order
        if (!$quote->getCustomerEmail()) {
            $email = $transaction->from_credit_card->billing_address->email_address;
            $quote->setCustomerEmail($email);
            $quote->save();
        }

        $quote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
        $quote->getBillingAddress()->setShouldIgnoreValidation(true)->save();

        /********************************************************************
         * Setting up shipping method by finding the carrier code that matches
         * the one set during checkout
         ********************************************************************/
        $shipping_address = $quote->getShippingAddress();
        $shipping_address->setCollectShippingRates(true)->collectShippingRates();
        $rates = $shipping_address->getAllShippingRates();

        foreach ($rates as $rate) {
            if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() == $service) {

                $shippingMethod = $rate->getCarrier() . '_' . $rate->getMethod();
                $quote->getShippingAddress()->setShippingMethod($shippingMethod)->save();
                break;
            }
        }

        // setting Bolt as payment method
        $quote->getShippingAddress()->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
        $payment = $quote->getPayment();
        $payment->setMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE);

        // adding transaction data to payment instance
        $payment->setAdditionalInformation('bolt_transaction_status', $transactionStatus);
        $payment->setAdditionalInformation('bolt_reference', $reference);
        $payment->setAdditionalInformation('bolt_merchant_transaction_id', $transaction->id);
        $payment->setTransactionId($transaction->id);

        $quote->collectTotals()->save();

        // a call to internal Magento service for order creation
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        $order = $service->getOrder();

        // deactivate quote
        $quote->setIsActive(false);
        $quote->save();

        Mage::getModel('boltpay/payment')->handleOrderUpdate($order);

        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order'=>$order, 'quote'=>$quote));

        return $order;

    }

    /**
     * Calls the Bolt API endpoint.
     *
     * @param string $command  The endpoint to be called
     * @param string $data     an object to be encoded to JSON as the value passed to the endpoint
     * @param string $object   defines part of endpoint url which is normally/always??? set to merchant
     * @param string $type     Defines the endpoint type (i.e. order|transactions|sign) that is used as part of the url
     * @throws  Mage_Core_Exception  thrown if an error is detected in a response
     * @return mixed           Object derived from Json got as a response
     */
    public function transmit($command, $data, $object='merchant', $type='transactions') {
        $url = $this->getApiUrl() . 'v1/';

        if($command == 'sign') {
            $url .= $object . '/' . $command;
        } elseif ($command == null || $command == '') {
            $url .= $object;
        } elseif ($command == 'orders') {
            $url .= $object . '/' . $command;
        } else {
            $url .= $object . '/' . $type . '/' . $command;
        }
        //Mage::log(sprintf("Making an API call to %s", $url), null, 'bolt.log');

        $ch = curl_init($url);
        $params = "";
        if ($data != null) {
            $params = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        if ($command == 'oauth' && $type == 'division') {
            $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        } elseif ($command == '' && $type == '' && $object == 'merchant') {
            $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        } elseif ($command == 'sign') {
            $key = Mage::getStoreConfig('payment/boltpay/api_key');
        } else {
            $key = Mage::getStoreConfig('payment/boltpay/api_key');
        }

        //Mage::log('KEY: ' . Mage::helper('core')->decrypt($key), null, 'bolt.log');

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'X-Api-Key: ' . Mage::helper('core')->decrypt($key),
            'X-Nonce: ' . rand(100000000, 999999999),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if ($result === false) {
            $curl_info = var_dump(curl_getinfo($ch));
            $curl_err = curl_error($ch);
            curl_close($ch);

            $message ="Curl info: " . $curl_info;

            //Mage::log($message, null, 'bolt.log');

            Mage::throwException($message);
        }

        $resultJSON = json_decode($result);
        $jsonError = $this->handleJSONParseError();
        if ($jsonError != null) {
            curl_close($ch);
            $message ="JSON Parse Type: " . $jsonError . " Response: " . $result;
            Mage::throwException($message);
        }
        curl_close($ch);
        Mage::getModel('boltpay/payment')->debugData($resultJSON);

        return $this->_handleErrorResponse($resultJSON, $url, $params);
    }

    /**
     * Bolt Api call response wrapper method that checks for potential error responses.
     *
     * @param mixed $response   A response received from calling a Bolt endpoint
     * @param $url
     *
     * @throws  Mage_Core_Exception  thrown if an error is detected in a response
     * @return mixed  If there is no error then the response is returned unaltered.
     */
    private function _handleErrorResponse($response, $url, $request) {
        if (strpos($url, 'v1/merchant/division/oauth') !== false) {
          // Do not log division keys here since they are sensitive.
          $request = "<redacted>";
        }

        if (is_null($response)) {
            $message ="BoltPay Gateway error: No response from Bolt. Please re-try again";
            Mage::throwException($message);
        } elseif (self::isResponseError($response)) {
            $message = sprintf("BoltPay Gateway error for %s: Request: %s, Response: %s", $url, $request, var_export($response, true));
            Mage::throwException($message);
        }
        return $response;
    }

    /**
     * A helper methond for checking errors in JSON object.
     *
     * @return null|string
     */
    public function handleJSONParseError() {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return null;

            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';

            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';

            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';

            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';

            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';

            default:
                return 'Unknown error';
        }
    }

    /**
     * Returns the Bolt API url, sandbox or production, depending on the store configuration.
     *
     * @return string  the api url, sandbox or production
     */
    public function getApiUrl() {
        return Mage::getStoreConfig('payment/boltpay/test') ?
            self::API_URL_TEST :
            self::API_URL_PROD;
    }

    /**
     * Generates order data for sending to Bolt.
     *
     * @param $quote            Magento quote instance
     * @param array $items      array of Magento products
     * @param bool $multipage   Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return array            The order payload to be sent as to bolt in API call as a PHP array
     */
    public function buildOrder($quote, $items, $multipage) {
        $cart = $this->buildCart($quote, $items, $multipage);
        return array(
            'cart' => $cart
        );
    }

    /**
     * Generates cart submission data for sending to Bolt order cart field.
     *
     * @param $quote            Magento quote instance
     * @param $items            array of Magento products
     * @param bool $multipage   Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     * @return array            The cart data part of the order payload to be sent as to bolt in API call as a PHP array
     */
    public function buildCart($quote, $items, $multipage) {

        ///////////////////////////////////////////////////////////////////////////////////
        // Get quote totals
        ///////////////////////////////////////////////////////////////////////////////////

        /***************************************************
         * One known Magento error is that sometimes the subtotal and grand totals are doubled
         * because Magento adds duplicate address totals when it is doing its total aggregation
         * for customers with multiple shipping addresses
         * Totals in magento are ultimately attached to addresses, so the solution is to limit
         * the total number addresses to two maximum (one billing which always exist, and one shipping
         *
         * The following commented out code implements this.
         *
         * HOWEVER: WE CANNOT PUT THIS INTO GENERAL USE AS IT WOULD DROP SUPPORT FOR ITEMS SHIPPED TO DIFFERENT LOCATIONS
         ***************************************************/
        //////////////////////////////////////////////////////////
        //$addresses = $quote->getAllAddresses();
        //if (count($addresses) > 2) {
        //    for($i = 2; $i < count($addresses); $i++) {
        //        $address = $addresses[$i];
        //        $address->isDeleted(true);
        //    }
        //}
        ///////////////////////////////////////////////////////////
        /* Instead we will calculate the cost and use our calculated value to match against magento's calculation
         * If the totals do not match, then we will try halving the Magento total.  We use Magento's
         * instead of ours because on the potential complex nature of discounts and virtual products.
         */
        /***************************************************/
        $calculated_total = 0;
        $quote->collectTotals()->save();
        $totals = $quote->getTotals();
        ///////////////////////////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////
        // Generate base cart data, quote, order and items related.
        ///////////////////////////////////////////////////////////
        $productMediaConfig = Mage::getModel('catalog/product_media_config');
        $cart_submission_data = array(
            'order_reference' => $quote->getId(),
            'display_id'      => $quote->getReservedOrderId(),
            'items'           => array_map(function ($item) use ($quote, $productMediaConfig, &$calculated_total) {
                $image_url = $productMediaConfig->getMediaUrl($item->getProduct()->getThumbnail());
                $product   = Mage::getModel('catalog/product')->load($item->getProductId());
                $calculated_total += round($item->getPrice() * 100 * $item->getQty());
                return array(
                    'reference'    => $quote->getId(),
                    'image_url'    => $image_url,
                    'name'         => $item->getName(),
                    'sku'          => $product->getData('sku'),
                    'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                    'total_amount' => round($item->getPrice() * 100 * $item->getQty()),
                    'unit_price'   => round($item->getPrice() * 100),
                    'quantity'     => $item->getQty()
                );
            }, $items),
            'currency' => $quote->getQuoteCurrencyCode(),
        );
        ///////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // Check for discounts and include them in the submission data if found.
        /////////////////////////////////////////////////////////////////////////
        $total_discount = 0;

        $cart_submission_data['discounts'] = array();

        foreach ($this->discount_types as $discount) {

            if (@$totals[$discount]) {

                $discount_amount = round($totals[$discount]->getValue() * 100);

                preg_match('#\((.*?)\)#', $totals[$discount]->getTitle(), $description);
                $description = @$description[1] ?: $totals[$discount]->getTitle();

                $cart_submission_data['discounts'][] = array(
                    'amount'      => -1 * $discount_amount,
                    'description' => $description,
                );
                $total_discount += $discount_amount;
            }
        }
        $calculated_total += $total_discount;
        /////////////////////////////////////////////////////////////////////////

        if ($multipage) {
            /////////////////////////////////////////////////////////////////////////////////////////
            // For multi-page checkout type send only subtotal, do not include shipping and tax info.
            /////////////////////////////////////////////////////////////////////////////////////////
            $total_key = @$totals['subtotal'] ? 'subtotal' : 'grand_total';

            $cart_submission_data['total_amount'] = round($totals[$total_key]->getValue() * 100);
            $cart_submission_data['total_amount'] += $total_discount;
            /////////////////////////////////////////////////////////////////////////////////////////
        } else {
            // Billing / shipping address fields that are required when the address data is sent to Bolt.
            $required_address_fields = array(
                'first_name',
                'last_name',
                'street_address1',
                'locality',
                'region',
                'postal_code',
                'country_code',
            );

            ///////////////////////////////////////////
            // Include billing address info if defined.
            ///////////////////////////////////////////
            $billingAddress  = $quote->getBillingAddress();

            if ($billingAddress) {

                $cart_submission_data['billing_address'] = array(
                    'street_address1' => $billingAddress->getStreet1(),
                    'street_address2' => $billingAddress->getStreet2(),
                    'street_address3' => $billingAddress->getStreet3(),
                    'street_address4' => $billingAddress->getStreet4(),
                    'first_name'      => $billingAddress->getFirstname(),
                    'last_name'       => $billingAddress->getLastname(),
                    'locality'        => $billingAddress->getCity(),
                    'region'          => $billingAddress->getRegion(),
                    'postal_code'     => $billingAddress->getPostcode(),
                    'country_code'    => $billingAddress->getCountry(),
                    'phone'           => $billingAddress->getTelephone(),
                    'email'           => $billingAddress->getEmail(),
                    'phone_number'    => $billingAddress->getTelephone(),
                    'email_address'   => $billingAddress->getEmail(),
                );

                foreach ($required_address_fields as $field) {
                    if (empty($cart_submission_data['billing_address'][$field])) {
                        unset($cart_submission_data['billing_address']);
                        break;
                    }
                }
            }
            ///////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////////////
            // For one page checkout type include tax and shipment / address data in submission.
            ////////////////////////////////////////////////////////////////////////////////////
            $cart_submission_data['total_amount'] = round($totals["grand_total"]->getValue() * 100);

            if (@$totals['tax']) {
                $cart_submission_data['tax_amount'] = round($totals['tax']->getValue() * 100);
                $calculated_total += $cart_submission_data['tax_amount'];
            }

            $shippingAddress = $quote->getShippingAddress();

            if ($shippingAddress) {

                $shipping_address = array(
                    'street_address1' => $shippingAddress->getStreet1(),
                    'street_address2' => $shippingAddress->getStreet2(),
                    'street_address3' => $shippingAddress->getStreet3(),
                    'street_address4' => $shippingAddress->getStreet4(),
                    'first_name'      => $shippingAddress->getFirstname(),
                    'last_name'       => $shippingAddress->getLastname(),
                    'locality'        => $shippingAddress->getCity(),
                    'region'          => $shippingAddress->getRegion(),
                    'postal_code'     => $shippingAddress->getPostcode(),
                    'country_code'    => $shippingAddress->getCountry(),
                    'phone'           => $shippingAddress->getTelephone(),
                    'email'           => $shippingAddress->getEmail(),
                    'phone_number'    => $shippingAddress->getTelephone(),
                    'email_address'   => $shippingAddress->getEmail(),
                );

                //Mage::log("shipping_address: " . var_export($shipping_address, true), null, "bolt.log");

                if (@$totals['shipping']) {
                    $cart_submission_data['shipments'] = array(array(
                        'shipping_address' => $shipping_address,
                        'tax_amount'       => round($shippingAddress->getShippingTaxAmount() * 100),
                        'service'          => $shippingAddress->getShippingDescription(),
                        'carrier'          => $shippingAddress->getShippingMethod(),
                        'cost'             => round($totals['shipping']->getValue() * 100),
                    ));

                    $calculated_total += round($totals['shipping']->getValue() * 100);
                }

                foreach ($required_address_fields as $field) {
                    if (empty($shipping_address[$field])) {
                        unset($cart_submission_data['shipments']);
                        break;
                    }
                }
            }
            ////////////////////////////////////////////////////////////////////////////////////
        }

        //Mage::log(var_export($cart_submission_data, true), null, "bolt.log");

        return $this->getCorrectedTotal( $calculated_total, $cart_submission_data);
    }

    /**
     * Utility method that attempts to correct totals if the projected total that was calculated from
     * all items and the given discount, does not match the $magento calculated total.  The totals may vary
     * do to an error in the internal Magento code
     *
     * @param int $projected_total              total calculated from items, discounts, taxes and shipping
     * @param int $magento_derived_cart_data    totals returned by magento and formatted for Bolt
     *
     * @return array  the corrected Bolt formatted cart data.
     */
    private function getCorrectedTotal($projected_total, $magento_derived_cart_data) {
        // we'll check if we can simply dividing by two corrects the problem
        if ($projected_total == (int)($magento_derived_cart_data['total_amount']/2)) {

            $magento_derived_cart_data["total_amount"] = (int)($magento_derived_cart_data['total_amount']/2);

            /*  I will defer handling discounts, tax, and shipping until more info is collected
            /*  The placeholder code is left below to be filled in if and when more cases arise

            if (isset($magento_derived_cart_data["tax_amount"])) {
                $magento_derived_cart_data["tax_amount"] = (int)($magento_derived_cart_data["tax_amount"]/2);
            }

            if (isset($magento_derived_cart_data["discounts"])) {
                $magento_derived_cart_data[""] = (int)($magento_derived_cart_data[""]/2);
            }

            if (isset($magento_derived_cart_data["shipments"])) {
                $magento_derived_cart_data[""] = (int)($magento_derived_cart_data[""]/2);
            }
            */
        }

        // otherwise, we have no better thing to do than let the Bolt server do the checking
        return $magento_derived_cart_data;

    }

    /**
     * Checks if the Bolt API response indicates an error.
     *
     * @param $response     Bolt API response
     * @return bool         true if there is an error, false otherwise
     */
    public function isResponseError($response) {
        return array_key_exists('errors', $response) || array_key_exists('error_code', $response);
    }

    /**
     * Gets the shipping and the tax estimate for a quote
     *
     * @param $quote    A quote object with pre-populated addresses
     * @return array
     */
    public function getShippingAndTaxEstimate( $quote ) {

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
            //Mage::log('ShippingController.php: summary:'.var_export($summary, true), null, 'shipping_and_tax.log');
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

        return $response;
    }
}
