<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
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
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL  = 'digital';

    protected $curlHeaders;
    protected $curlBody;

    // Store discount types, internal and 3rd party.
    // Can appear as keys in Quote::getTotals result array.
    private $discountTypes = array(
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
                $message = Mage::helper('boltpay')->__("BoltPay Gateway error: Fetch Transaction call failed multiple times for transaction referenced: %s", $reference);
                Mage::helper('boltpay/bugsnag')->notifyException(new Exception($message));
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
     * @param $hmacHeader
     * @return bool
     */
    private function verify_hook_secret($payload, $hmacHeader)
    {
        $signingSecret = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/signing_key'));
        $computedHmac  = trim(base64_encode(hash_hmac('sha256', $payload, $signingSecret, true)));

        return $hmacHeader == $computedHmac;
    }

    /**
     * Verifying Hook Requests via API call.
     *
     * @param $payload
     * @param $hmacHeader
     * @return bool if signature is verified
     */
    private function verify_hook_api($payload, $hmacHeader)
    {
        try {
            $url = Mage::helper('boltpay/url')->getApiUrl() . "/v1/merchant/verify_signature";

            $key = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/boltpay/api_key'));

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $httpHeader = array(
                "X-Api-Key: $key",
                "X-Bolt-Hmac-Sha256: $hmacHeader",
                "Content-type: application/json",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('verify-hook-api-header'=>$httpHeader)),true);
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
     * @param $hmacHeader
     * @return bool
     */
    public function verify_hook($payload, $hmacHeader)
    {
        return $this->verify_hook_secret($payload, $hmacHeader) || $this->verify_hook_api($payload, $hmacHeader);
    }

    /**
     * Processes Magento order creation. Called from both frontend and API.
     *
     * @param string        $reference           Bolt transaction reference
     * @param int           $sessionQuoteId      Quote id, used if triggered from shopping session context,
     *                                           This will be null if called from within an API call context
     * @param boolean       $isAjaxRequest       If called by ajax request. default to false.
     * @param object        $transaction         pre-loaded Bolt Transaction object
     *
     * @return Mage_Sales_Model_Order   The order saved to Magento
     *
     * @throws Exception    thrown on order creation failure
     */
    public function createOrder($reference, $sessionQuoteId = null, $isAjaxRequest = false, $transaction = null)
    {

        try {
            if (empty($reference)) {
                throw new Exception(Mage::helper('boltpay')->__("Bolt transaction reference is missing in the Magento order creation process."));
            }

            $transaction = $transaction ?: $this->fetchTransaction($reference);

            $immutableQuoteId = $this->getImmutableQuoteIdFromTransaction($transaction);

            /* @var Mage_Sales_Model_Quote $immutableQuote */
            $immutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuoteId);

            // check that the order is in the system.  If not, we have an unexpected problem
            if ($immutableQuote->isEmpty()) {
                throw new Exception(Mage::helper('boltpay')->__("The expected quote is missing from the Magento system.  Were old quotes recently removed from the database?"));
            }

            if(!$this->storeHasAllCartItems($immutableQuote)){
                throw new Exception(Mage::helper('boltpay')->__("Not all items are available in the requested quantities."));
            }

            // check if the quotes matches, frontend only
            if ( $sessionQuoteId && ($sessionQuoteId != $immutableQuote->getParentQuoteId()) ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The Bolt order reference does not match the current cart ID. Cart ID: [%s]  Bolt Reference: [%s]",
                                                $sessionQuoteId , $immutableQuote->getParentQuoteId())
                );
            }

            // check if quote has already been used
            if ( !$immutableQuote->getIsActive() ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The order #%s has already been processed for this quote.",
                                                $immutableQuote->getReservedOrderId() )
                );
            }

            // check if this order is currently being proccessed.  If so, throw exception
            /* @var Mage_Sales_Model_Quote $parentQuote */
            $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($immutableQuote->getParentQuoteId());
            if ($parentQuote->isEmpty() ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The parent quote %s is unexpectedly missing.",
                                                $immutableQuote->getParentQuoteId() )
                );
            } else if (!$parentQuote->getIsActive() ) {
                throw new Exception(
                    Mage::helper('boltpay')->__("The parent quote %s is currently being processed or has been processed.",
                                                $immutableQuote->getParentQuoteId() )    
                );
            } else {
                $parentQuote->setIsActive(false)->save();
            }

            // adding guest user email to order
            if (!$immutableQuote->getCustomerEmail()) {
                $email = $transaction->from_credit_card->billing_address->email_address;
                $immutableQuote->setCustomerEmail($email);
                $immutableQuote->save();
            }

            // explicitly set quote belong to guest if customer id does not exist
            $immutableQuote
               ->setCustomerIsGuest( (($parentQuote->getCustomerId()) ? false : true) )
               ->save();

            $immutableQuote->getShippingAddress()->setShouldIgnoreValidation(true)->save();
            $immutableQuote->getBillingAddress()->setShouldIgnoreValidation(true)->save();

            /********************************************************************
             * Setting up shipping method by option reference
             * the one set during checkout
             ********************************************************************/
            $referenceShipmentMethod = ($transaction->order->cart->shipments[0]->reference) ?: false;
            if ($referenceShipmentMethod) {
                $immutableQuote->getShippingAddress()->setShippingMethod($referenceShipmentMethod)->save();
            } else {
                // Legacy transaction does not have shipments reference - fallback to $service field
                $service = $transaction->order->cart->shipments[0]->service;

                Mage::helper('boltpay')->collectTotals($immutableQuote);

                $shippingAddress = $immutableQuote->getShippingAddress();
                $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
                $rates = $shippingAddress->getAllShippingRates();

                $isShippingSet = false;
                foreach ($rates as $rate) {
                    if ($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle() == $service
                        || (!$rate->getMethodTitle() && $rate->getCarrierTitle() == $service)) {
                        $shippingMethod = $rate->getCarrier() . '_' . $rate->getMethod();
                        $immutableQuote->getShippingAddress()->setShippingMethod($shippingMethod)->save();
                        $isShippingSet = true;
                        break;
                    }
                }

                if (!$isShippingSet) {
                    $errorMessage = Mage::helper('boltpay')->__('Shipping method not found');
                    $metaData = array(
                        'transaction'   => $transaction,
                        'rates' => $this->getRatesDebuggingData($rates),
                        'service' => $service,
                        'shipping_address' => var_export($shippingAddress->debug(), true),
                        'quote' => var_export($immutableQuote->debug(), true)
                    );
                    Mage::helper('boltpay/bugsnag')->notifyException(new Exception($errorMessage), $metaData);
                }
            }


            // setting Bolt as payment method
            $immutableQuote->getShippingAddress()->setPaymentMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE)->save();
            $payment = $immutableQuote->getPayment();
            $payment->setMethod(Bolt_Boltpay_Model_Payment::METHOD_CODE);

            Mage::helper('boltpay')->collectTotals($immutableQuote, true)->save();

            ////////////////////////////////////////////////////////////////////////////
            // reset increment id if needed
            ////////////////////////////////////////////////////////////////////////////
            /* @var Mage_Sales_Model_Order $preExistingOrder */
            $preExistingOrder = Mage::getModel('sales/order')->loadByIncrementId($parentQuote->getReservedOrderId());

            if (!$preExistingOrder->isObjectNew()) {
                ############################
                # First check if this order matches the transaction and therefore already created
                # If so, we can return it after notifying Bugsnag
                ############################
                $preExistingTransactionReference = $preExistingOrder->getPayment()->getAdditionalInformation('bolt_reference');
                if ( $preExistingTransactionReference === $reference ) {
                    Mage::helper('boltpay/bugsnag')->notifyException(
                        new Exception( Mage::helper('boltpay')->__("The order #%s has already been processed for this quote.", $preExistingOrder->getIncrementId() ) ),
                        array(),
                        'warning'
                    );
                    return $preExistingOrder;
                }
                ############################

                $parentQuote
                    ->setReservedOrderId(null)
                    ->reserveOrderId()
                    ->save();

                $immutableQuote->setReservedOrderId($parentQuote->getReservedOrderId());
            }
            ////////////////////////////////////////////////////////////////////////////

            // a call to internal Magento service for order creation
            $service = Mage::getModel('sales/service_quote', $immutableQuote);

            try {
                ///////////////////////////////////////////////////////
                /// These values are used in the observer after successful
                /// order creation
                ///////////////////////////////////////////////////////
                Mage::getSingleton('core/session')->setBoltTransaction($transaction);
                Mage::getSingleton('core/session')->setBoltReference($reference);
                Mage::getSingleton('core/session')->setWasCreatedByHook(!$isAjaxRequest);
                ///////////////////////////////////////////////////////

                $service->submitAll();
            } catch (Exception $e) {

                ///////////////////////////////////////////////////////
                /// Unset session values set above
                ///////////////////////////////////////////////////////
                Mage::getSingleton('core/session')->unsBoltTransaction();
                Mage::getSingleton('core/session')->unsBoltReference();
                Mage::getSingleton('core/session')->unsWasCreatedByHook();
                ///////////////////////////////////////////////////////

                Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                    array(
                        'transaction'   => json_encode((array)$transaction),
                        'quote_address' => var_export($immutableQuote->getShippingAddress()->debug(), true)
                    )
                );
                throw $e;
            }

        } catch ( Exception $e ) {
            // Order creation failed, so mark the parent quote as active so webhooks can retry it
            if (@$parentQuote) {
                $parentQuote->setIsActive(true)->save();
            }

            throw $e;
        }

        $order = $service->getOrder();
        $this->validateSubmittedOrder($order, $immutableQuote);

        Mage::getModel('boltpay/payment')->handleOrderUpdate($order);

        Mage::dispatchEvent('bolt_boltpay_save_order_after', array('order'=>$order, 'quote'=>$immutableQuote, 'transaction' => $transaction));

        if ($sessionQuoteId) {
            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession
                ->clearHelperData();
            $checkoutSession
                ->setLastQuoteId($parentQuote->getId())
                ->setLastSuccessQuoteId($parentQuote->getId());
            // add order information to the session
            $checkoutSession->setLastOrderId($order->getId())
                ->setRedirectUrl('')
                ->setLastRealOrderId($order->getIncrementId());
        }

        ///////////////////////////////////////////////////////
        // Close out session by
        // 1.) deactivating the immutable quote so it can no longer be used
        // 2.) assigning the immutable quote as the parent of its parent quote
        //
        // This creates a circular reference so that we can use the parent quote
        // to look up the used immutable quote
        ///////////////////////////////////////////////////////
        $immutableQuote->setIsActive(false)
            ->save();
        $parentQuote->setParentQuoteId($immutableQuote->getId())
            ->save();
        ///////////////////////////////////////////////////////

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

    protected function validateSubmittedOrder($order, $quote) {
        if(empty($order)) {
            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    'quote'  => var_export($quote->debug(), true),
                    'quote_address'  => var_export($quote->getShippingAddress()->debug(), true),
                )
            );

            throw new Exception(Mage::helper('boltpay')->__('Order is empty after call to Sales_Model_Service_Quote->submitAll()'));
        }
    }

    /**
     * Calls the Bolt API endpoint.
     *
     * @param string $command  The endpoint to be called
     * @param string $data     an object to be encoded to JSON as the value passed to the endpoint
     * @param string $object   defines part of endpoint url which is normally/always??? set to merchant
     * @param string $type     Defines the endpoint type (i.e. order|transactions|sign) that is used as part of the url
     * @param null $storeId
     * @throws  Mage_Core_Exception  thrown if an error is detected in a response
     * @return mixed           Object derived from Json got as a response
     */
    public function transmit($command, $data, $object='merchant', $type='transactions', $storeId = null)
    {
        $url = Mage::helper('boltpay/url')->getApiUrl($storeId) . 'v1/';

        if($command == 'sign' || $command == 'orders') {
            $url .= $object . '/' . $command;
        } elseif ($command == null || $command == '') {
            $url .= $object;
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

        if ($command == '' && $type == '' && $object == 'merchant') {
            $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage', $storeId);
        } else {
            $key = Mage::getStoreConfig('payment/boltpay/api_key', $storeId);
        }

        //Mage::log('KEY: ' . Mage::helper('core')->decrypt($key), null, 'bolt.log');

        $contextInfo = Mage::helper('boltpay/bugsnag')->getContextInfo();
        $headerInfo = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'X-Api-Key: ' . Mage::helper('core')->decrypt($key),
            'X-Nonce: ' . rand(100000000, 999999999),
            'User-Agent: BoltPay/Magento-' . $contextInfo["Magento-Version"],
            'X-Bolt-Plugin-Version: ' . $contextInfo["Bolt-Plugin-Version"]
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerInfo);
        Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('header'=>$headerInfo)));
        Mage::helper('boltpay/bugsnag')->addMetaData(array('BOLT API REQUEST' => array('data'=>$data)),true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            $curlInfo = var_export(curl_getinfo($ch), true);
            curl_close($ch);

            $message ="Curl info: " . $curlInfo;

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
        if (is_null($response)) {
            $message = Mage::helper('boltpay')->__("BoltPay Gateway error: No response from Bolt. Please re-try again");
            Mage::throwException($message);
        } elseif (self::isResponseError($response)) {
            if (property_exists($response, 'errors')) {
                Mage::register("api_error", $response->errors[0]->message);
            }

            $message = Mage::helper('boltpay')->__("BoltPay Gateway error for %s: Request: %s, Response: %s", $url, $request, json_encode($response, true));

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
                return Mage::helper('boltpay')->__('Maximum stack depth exceeded');

            case JSON_ERROR_STATE_MISMATCH:
                return Mage::helper('boltpay')->__('Underflow or the modes mismatch');

            case JSON_ERROR_CTRL_CHAR:
                return Mage::helper('boltpay')->__('Unexpected control character found');

            case JSON_ERROR_SYNTAX:
                return Mage::helper('boltpay')->__('Syntax error, malformed JSON');

            case JSON_ERROR_UTF8:
                return Mage::helper('boltpay')->__('Malformed UTF-8 characters, possibly incorrectly encoded');

            default:
                return Mage::helper('boltpay')->__('Unknown error');
        }
    }

    /**
     * Generates order data for sending to Bolt.
     *
     * @param Mage_Sales_Model_Quote        $quote      Magento quote instance
     * @param bool                          $multipage  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     *
     * @return array            The order payload to be sent as to bolt in API call as a PHP array
     */
    public function buildOrder($quote, $multipage)
    {
        $cart = $this->buildCart($quote, $multipage);
        return array(
            'cart' => $cart
        );
    }


    /**
     * Generates cart submission data for sending to Bolt order cart field.
     *
     * @param Mage_Sales_Model_Quote        $quote      Magento quote instance
     * @param bool                          $multipage  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     *
     * @return array            The cart data part of the order payload to be sent as to bolt in API call as a PHP array
     */
    public function buildCart($quote, $multipage)
    {
        /** @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');

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
        $boltHelper->collectTotals($quote)->save();

        $totals = $quote->getTotals();
        ///////////////////////////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////
        // Generate base cart data, quote, order and items related.
        ///////////////////////////////////////////////////////////
        $cartSubmissionData = array(
            'order_reference' => $quote->getParentQuoteId(),
            'display_id'      => $quote->getReservedOrderId().'|'.$quote->getId(),
            'items'           => array_map(
                function ($item) use ($quote, &$calculatedTotal, $boltHelper) {
                    $imageUrl = $boltHelper->getItemImageUrl($item);
                    $product   = Mage::getModel('catalog/product')->load($item->getProductId());
                    $type = $product->getTypeId() == 'virtual' ? self::ITEM_TYPE_DIGITAL : self::ITEM_TYPE_PHYSICAL;

                    $calculatedTotal += round($item->getPrice() * 100 * $item->getQty());
                    return array(
                        'reference'    => $quote->getId(),
                        'image_url'    => $imageUrl,
                        'name'         => $item->getName(),
                        'sku'          => $product->getData('sku'),
                        'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                        'total_amount' => round($item->getCalculationPrice() * 100 * $item->getQty()),
                        'unit_price'   => round($item->getCalculationPrice() * 100),
                        'quantity'     => $item->getQty(),
                        'type'         => $type
                    );
                }, $quote->getAllVisibleItems()
            ),
            'currency' => $quote->getQuoteCurrencyCode(),
        );
        ///////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // Check for discounts and include them in the submission data if found.
        /////////////////////////////////////////////////////////////////////////
        $totalDiscount = 0;

        $cartSubmissionData['discounts'] = array();

        foreach ($this->discountTypes as $discount) {
            if (@$totals[$discount] && $amount = $totals[$discount]->getValue()) {
                // Some extensions keep discount totals as positive values,
                // others as negative, which is the Magento default.
                // Using the absolute value.
                $discountAmount = abs(round($amount * 100));

                $cartSubmissionData['discounts'][] = array(
                    'amount'      => $discountAmount,
                    'description' => $totals[$discount]->getTitle(),
                    'type'        => 'fixed_amount',
                );
                $totalDiscount -= $discountAmount;
            }
        }

        $calculatedTotal += $totalDiscount;
        /////////////////////////////////////////////////////////////////////////

        if ($multipage) {
            /////////////////////////////////////////////////////////////////////////////////////////
            // For multi-page checkout type send only subtotal, do not include shipping and tax info.
            /////////////////////////////////////////////////////////////////////////////////////////
            $totalKey = @$totals['subtotal'] ? 'subtotal' : 'grand_total';

            $cartSubmissionData['total_amount'] = round($totals[$totalKey]->getValue() * 100);
            $cartSubmissionData['total_amount'] += $totalDiscount;
            /////////////////////////////////////////////////////////////////////////////////////////
        } else {

            // Billing / shipping address fields that are required when the address data is sent to Bolt.
            $requiredAddressFields = array(
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
                $cartSubmissionData['billing_address'] = array(
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
                    'email'           => $billingAddress->getEmail() ?: $quote->getCustomerEmail(),
                    'phone_number'    => $billingAddress->getTelephone(),
                    'email_address'   => $billingAddress->getEmail() ?: $quote->getCustomerEmail(),
                );

                foreach ($requiredAddressFields as $field) {
                    if (empty($cartSubmissionData['billing_address'][$field])) {
                        unset($cartSubmissionData['billing_address']);
                        break;
                    }
                }
            }
            ///////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////////////
            // For one page checkout type include tax and shipment / address data in submission.
            ////////////////////////////////////////////////////////////////////////////////////
            $cartSubmissionData['total_amount'] = round($totals["grand_total"]->getValue() * 100);

            if (@$totals['tax']) {
                $cartSubmissionData['tax_amount'] = round($totals['tax']->getValue() * 100);
                $calculatedTotal += $cartSubmissionData['tax_amount'];
            }

            $shippingAddress = $quote->getShippingAddress();

            if ($shippingAddress) {
                $cartShippingAddress = array(
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
                    'email'           => $shippingAddress->getEmail() ?: $quote->getCustomerEmail(),
                    'phone_number'    => $shippingAddress->getTelephone(),
                    'email_address'   => $shippingAddress->getEmail() ?: $quote->getCustomerEmail(),
                );

                if (@$totals['shipping']) {

                    $cartSubmissionData['shipments'] = array(array(
                        'shipping_address' => $cartShippingAddress,
                        'tax_amount'       => (int) round($shippingAddress->getShippingTaxAmount() * 100),
                        'service'          => $shippingAddress->getShippingDescription(),
                        'carrier'          => $shippingAddress->getShippingMethod(),
                        'cost'             => (int) round($totals['shipping']->getValue() * 100),
                    ));
                    $calculatedTotal += round($totals['shipping']->getValue() * 100);

                } else if (Mage::app()->getStore()->isAdmin()) {
                    $cartShippingAddress = Mage::getSingleton('admin/session')->getOrderShippingAddress();

                    if (empty($cartShippingAddress['email'])) {
                        $cartShippingAddress['email'] = $cartShippingAddress['email_address'] = $quote->getCustomerEmail();
                    }

                    /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
                    $shippingMethodBlock =  Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_shipping_method_form");
                    $shipping_rate = $shippingMethodBlock->getActiveMethodRate();

                    if ($shipping_rate) {
                        /* @var Mage_Adminhtml_Block_Sales_Order_Create_Totals $totalsBlock */
                        $totalsBlock =  Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_totals_shipping");

                        /* @var Mage_Sales_Model_Quote_Address_Total $grandTotal */
                        $grandTotal = $totalsBlock->getTotals()['grand_total'];
                        /* @var Mage_Sales_Model_Quote_Address_Total $taxTotal */
                        $taxTotal = $totalsBlock->getTotals()['tax'];
                        /* @var Mage_Sales_Model_Quote_Address_Total $shippingTotal */
                        $shippingTotal = $totalsBlock->getTotals()['shipping'];

                        $cartSubmissionData['shipments'] = array(array(
                            'shipping_address' => $cartShippingAddress,
                            'tax_amount'       => 0,
                            'service'          => $shipping_rate->getMethodTitle(),
                            'carrier'          => $shipping_rate->getCarrierTitle(),
                            'cost'             => $shippingTotal ? (int) round($shippingTotal->getValue() * 100) : 0,
                        ));

                        $calculatedTotal += round($shippingTotal->getValue() * 100);

                        $cartSubmissionData['total_amount'] = (int) round($grandTotal->getValue() * 100);
                        $cartSubmissionData['tax_amount'] = $taxTotal ? (int) round($taxTotal->getValue() * 100) : 0;
                    }

                }

                foreach ($requiredAddressFields as $field) {
                    if (empty($cartShippingAddress[$field])) {
                        unset($cartSubmissionData['shipments']);
                        break;
                    }
                }
            }
            ////////////////////////////////////////////////////////////////////////////////////
        }

        //Mage::log(var_export($cart_submission_data, true), null, "bolt.log");
        // In some cases discount amount can cause total_amount to be negative. In this case we need to set it to 0.
        if($cartSubmissionData['total_amount'] < 0) {
            $cartSubmissionData['total_amount'] = 0;
        }

        return $this->getCorrectedTotal($calculatedTotal, $cartSubmissionData);
    }


    /**
     * Utility method that attempts to correct totals if the projected total that was calculated from
     * all items and the given discount, does not match the $magento calculated total.  The totals may vary
     * do to an error in the internal Magento code
     *
     * @param int $projectedTotal              total calculated from items, discounts, taxes and shipping
     * @param int $magentoDerivedCartData    totals returned by magento and formatted for Bolt
     *
     * @return array  the corrected Bolt formatted cart data.
     */
    private function getCorrectedTotal($projectedTotal, $magentoDerivedCartData)
    {
        // we'll check if we can simply dividing by two corrects the problem
        if ($projectedTotal == (int)($magentoDerivedCartData['total_amount']/2)) {
            $magentoDerivedCartData["total_amount"] = (int)($magentoDerivedCartData['total_amount']/2);

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
        return $magentoDerivedCartData;

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
     * @param Mage_Sales_Model_Quote  $quote    A quote object with pre-populated addresses
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

        Mage::helper('boltpay')->collectTotals(Mage::getModel('sales/quote')->load($quote->getId()));

        //we should first determine if the cart is virtual
        if($quote->isVirtual()){
            Mage::helper('boltpay')->collectTotals($quote, true);
            $option = array(
                "service"   => Mage::helper('boltpay')->__('No Shipping Required'),
                "reference" => 'noshipping',
                "cost" => 0,
                "tax_amount" => abs(round($quote->getBillingAddress()->getTaxAmount() * 100))
            );
            $response['shipping_options'][] = $option;
            $quote->setTotalsCollectedFlag(true);
            return $response;
        }

        /*****************************************************************************************
         * Calculate tax
         *****************************************************************************************/
        $this->applyShippingRate($quote, null);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->save();

        $originalDiscountedSubtotal = $quote->getSubtotalWithDiscount();

        $rates = $this->getSortedShippingRates($shippingAddress);

        foreach ($rates as $rate) {

            if ($rate->getErrorMessage()) {
                $metaData = array('quote' => var_export($quote->debug(), true));
                Mage::helper('boltpay/bugsnag')->notifyException(
                    new Exception(
                        Mage::helper('boltpay')->__("Error getting shipping option for %s: %s", $rate->getCarrierTitle(), $rate->getErrorMessage())
                    ),
                    $metaData
                );
                continue;
            }

            $this->applyShippingRate($quote, $rate->getCode());

            $label = $rate->getCarrierTitle();
            if ($rate->getMethodTitle()) {
                $label = $label . ' - ' . $rate->getMethodTitle();
            }

            $rateCode = $rate->getCode();

            if (empty($rateCode)) {
                $metaData = array('quote' => var_export($quote->debug(), true));

                Mage::helper('boltpay/bugsnag')->notifyException(
                    new Exception( Mage::helper('boltpay')->__('Rate code is empty. ') . var_export($rate->debug(), true) ),
                    $metaData
                );
            }

            $adjustedShippingAmount = $this->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

            $option = array(
                "service" => $label,
                "reference" => $rateCode,
                "cost" => round($adjustedShippingAmount * 100),
                "tax_amount" => abs(round($shippingAddress->getTaxAmount() * 100))
            );

            $response['shipping_options'][] = $option;
        }

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
            // previously set discount, so the previous discount gets added to each subsequent shipping method that
            // includes a discount. Here we reset it to the original amount to resolve this bug.
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                $item->setData('discount_amount', $item->getOrigData('discount_amount'));
                $item->setData('base_discount_amount', $item->getOrigData('base_discount_amount'));
            }

            Mage::helper('boltpay')->collectTotals($quote, true);

            if(!empty($shippingAddressId) && $shippingAddressId != $shippingAddress->getData('address_id')) {
                $shippingAddress->setData('address_id', $shippingAddressId);
            }
        }
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
     * When Bolt attempts to get shipping rates, it already knows the quote subtotal. However, if there are shipping
     * methods that could affect the subtotal (e.g. $5 off when you choose Next Day Air), then we need to modify the
     * shipping amount so that it makes up for the previous subtotal.
     *
     * @param float $originalDiscountedSubtotal    Original discounted subtotal
     * @param Mage_Sales_Model_Quote    $quote    Quote which has been updated to use new shipping rate
     *
     * @return float    Discount modified as a result of the new shipping method
     */
    public function getAdjustedShippingAmount($originalDiscountedSubtotal, $quote) {
        return $quote->getShippingAddress()->getShippingAmount() + $quote->getSubtotalWithDiscount() - $originalDiscountedSubtotal;
    }

    /**
     * Sets Plugin information in the response headers to callers of the API
     */
    public function setResponseContextHeaders()
    {
        $contextInfo = Mage::helper('boltpay/bugsnag')->getContextInfo();

        Mage::app()->getResponse()
            ->setHeader('User-Agent', 'BoltPay/Magento-' . $contextInfo["Magento-Version"], true)
            ->setHeader('X-Bolt-Plugin-Version', $contextInfo["Bolt-Plugin-Version"], true);
    }


    /**
     * Determines whether the cart has either all items available if Manage Stock is yes for requested quantities,
     * or, if not, those items are eligible for back order.
     *
     * @var Mage_Sales_Model_Quote $quote   The quote that defines the cart
     *
     * @return bool true if the store can accept an order for all items in the cart,
     *              otherwise, false
     */
    public function storeHasAllCartItems($quote)
    {
        foreach ($quote->getAllItems() as $cartItem) {
            if($cartItem->getHasChildren()) {
                continue;
            }

            $_product = Mage::getModel('catalog/product')->load($cartItem->getProductId());
            $stockInfo = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);
            if($stockInfo->getManageStock()){
                if( ($stockInfo->getQty() < $cartItem->getQty()) && !$stockInfo->getBackorders() ){
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * Gets a an order by quote id/order reference
     *
     * @param int|string $quoteId  The quote id which this order was created from
     *
     * @return Mage_Sales_Model_Order   If found, and order with all the details, otherwise a new object order
     */
    public function getOrderByQuoteId($quoteId) {
        /* @var Mage_Sales_Model_Resource_Order_Collection $orderCollection */
        $orderCollection = Mage::getResourceModel('sales/order_collection');

        return $orderCollection
                ->addFieldToFilter('quote_id', $quoteId)
                ->getFirstItem();
    }

    /**
     * Gets the immutable quote id stored in the Bolt transaction.  This is backwards
     * compatible with older versions of the plugin and is suitable for transition
     * installations.
     *
     * @param object $transaction  The Bolt transaction as a php object
     *
     * @return string  The immutable quote id
     */
    public function getImmutableQuoteIdFromTransaction( $transaction ) {
        if (strpos($transaction->order->cart->display_id, '|')) {
            return explode("|", $transaction->order->cart->display_id)[1];
        } else {
            /////////////////////////////////////////////////////////////////
            // Here we address legacy hook format for backward compatibility
            // When placed into production in a merchant that previously used the old format,
            // all their prior orders will have to be accounted for as there are potential
            // hooks like refund, cancel, or order approval that will still be presented in
            // the old format.
            //
            // For $transaction->order->cart->order_reference
            //  - older version stores the immutable quote ID here, and parent ID in getParentQuoteId()
            //  - newer version stores the parent ID here, and immutable quote ID in getParentQuoteId()
            // So, we take the max of getParentQuoteId() and $transaction->order->cart->order_reference
            // which will be the immutable quote ID
            /////////////////////////////////////////////////////////////////
            $potentialQuoteId = (int) $transaction->order->cart->order_reference;
            /** @var Mage_Sales_Model_Quote $potentialQuote */
            $potentialQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($potentialQuoteId);

            $associatedQuoteId = (int) $potentialQuote->getParentQuoteId();

            return max($potentialQuoteId, $associatedQuoteId);
        }

    }

    /**
     * Gets the increment id stored in the Bolt transaction.  This is backwards
     * compatible with older versions of the plugin and is suitable for transition
     * installations.
     *
     * @param object $transaction  The Bolt transaction as a php object
     *
     * @return string  The order increment id
     */
    public function getIncrementIdFromTransaction( $transaction ) {
        return (strpos($transaction->order->cart->display_id, '|'))
            ? explode("|", $transaction->order->cart->display_id)[0]
            : $transaction->order->cart->display_id;
    }
    
    /**
     * Generate (if) secure url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getMagentoUrl($route = '', $params = array()){
        if ((Mage::app()->getStore()->isFrontUrlSecure()) &&
            (Mage::app()->getRequest()->isSecure())) {
            $params["_secure"] = true;
        }
        return Mage::getUrl($route, $params);
    }
}