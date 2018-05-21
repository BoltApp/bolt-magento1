<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
class Bolt_Boltpay_Helper_Api extends Bolt_Boltpay_Helper_Data
{

    const API_URL_TEST = 'https://api-sandbox.bolt.com/';
    const API_URL_PROD = 'https://api.bolt.com/';

    protected $curlHeaders;
    protected $curlBody;

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
        'credit', // magestore-customer-credit
        'amgiftcard', // https://amasty.com/magento-gift-card.html
        'amstcred', // https://amasty.com/magento-store-credit.html
    );
    ///////////////////////////////////////////////////////

    /**
     * A call to Fetch Bolt API endpoint. Gets the transaction info.
     *
     * @param string $reference        Bolt transaction reference
     * @param int $tries
     *
     * @throws Exception     thrown if multiple (3) calls fail
     * @return bool|mixed Transaction info
     */
    public function fetchTransaction($reference, $tries = 3) 
    {
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
    private function verify_hook_secret($payload, $hmac_header) 
    {

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
    private function verify_hook_api($payload, $hmac_header) 
    {

        try {
            $url = $this->getApiUrl() . "/v1/merchant/verify_signature";

            $key = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/api_key'));

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $httpheader = array(
                "X-Api-Key: $key",
                "X-Bolt-Hmac-Sha256: $hmac_header",
                "Content-type: application/json",
                );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-header'=>$httpheader)),true);  
            Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-data'=>$payload)),true);  
            $result = curl_exec($ch);
            
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->setCurlResultWithHeader($ch, $result);

            $resultJSON = $this->getCurlJSONBody();
            Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API RESPONSE' => array('verify-hook-api-response'=>$resultJSON)),true);

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
    public function verify_hook($payload, $hmac_header) 
    {

        return $this->verify_hook_secret($payload, $hmac_header) || $this->verify_hook_api($payload, $hmac_header);
    }

    /**
     * Processes Magento order creation. Called from both frontend and API.
     *
     * @param string    $reference           Bolt transaction reference
     * @param int       $sessionQuoteId    Quote id, used if triggered from shopping session context,
     *                                       This will be null if called from within an API call context
     *
     * @return Mage_Sales_Model_Order   The order saved to Magento
     *
     * @throws Exception    thrown on order creation failure
     */
    public function createOrder($reference, $sessionQuoteId = null)
    {
        if (empty($reference)) {
            throw new Exception("Bolt transaction reference is missing in the Magento order creation process.");
        }

        if(!$this->storeHasAllCartItems()){
            throw new Exception("Not all items are available in the requested quantities.");
        }

        // fetch transaction info
        $transaction = $this->fetchTransaction($reference);

        $transactionStatus = $transaction->status;

        $quoteId = $transaction->order->cart->order_reference;

        /* @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);

        // make sure this quote has not already processed
        if ($quote->isEmpty()) {
            throw new Exception("This order has already been processed by Magento.");
        }

        // check if the quotes matches, frontend only
        if ( $sessionQuoteId && $sessionQuoteId != $quote->getParentQuoteId() ) {
            throw new Exception("The Bolt order reference does not match the current cart ID.");
        }

        $reservedOrderId = $transaction->order->cart->display_id;

        // adding guest user email to order
        if (!$quote->getCustomerEmail()) {
            $email = $transaction->from_credit_card->billing_address->email_address;
            $quote->setCustomerEmail($email);
            $quote->save();
        }

        $quote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
        $quote->getBillingAddress()->setShouldIgnoreValidation(true)->save();

        /********************************************************************
         * Setting up shipping method by option reference
         * the one set during checkout
         ********************************************************************/
        $referenceShipmentMethod = ($transaction->order->cart->shipments[0]->reference) ?: false;
        if ($referenceShipmentMethod) {
            $quote->getShippingAddress()->setShippingMethod($referenceShipmentMethod)->save();
        } else {
            // Legacy transaction does not have shipments reference - fallback to $service field
            $service = $transaction->order->cart->shipments[0]->service;

            $quote->collectTotals();

            $shipping_address = $quote->getShippingAddress();
            $shipping_address->setCollectShippingRates(true)->collectShippingRates();
            $rates = $shipping_address->getAllShippingRates();

            $is_shipping_set = false;
            foreach ($rates as $rate) {
                if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() == $service
                    || (!$rate->getMethodTitle() && $rate->getCarrierTitle() == $service)) {
                    $shippingMethod = $rate->getCarrier() . '_' . $rate->getMethod();
                    $quote->getShippingAddress()->setShippingMethod($shippingMethod)->save();
                    $is_shipping_set = true;
                    break;
                }
            }

            if (!$is_shipping_set) {
                $errorMessage = 'Shipping method not found';
                $metaData = array(
                    'transaction'   => $transaction,
                    'rates' => $this->getRatesDebuggingData($rates),
                    'service' => $service,
                    'shipping_address' => var_export($shipping_address->debug(), true)
                );
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception($errorMessage), $metaData);
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

        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        /*******************************************************************
         * TODO: Move code to @see Bolt_Boltpay_ApiController::hookAction()
         *******************************************************************/
        /* @var Mage_Sales_Model_Order $existingOrder */
        $existingOrder = Mage::getModel('sales/order')->loadByIncrementId($reservedOrderId);
        if (!$existingOrder->isEmpty()) {
            Mage::app()->getResponse()->setHttpResponseCode(200);
            Mage::app()->getResponse()->setBody(
                json_encode(
                    array(
                    'status' => 'success',
                    'message' => "Order increment $reservedOrderId already exists."
                    )
                )
            );
            return;
        }
        /*******************************************************************/

        if($this->isDiscountRoundingDeltaError($transaction, $quote)) {
            $this->fixQuoteDiscountAmount($transaction, $quote);
        }

        // a call to internal Magento service for order creation
        $service = Mage::getModel('sales/service_quote', $quote);

        try {
            $service->submitAll();
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'transaction'   => json_encode((array)$transaction),
                    'quote_address' => var_export($quote->getShippingAddress()->debug(), true)
                )
            );
            throw $e;
        }

        $order = $service->getOrder();

        $this->validateSubmittedOrder($order, $quote);

        Mage::getModel('boltpay/payment')->handleOrderUpdate($order);

        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order'=>$order, 'quote'=>$quote));

        // Close out session by deactivating parent quote and deleting the immutable quote so that it can no
        // longer be used.
        /* @var Mage_Sales_Model_Quote $parentQuote */
        $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId());
        $parentQuote->setIsActive(false)
            ->save();

        if ($sessionQuoteId) {
            $checkout_session = Mage::getSingleton('checkout/session');

            $checkout_session
                ->clearHelperData();

            $checkout_session
                ->setLastQuoteId($quote->getId())
                ->setLastSuccessQuoteId($quote->getId());

            // add order information to the session
            $checkout_session->setLastOrderId($order->getId())
                ->setRedirectUrl('')
                ->setLastRealOrderId($order->getIncrementId());
        }

        //$quote->delete();

        return $order;

    }

    protected function getRatesDebuggingData($rates) {
        $rateDebuggingData = '';

        if(isset($rates)) {
            foreach($rates as $rate) {
                $rateDebuggingData .= var_export($rate->debug(), true);
            }
        }

        return $rateDebuggingData;
    }

    /**
     * Determines whether the discount amount from Bolt is off by $0.01 compared to the Magento quote discount amount
     *
     * When $quote->collectTotals calls Mage_SalesRule_Model_Validator->process it uses a singleton to instantiate the
     * validator so when it gets called each time, the  _roundingDeltas variable persists previous data and causes off
     * by $0.01 rounding errors. Each call to collectTotals either sets it to the correct amount or to an amount that
     * is off by $0.01. This function detects this problem.
     *
     * @param $transaction  Transaction data sent by Bolt
     * @param Sales_Model_Service_Quote $quote     Quote derived from transaction data
     */
    protected function isDiscountRoundingDeltaError($transaction, $quote) {
        $boltDiscountAmount = round($this->getBoltDiscountAmount($transaction), 2);
        $quoteDiscountAmount = round($quote->getShippingAddress()->getDiscountAmount(), 2);

        return $this->isOnePennyRoundingError($boltDiscountAmount, $quoteDiscountAmount);
    }

    protected function getBoltDiscountAmount($transaction) {
        $boltDiscountAmount = 0;

        if(isset($transaction->order->cart->discounts)) {
            foreach($transaction->order->cart->discounts as $discount) {
                if(isset($discount->amount->amount) && is_numeric($discount->amount->amount) && $discount->amount->amount > 0) {
                    $boltDiscountAmount -= $discount->amount->amount / 100;
                }
            }
        }

        return $boltDiscountAmount;
    }

    protected function isOnePennyRoundingError($boltDiscountAmount, $quoteDiscountAmount) {
        if($boltDiscountAmount != $quoteDiscountAmount) {
            $difference = round(abs($boltDiscountAmount - $quoteDiscountAmount), 2);
            if($difference <= 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fixes the quote if it is determined that the discount amount meets the criteria in isDiscountRoundingDeltaError.
     *
     * The bug that gets detected in isDiscountRoundingDeltaError can be resolved by simply calling collectTotals again.
     * This function calls it again and throws an exception if the problem is not resolved.
     *
     * @param $transaction  Transaction data sent by Bolt
     * @param Sales_Model_Service_Quote $quote     Quote derived from transaction data
     */
    protected function fixQuoteDiscountAmount($transaction, $quote) {
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        if($this->isDiscountRoundingDeltaError($transaction, $quote)) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'transaction'  => $transaction,
                    'quote'  => var_export($quote->debug(), true),
                    'quote_address'  => var_export($quote->getShippingAddress()->debug(), true),
                )
            );

            Mage::helper('boltpay/bugsnag')->notifyException(new Exception('Failed to fix quote discount amount'));
        }
    }

    protected function validateSubmittedOrder($order, $quote) {
        if(empty($order)) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'quote'  => var_export($quote->debug(), true),
                    'quote_address'  => var_export($quote->getShippingAddress()->debug(), true),
                )
            );

            throw new Exception('Order is empty after call to Sales_Model_Service_Quote->submitAll()');
        }
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
    public function transmit($command, $data, $object='merchant', $type='transactions') 
    {
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

        $context_info = Mage::helper('boltpay/bugsnag')->getContextInfo();
        $header_info = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'X-Api-Key: ' . Mage::helper('core')->decrypt($key),
            'X-Nonce: ' . rand(100000000, 999999999),
            'User-Agent: BoltPay/Magento-' . $context_info["Magento-Version"],
            'X-Bolt-Plugin-Version: ' . $context_info["Bolt-Plugin-Version"]
            );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_info);
        Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('header'=>$header_info)));
        Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('data'=>$data)),true);   
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            $curl_info = var_export(curl_getinfo($ch), true);
            $curl_err = curl_error($ch);
            curl_close($ch);

            $message ="Curl info: " . $curl_info;

            //Mage::log($message, null, 'bolt.log');

            Mage::throwException($message);
        }

        $this->setCurlResultWithHeader($ch, $result);

        $resultJSON = $this->getCurlJSONBody();
        Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API RESPONSE' => array('BOLT-RESPONSE'=>$resultJSON)),true);
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

    protected function setCurlResultWithHeader($curlResource, $result) 
    {
        $curlHeaderSize = curl_getinfo($curlResource, CURLINFO_HEADER_SIZE);

        $this->curlHeaders = substr($result, 0, $curlHeaderSize);
        $this->curlBody = substr($result, $curlHeaderSize);

        $this->setBoltTraceId();
    }

    protected function setBoltTraceId() 
    {
        if(empty($this->curlHeaders)) { return; 
        }

        foreach(explode("\r\n", $this->curlHeaders) as $row) {
            if(preg_match('/(.*?): (.*)/', $row, $matches)) {
                if(count($matches) == 3 && $matches[1] == 'X-Bolt-Trace-Id') {
                    Mage::helper('boltpay/bugsnag')->setBoltTraceId($matches[2]);
                    break;
                }
            }
        }
    }

    protected function getCurlJSONBody() 
    {
        return json_decode($this->curlBody);
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
    private function _handleErrorResponse($response, $url, $request) 
    {
        if (strpos($url, 'v1/merchant/division/oauth') !== false) {
            // Do not log division keys here since they are sensitive.
            $request = "<redacted>";
        }

        if (is_null($response)) {
            $message ="BoltPay Gateway error: No response from Bolt. Please re-try again";
            Mage::throwException($message);
        } elseif (self::isResponseError($response)) {
            if (property_exists($response, 'errors')) {
                Mage::register("api_error", $response->errors[0]->message);
            }

            $message = sprintf("BoltPay Gateway error for %s: Request: %s, Response: %s", $url, $request, json_encode($response, true));

            Mage::helper('boltpay/bugsnag')->notifyException(new Exception($message));
            Mage::throwException($message);
        }

        return $response;
    }

    /**
     * A helper methond for checking errors in JSON object.
     *
     * @return null|string
     */
    public function handleJSONParseError() 
    {
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
    public function getApiUrl() 
    {
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
    public function buildOrder($quote, $items, $multipage) 
    {
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
    public function buildCart($quote, $items, $multipage) 
    {

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
        //Mage::log(var_export(array_keys($totals), 1), null, 'bolt.log');
        ///////////////////////////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////
        // Generate base cart data, quote, order and items related.
        ///////////////////////////////////////////////////////////
        $productMediaConfig = Mage::getModel('catalog/product_media_config');
        $cart_submission_data = array(
            'order_reference' => $quote->getId(),
            'display_id'      => $quote->getReservedOrderId(),
            'items'           => array_map(
                function ($item) use ($quote, $productMediaConfig, &$calculated_total) {
                $image_url = $productMediaConfig->getMediaUrl($item->getProduct()->getThumbnail());
                $product   = Mage::getModel('catalog/product')->load($item->getProductId());
                $calculated_total += round($item->getPrice() * 100 * $item->getQty());
                return array(
                    'reference'    => $quote->getId(),
                    'image_url'    => $image_url,
                    'name'         => $item->getName(),
                    'sku'          => $product->getData('sku'),
                    'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                    'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
                    'unit_price'   => round($item->getCalculationPrice() * 100),
                    'quantity'     => $item->getQty()
                );
                }, $items
            ),
            'currency' => $quote->getQuoteCurrencyCode(),
        );
        ///////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // Check for discounts and include them in the submission data if found.
        /////////////////////////////////////////////////////////////////////////
        $total_discount = 0;

        $cart_submission_data['discounts'] = array();

        foreach ($this->discount_types as $discount) {
            if (@$totals[$discount] && $amount = $totals[$discount]->getValue()) {
                // Some extensions keep discount totals as positive values,
                // others as negative, which is the Magento default.
                // Using the absolute value.
                $discount_amount = abs(round($amount * 100));

                $cart_submission_data['discounts'][] = array(
                    'amount'      => $discount_amount,
                    'description' => $totals[$discount]->getTitle(),
                    'type'        => 'fixed_amount',
                );
                $total_discount -= $discount_amount;
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

        return $this->getCorrectedTotal($calculated_total, $cart_submission_data);
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
    private function getCorrectedTotal($projected_total, $magento_derived_cart_data) 
    {
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
    public function isResponseError($response) 
    {
        return property_exists($response, 'errors') || property_exists($response, 'error_code');
    }

    /**
     * Gets the shipping and the tax estimate for a quote
     *
     * @param $quote    A quote object with pre-populated addresses
     *
     * @return array    Bolt shipping and tax response array to be converted to JSON
     */
    public function getShippingAndTaxEstimate( $quote )
    {

        $response = array(
            'shipping_options' => array(),
            'tax_result' => array(
                "amount" => 0
            ),
        );

        Mage::getModel('sales/quote')->load($quote->getId())->collectTotals();

        /*****************************************************************************************
         * Calculate tax
         *****************************************************************************************/
        $this->applyShippingRate($quote, null);

        /*****************************************************************************************/

        $shipping_address = $quote->getShippingAddress();
        $shipping_address->setCollectShippingRates(true)->collectShippingRates()->save();

        $origTotalWithoutShippingOrTax = $this->getTotalWithoutTaxOrShipping($quote);

        $rates = $this->getSortedShippingRates($shipping_address);

        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                Mage::helper('boltpay/bugsnag')->notifyException( new Exception("Error getting shipping option for " .  $rate->getCarrierTitle() . ": " . $rate->getErrorMessage()) );
                continue;
            }

            $this->applyShippingRate($quote, $rate->getCode());

            $label = $rate->getCarrierTitle();
            if ($rate->getMethodTitle()) {
                $label = $label . ' - ' . $rate->getMethodTitle();
            }

            $rateCode = $rate->getCode();

            if(empty($rateCode)) {
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception('Rate code is empty. ' . var_export($rate->debug(), true)));
            }

            $shippingDiscountModifier = $this->getShippingDiscountModifier($origTotalWithoutShippingOrTax, $quote);

            $option = array(
                "service"   => $label,
                "reference" => $rateCode,
                "cost" => round(($quote->getShippingAddress()->getShippingAmount() - $shippingDiscountModifier) * 100),
                "tax_amount" => abs(round($quote->getShippingAddress()->getTaxAmount() * 100))
            );

            $response['shipping_options'][] = $option;
        }

        /*****************************************************************************************/

        return $response;
    }

    /**
     * Applies shipping rate to quote. Clears previously calculated discounts by clearing address id.
     *
     * @param Mage_Sales_Model_Quote $quote    Quote which has been updated to use new shipping rate
     * @param string $shippingRateCode    Shipping rate code
     */
    public function applyShippingRate($quote, $shippingRateCode) {
        $shippingAddress = $quote->getShippingAddress();

        if (!empty($shippingAddress)) {
            // Flagging address as new is required to force collectTotals to recalculate discounts
            $shippingAddress->isObjectNew(true);
            $shippingAddressId = $shippingAddress->getData('address_id');

            $shippingAddress->setShippingMethod($shippingRateCode);

            // When multiple shipping methods apply a discount to the sub-total, collect totals doesn't clear the
            // previously set discocunt, so the previous discount gets added to each subsequent shipping method that
            // includes a discount. Here we reset it to the original amount to resolve this bug.
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                $item->setData('discount_amount', $item->getOrigData('discount_amount'));
                $item->setData('base_discount_amount', $item->getOrigData('base_discount_amount'));
            }

            $quote->setTotalsCollectedFlag(false)->collectTotals();

            if(!empty($shippingAddressId) && $shippingAddressId != $shippingAddress->getData('address_id')) {
                $shippingAddress->setData('address_id', $shippingAddressId);
            }
        }
    }

    /**
     * Gets the quote total after tax and shipping costs have been removed
     *
     * @param Mage_Sales_Model_Quote $quote    Quote which has been updated to use new shipping rate
     *
     * @return float    Grand Total - Taxes - Shipping Cost
     */
    protected function getTotalWithoutTaxOrShipping($quote) {
        $address = $quote->getShippingAddress();

        return $address->getGrandTotal() - $address->getTaxAmount() - $address->getShippingAmount();
    }

    protected function getSortedShippingRates($address) {
        $rates = array();

        foreach($address->getGroupedAllShippingRates() as $code => $carrierRates) {
            foreach ($carrierRates as $carrierRate) {
                $rates[] = $carrierRate;
            }
        }

        return $rates;
    }

    /**
     * Gets the difference between a previously calculated subtotal and the new subtotal due to changing shipping methods.
     *
     * @param float $origTotalWithoutShippingOrTax    Original subtotal
     * @param Mage_Sales_Model_Quote    $updatedQuote    Quote which has been updated to use new shipping rate
     *
     * @return float    Discount modified as a result of the new shipping method
     */
    protected function getShippingDiscountModifier($origTotalWithoutShippingOrTax, $updatedQuote) {
        $newQuoteWithoutShippingOrTax = $this->getTotalWithoutTaxOrShipping($updatedQuote);

        return $origTotalWithoutShippingOrTax - $newQuoteWithoutShippingOrTax;
    }

    /**
     * Sets Plugin information in the response headers to callers of the API
     */
    public function setResponseContextHeaders() 
    {
        $context_info = Mage::helper('boltpay/bugsnag')->getContextInfo();

        Mage::app()->getResponse()
            ->setHeader('User-Agent', 'BoltPay/Magento-' . $context_info["Magento-Version"], true)
            ->setHeader('X-Bolt-Plugin-Version', $context_info["Bolt-Plugin-Version"], true);
    }


    /**
     * Determines whether the cart has either all items available if Manage Stock is yes for requested quantities,
     * or, if not, those items are eligible for back order.
     *
     * @return bool true if the store can accept an order for all items in the cart,
     *              otherwise, false
     */
    public function storeHasAllCartItems()
    {
        /* @var Mage_Sales_Model_Quote $cart_quote */
        $cart_quote = Mage::helper('checkout/cart')->getCart()->getQuote();

        foreach ($cart_quote->getAllItems() as $cart_item) {
            if($cart_item->getHasChildren()) {
                continue;
            }

            $_product = Mage::getModel('catalog/product')->load($cart_item->getProductId());
            $stock_info = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
			if($stock_info->getManageStock()){
				if( ($stock_info->getQty() < $cart_item->getQty()) && !$stock_info->getBackorders() ){
					 return false;
				}
			}
        }

        return true;
    }
}
