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
 * Class Bolt_Bolt_Helper_Api
 *
 * The Magento Helper class that provides utility methods for the following operations:
 *
 * 1. Fetching the transaction info by calling the Fetch Bolt API endpoint.
 * 2. Verifying Hook Requests.
 * 3. Saves the order in Magento system.
 * 4. Makes the calls towards Bolt API.
 * 5. Generates Bolt order submission data.
 */
class Bolt_Bolt_Helper_Api extends Bolt_Boltpay_Helper_Api
{
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

    const MERCHANT_BACK_OFFICE = 'merchant_back_office';

    /**
     * Generates cart submission data for sending to Bolt order cart field.
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Sales_Model_Quote_Item[] $items
     * @param bool $multipage
     * @return array
     * @throws Mage_Core_Model_Store_Exception
     * @throws Varien_Exception
     */
    public function buildCart($quote, $items, $multipage)
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
                        'quantity'     => $item->getQty()
                        //'type'         => $type
                    );
                }, $items
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
                if($discount=='amgiftcard'){
                    $giftCardsBalance = $quote->getAmGiftCardsAmount();
                    $discountAmount = abs(round(($giftCardsBalance) * 100));
                }elseif ($discount == 'amstcred') {
                    $customerId = $quote->getCustomer()->getId();
                    if ($customerId) $discountAmount = abs(round($this->getStoreCreditBalance($customerId) * 100));
                } else {
                    // Some extensions keep discount totals as positive values,
                    // others as negative, which is the Magento default.
                    // Using the absolute value.
                    $discountAmount = abs(round($amount * 100));
                }

                $description = $totals[$discount]->getAddress()->getDiscountDescription();
                $description = Mage::helper('boltpay')->__('Discount (%s)', $description);

                $cartSubmissionData['discounts'][] = array(
                    'amount'      => $discountAmount,
                    'description' => $description,
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
     * @param int $projectedTotal total calculated from items, discounts, taxes and shipping
     * @param int $magentoDerivedCartData totals returned by magento and formatted for Bolt
     *
     * @return array  the corrected Bolt formatted cart data.
     */
    private function getCorrectedTotal($projectedTotal, $magentoDerivedCartData)
    {
        // we'll check if we can simply dividing by two corrects the problem
        if ($projectedTotal == (int)($magentoDerivedCartData['total_amount'] / 2)) {
            $magentoDerivedCartData["total_amount"] = (int)($magentoDerivedCartData['total_amount'] / 2);

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
     * Gets the shipping and the tax estimate for a quote
     *
     * @param Mage_Sales_Model_Quote $quote A quote object with pre-populated addresses
     *
     * @return array    Bolt shipping and tax response array to be converted to JSON
     */
    public function getShippingAndTaxEstimate($quote)
    {
        $response = array(
            'shipping_options' => array(),
            'tax_result'       => array(
                "amount" => 0
            ),
        );

        Mage::helper('boltpay')->collectTotals(Mage::getModel('sales/quote')->load($quote->getId()));

        //we should first determine if the cart is virtual
        if ($quote->isVirtual()) {
            Mage::helper('boltpay')->collectTotals($quote, true);
            $option = array(
                "service"    => Mage::helper('boltpay')->__('No Shipping Required'),
                "reference"  => 'noshipping',
                "cost"       => 0,
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

            if (strpos($rate->getMethodTitle(), 'In-Store Pick Up') !== false){
                continue;
            }

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

            $rateCode = $rate->getCode();

            if (empty($rateCode)) {
                $metaData = array('quote' => var_export($quote->debug(), true));

                Mage::helper('boltpay/bugsnag')->notifyException(
                    new Exception(Mage::helper('boltpay')->__('Rate code is empty. ') . var_export($rate->debug(), true)),
                    $metaData
                );
            }

            $adjustedShippingAmount = $this->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

            $option = array(
                "service"    => $this->getShippingLabel($rate),
                "reference"  => $rateCode,
                "cost"       => round($adjustedShippingAmount * 100),
                "tax_amount" => abs(round($shippingAddress->getTaxAmount() * 100))
            );

            $response['shipping_options'][] = $option;
        }

        return $response;
    }

    /**
     * Returns user-visible label for given shipping rate.
     *
     * @param   object rate
     *
     * @return  string
     */
    public function getShippingLabel($rate)
    {
        $carrier = $rate->getCarrierTitle();
        $title = $rate->getMethodTitle();
        if (!$title) {
            return $carrier;
        }
        // Apply adhoc rules to return concise string.
        if ($carrier === "Shipping Table Rates") {
            $methodId = explode('amtable_amtable', $rate->getCode());
            $methodId = isset($methodId[1]) ? $methodId[1] : false;
            $estimateDelivery = $methodId ? Mage::helper('arrivaldates')->getEstimateHtml($methodId, false) : '';
            return $title . ' -- ' . $estimateDelivery;
        }
        if ($carrier === "United Parcel Service" && substr($title, 0, 3) === "UPS") {
            return $title;
        }
        if (strncasecmp($carrier, $title, strlen($carrier)) === 0) {
            return $title;
        }

        return $carrier . " - " . $title;
    }

    /**
     * Function get store credit balance of customer
     *
     * @param $customerId
     *
     * @return mixed
     */
    protected function getStoreCreditBalance($customerId)
    {
        return Mage::getModel('amstcred/balance')->getCollection()
            ->addFilter('customer_id', $customerId)->getFirstitem()->getAmount();
    }

    /**
     * add customer_note to amasty_amorderattr_order_attribute when checkout by Bolt
     * @param $reference
     * @param null $sessionQuoteId
     * @param bool $isAjaxRequest
     * @param null $transaction
     * @throws Exception
     */
    public function createOrder($reference, $sessionQuoteId = null, $isAjaxRequest = false, $transaction = null){

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
                throw new Exception(Mage::helper('boltpay')->__("The expected immutable quote [$immutableQuoteId] is missing from the Magento system.  Were old quotes recently removed from the database?"));
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
            if ( !$immutableQuote->getIsActive() && $transaction->indemnification_reason !== self::MERCHANT_BACK_OFFICE) {
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
            } else if (!$parentQuote->getIsActive() && $transaction->indemnification_reason !== self::MERCHANT_BACK_OFFICE) {
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
                if ($service) {
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
                        if (!$immutableQuote->isVirtual()) {
                            $errorMessage = Mage::helper('boltpay')->__('Shipping method not found');
                            $metaData = array(
                                'transaction'      => $transaction,
                                'rates'            => $this->getRatesDebuggingData($rates),
                                'service'          => $service,
                                'shipping_address' => var_export($shippingAddress->debug(), true),
                                'quote'            => var_export($immutableQuote->debug(), true)
                            );
                            Mage::helper('boltpay/bugsnag')->notifyException(new Exception($errorMessage), $metaData);
                        }
                    }
                }else{

                    if (!$immutableQuote->isVirtual()) {
                        if ($immutableQuote->getStorePickupId()) {
                            Mage::getSingleton('core/session')->setIsStorePickup(true);
                        }
                    }
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
                    throw new Exception(
                        Mage::helper('boltpay')->__("The parent quote %s is currently being processed or has been processed.",
                            $immutableQuote->getParentQuoteId() )
                    );
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

        try {

            if ($order->getId()) {
                if (isset($transaction->order->user_note)) {
                    Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                        'customerordercomments', $transaction->order->user_note
                    )->save();
                }
                
                if(Mage::getSingleton('core/session')->getBoltOnePageComments()) {
                    Mage::getModel('amorderattr/attribute')->load($order->getId(), 'order_id')->setData(
                        'customerordercomments', Mage::getSingleton('core/session')->getBoltOnePageComments()
                    )->save();
                    Mage::getSingleton('core/session')->unsBoltOnePageComments();
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
