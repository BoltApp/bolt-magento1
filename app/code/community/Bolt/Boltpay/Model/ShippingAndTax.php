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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_ShippingAndTax
 *
 * The Magento Model class that provides shipping and tax related utility methods
 *
 */
class Bolt_Boltpay_Model_ShippingAndTax extends Bolt_Boltpay_Model_Abstract
{
    /**
     * Applies the address data provide by Bolt to the Magento quote and customer
     *
     * @param Mage_Sales_Model_Quote    $quote             The quote to which the address will be applied
     * @param object                    $boltAddressData   The Bolt formatted address data
     * @param bool                      $clearCurrentData  If true, the current address data in the quote will be
     *                                                     removed prior to adding the Bolt provided address data
     *
     * @return  array   The shipping address applied in Magento compatible format
     *
     * @throws Exception  if the bolt address does not contain an postal or country code
     * @throws Exception  if there is a failure saving the customer or address data to the database
     */
    public function applyBoltAddressData( $quote, $boltAddressData, $clearCurrentData = true ) {

        $region = $boltAddressData->region; // Initialize and set default value for region name

        $directory = Mage::getModel('directory/region')->loadByName($region, $boltAddressData->country_code);

        // If region_id is null, try to load by region code
        if(!$directory->getRegionId()) {
            $directory = Mage::getModel('directory/region')->loadByCode($region, $boltAddressData->country_code);
        }

        // If region_id is not null, use the name and region_id
        if($directory->getRegionId()) {
            $region = $directory->getName(); // For region field should be the name not a code.
        }

        $regionId = $directory->getRegionId(); // This is a required field for calculation: shipping, shopping price rules and etc.

        if (!property_exists($boltAddressData, 'postal_code') || !property_exists($boltAddressData, 'country_code')) {
            $exception = new Exception($this->boltHelper()->__("Address must contain postal_code and country_code."));
            $this->boltHelper()->logException($exception);
            throw $exception;
        }

        $boltStreetData = trim(
            (@$boltAddressData->street_address1 ?: '') . "\n"
            . (@$boltAddressData->street_address2 ?: '') . "\n"
            . (@$boltAddressData->street_address3 ?: '') . "\n"
            . (@$boltAddressData->street_address4 ?: '')
        );

        $addressData = array(
            'email' => @$boltAddressData->email ?: $boltAddressData->email_address,
            'firstname' => @$boltAddressData->first_name,
            'lastname' => @$boltAddressData->last_name,
            'street' => $boltStreetData,
            'company' => @$boltAddressData->company,
            'city' => @$boltAddressData->locality,
            'region' => $region,
            'region_id' => $regionId,
            'postcode' => $boltAddressData->postal_code,
            'country_id' => $boltAddressData->country_code,
            'telephone' => @$boltAddressData->phone ?: $boltAddressData->phone_number
        );

        if ($quote->getCustomerId()) {
            $customerSession = Mage::getSingleton('customer/session');
            $customerSession->setCustomerGroupId($quote->getCustomerGroupId());
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
            $primaryShippingAddress = $customer->getPrimaryShippingAddress();
            $primaryBillingAddress = $customer->getPrimaryBillingAddress();

            /**
             * Saves a billing or shipping address to the customer address book and sets it as default
             *
             * @param string $addressType either 'Shipping'|'Billing'
             */
            $saveAddressFunction = function( $addressType ) use ( $customer, $addressData ) {
                /** @var Mage_Customer_Model_Address $customerAddress */
                $customerAddress = Mage::getModel('customer/address');

                try {
                    $setIsDefaultMethod = 'setIsDefault'.$addressType;
                    $customerAddress
                        ->setCustomerId($customer->getId())
                        ->setCustomer($customer)
                        ->addData($addressData)
                        ->$setIsDefaultMethod('1')
                        ->setSaveInAddressBook('1')
                        ->save();
                } catch ( Exception $e ) {
                    // We catch any exception because they could be thrown from 3rd-party software after save
                    // If so, we have already accomplished what we need to do.  If the error is before, it is
                    // still ok because we are doing a non-critical routine of saving to the address book.
                    $this->boltHelper()->notifyException(
                        $e,
                        ['bolt_address_data' => json_encode($addressData)],
                        'warning'
                    );
                }

                try {
                    $setDefaultMethod = 'setDefault'.$addressType;
                    $customer->addAddress($customerAddress)
                        ->$setDefaultMethod($customerAddress->getId())
                        ->save();
                } catch ( Exception $e ) {
                    // We catch any exception because they could be thrown from 3rd-party software after save
                    // If so, we have already accomplished what we need to do.  If the error is before, it is
                    // still ok because we are doing a non-critical routine of setting a default address
                    $this->boltHelper()->notifyException(
                        $e,
                        ['bolt_address_data' => json_encode($addressData)],
                        'warning'
                    );
                }
            };

            if (!$primaryShippingAddress) { $saveAddressFunction( 'Shipping' ); }
            if (!$primaryBillingAddress) { $saveAddressFunction('Billing' ); }

        }

        if ($clearCurrentData){
            $quote->removeAllAddresses();
            $quote->save();
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->addData($addressData)->save();
        Mage::getModel('boltpay/boltOrder')->correctBillingAddress($billingAddress, $shippingAddress, false);

        return $addressData;
    }

    /**
     * Gets the shipping and the tax estimate for a quote
     *
     * @param Mage_Sales_Model_Quote $quote     A quote object with pre-populated addresses
     * @param object                 $boltOrder The order information sent by Bolt to the shipping and tax endpoint
     *
     * @return array    Bolt shipping and tax response array to be converted to JSON
     *
     * @throws Exception if unable to save  shipping address
     */
    public function getShippingAndTaxEstimate( Mage_Sales_Model_Quote $quote, $boltOrder = null )
    {
        /** @var Mage_Sales_Model_Quote $parentQuote */
        $parentQuote = $quote->getParentQuoteId()
            ? Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId())
            : null;
        benchmark('Creating New Estimate: Loaded parent quote');

        $response = array(
            'shipping_options' => array(),
            'tax_result' => array(
                "amount" => 0
            ),
        );

        try {
            $originalCouponCode = $quote->getCouponCode();
            if ($parentQuote) $quote->setCouponCode($parentQuote->getCouponCode());

            ///////////////////////////////////////////////////////////////////////////
            // Fixed issue where Taxjar wasn't getting loaded because we called
            // $address->collectTotals before we called $quote->collectTotals because
            // collect totals gets cached would never load Taxjar.
            ///////////////////////////////////////////////////////////////////////////
            $this->boltHelper()->collectTotals(Mage::getModel('sales/quote')->load($quote->getId()), true);
            benchmark('Creating New Estimate: Initial loading and collecting totals');
            ///////////////////////////////////////////////////////////////////////////

            //we should first determine if the cart is virtual
            if($quote->isVirtual()){
                $this->boltHelper()->collectTotals($quote, true);
                benchmark('Creating New Estimate: Collected totals for virtual quote');
                $option = array(
                    "service"   => $this->boltHelper()->__('No Shipping Required'),
                    "reference" => 'noshipping',
                    "cost" => 0,
                    "tax_amount" => abs(round($quote->getBillingAddress()->getTaxAmount() * 100))
                );
                $response['shipping_options'][] = $option;
                $quote->setTotalsCollectedFlag(true);
                return $response;
            }

            $this->applyShippingRate($quote, null, false);
            benchmark('Creating New Estimate: Cleared all applied shipping rates');

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->save();
            benchmark('Creating New Estimate: Collected shipping rates on address');

            $originalDiscountTotal = 0;
            if ($boltOrder) {
                if (@$boltOrder->cart->discounts) {
                    $discounts = $boltOrder->cart->discounts;
                    for(
                        $i = 0, $originalDiscountTotal = 0;
                        $i < count($discounts);
                        $originalDiscountTotal += $discounts[$i]->amount/100, $i++
                    );
                }
            }
            benchmark('Creating New Estimate: Summed Bolt reported discounts');

            $rates = $this->getSortedShippingRates($shippingAddress);
            benchmark('Creating New Estimate: Sorted shipping rates');

            /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
            foreach ($rates as $rate) {

                if ($rate->getErrorMessage()) {
                    $exception = new Exception($this->boltHelper()->__("Error getting shipping option for %s: %s", $rate->getCarrierTitle(), $rate->getErrorMessage()));
                    $metaData = array('quote' => var_export($quote->debug(), true));
                    $this->boltHelper()->logWarning($exception->getMessage(),$metaData);
                    $this->boltHelper()->notifyException($exception, $metaData);
                    benchmark("Creating New Estimate: Logged shipping rate error for {$rate->getCarrierTitle()}");
                    continue;
                }

                if ($parentQuote) $quote->setCouponCode($parentQuote->getCouponCode());

                $quote->setShouldSkipThisShippingMethod(false);
                benchmark("Creating New Estimate: Applying shipping rate {$rate->getCode()}");
                $this->applyShippingRate($quote, $rate->getCode(), false);
                benchmark("Creating New Estimate: Applied shipping rate {$rate->getCode()} - {$rate->getCarrierTitle()}");

                $rateCode = $rate->getCode();

                if ($quote->getShouldSkipThisShippingMethod() || empty($rateCode)) {
                    ////////////////////////////////////////////////////////////////////////////
                    // The rate code theoretically should never be empty at this point.
                    // The merchant may also choose to skip any particular shipping method by
                    // via $quote->setShouldSkipThisShippingMethod(true) via the events
                    // 'bolt_boltpay_shipping_method_applied_before' or
                    // 'bolt_boltpay_shipping_method_applied_after'.
                    // If any of the above are true, then this shipping method should not be
                    // included as a Bolt shipping option.
                    ////////////////////////////////////////////////////////////////////////////
                    if (empty($rateCode)) {
                        $exception = new Exception( $this->boltHelper()->__('Rate code is empty. ') . var_export($rate->debug(), true) );
                        $metaData = array('quote' => var_export($quote->debug(), true));
                        $this->boltHelper()->logWarning($exception->getMessage(),$metaData);
                        $this->boltHelper()->notifyException($exception,$metaData, 'warning');
                        benchmark('Creating New Estimate: Logged to Bugsnag and DataDog empty shipping rate code');
                    }
                    continue;
                }

                $adjustedShippingAmount = $this->getAdjustedShippingAmount($originalDiscountTotal, $quote, $boltOrder);
                benchmark('Creating New Estimate: Adjusted Shipping Amount');

                $option = array(
                    "service" => $this->getShippingLabel($rate),
                    "reference" => $rateCode,
                    "cost" => round($adjustedShippingAmount * 100),
                    "tax_amount" => abs(round($shippingAddress->getTaxAmount() * 100))
                );

                $response['shipping_options'][] = $option;

                Mage::dispatchEvent(
                    'bolt_boltpay_shipping_option_added',
                    array(
                        'quote'=> $quote,
                        'rate' => $rate,
                        'option' => $option
                    )
                );
                benchmark('Creating New Estimate: dispatched event bolt_boltpay_shipping_option_added');
            }

        } catch ( Exception $exception ) {
            // catching and rethrowing exception in place of finally to support PHP 5.4
            $quote->setCouponCode($originalCouponCode);
            throw $exception;
        }

        $quote->setCouponCode($originalCouponCode);

        return $response;
    }

    /**
     * Applies shipping rate to quote. Clears previously calculated discounts by clearing address id.
     *
     * @param Mage_Sales_Model_Quote $quote                     Quote which has been updated to use new shipping rate
     * @param string                 $shippingRateCode          Shipping rate code composed of {carrier}_{method}
     * @param bool                   $shouldRecalculateShipping Determines if shipping should be recalculated
     */
    public function applyShippingRate($quote, $shippingRateCode, $shouldRecalculateShipping = true ) {

        $shippingAddress = $quote->getShippingAddress();
        benchmark("Creating New Estimate: $shippingRateCode: retrieved shipping address from quote");

        if (!empty($shippingAddress)) {

            // Flagging address as new is required to force collectTotals to recalculate discounts
            $shippingAddress->isObjectNew(true);
            $shippingAddressId = $shippingAddress->getData('address_id');

            $shippingAddress
                ->setShippingMethod($shippingRateCode)
                ->setCollectShippingRates($shouldRecalculateShipping);

            // When multiple shipping methods apply a discount to the sub-total, collect totals doesn't clear the
            // previously set discount, so the previous discount gets added to each subsequent shipping method that
            // includes a discount. Here we reset it to the original amount to resolve this bug.
            /** @var Mage_Sales_Model_Quote_Item[] $quoteItems */
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                $item->setData('discount_amount', $item->getOrigData('discount_amount'));
                $item->setData('base_discount_amount', $item->getOrigData('base_discount_amount'));
            }
            benchmark("Creating New Estimate: $shippingRateCode: resetting discount amounts");

            Mage::dispatchEvent(
                'bolt_boltpay_shipping_method_applied_before',
                array(
                    'quote'=> $quote,
                    'shipping_method_code' => $shippingRateCode
                )
            );
            benchmark("Creating New Estimate: $shippingRateCode: dispatched event bolt_boltpay_shipping_method_applied_before");

            $this->boltHelper()->collectTotals($quote, true);
            benchmark("Creating New Estimate: $shippingRateCode: collected quote totals");

            if(!empty($shippingAddressId) && $shippingAddressId != $shippingAddress->getData('address_id')) {
                $shippingAddress->setData('address_id', $shippingAddressId);
            }

            Mage::dispatchEvent(
                'bolt_boltpay_shipping_method_applied_after',
                array(
                    'quote'=> $quote,
                    'shipping_method_code' => $shippingRateCode
                )
            );
            benchmark("Creating New Estimate: $shippingRateCode: dispatched event bolt_boltpay_shipping_method_applied_after");
        }
    }

    /**
     * @param Mage_Sales_Model_Quote_Address $address
     * @return array
     */
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
     * @param float                     $originalDiscountTotal    Original discount
     * @param Mage_Sales_Model_Quote    $quote    Quote which has been updated to use new shipping rate
     * @param object                    $boltOrder  The order data sent as reported by Bolt
     *
     * @return float    Discount modified as a result of the new shipping method
     */
    public function getAdjustedShippingAmount($originalDiscountTotal, $quote, $boltOrder = null ) {
        $newDiscountTotal = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
        $adjustedShippingAmount = $quote->getShippingAddress()->getShippingAmount() + $originalDiscountTotal - $newDiscountTotal;

        return $this->boltHelper()->doFilterEvent(
            'bolt_boltpay_filter_adjusted_shipping_amount',
            $adjustedShippingAmount,
            array('originalDiscountTotal' => $originalDiscountTotal, 'quote'=>$quote, 'boltOrder' => $boltOrder)
        );
    }

    /**
     * Returns user-visible label for given shipping rate.
     *
     * @param   Mage_Sales_Model_Quote_Address_Rate $rate
     * @return  string
     */
    public function getShippingLabel($rate) {
        $carrier = $rate->getCarrierTitle();
        $title = $rate->getMethodTitle();

        $shippingLabel = $carrier . " - " . $title;

        // Apply adhoc rules to return concise string.
        if (!$title) {
            $shippingLabel = $carrier;
        } else if (
            ($carrier === "Shipping Table Rates")
            || ($carrier === "United Parcel Service" && substr( $title, 0, 3 ) === "UPS")
            || (strncasecmp( $carrier, $title, strlen($carrier) ) === 0)
        )
        {
            $shippingLabel = $title;
        }

        return $this->boltHelper()->doFilterEvent( 'bolt_boltpay_filter_shipping_label', $shippingLabel, $rate);
    }

    /**
     * Returns a whether P.O. box addresses are allowed for this store
     *
     * @return bool     true if P.O. boxes are allowed.  Otherwise, false.
     */
    public function isPOBoxAllowed()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/allow_po_box');
    }

    /**
     * Checks whether a P.O. Box exist in the addresses given
     *
     * @param string $address1 The address to be checked for a P.O. Box matching string
     * @param string $address2 If set, second address to be checked.  Useful for checking both shipping and billing in one call.
     *
     * @return bool     returns true only if any of the provided addresses contain a P.O. Box.  Otherwise, false
     */
    public function doesAddressContainPOBox($address1, $address2 = null)
    {
        $poBoxRegex = /** @lang PhpRegExp */ '/^\s*((P(OST)?.?\s*(O(FF(ICE)?)?|B(IN|OX))+.?\s+(B(IN|OX))?)|B(IN|OX))/i';
        $poBoxRegexStrict = /** @lang PhpRegExp */ '/(?:P(?:ost(?:al)?)?[\.\-\s]*(?:(?:O(?:ffice)?[\.\-\s]*)?B(?:ox|in|\b|\d)|o(?:ffice|\b)(?:[-\s]*\d)|code)|box[-\s\b]*\d)/i';

        return preg_match($poBoxRegex, $address1)
            || preg_match($poBoxRegex, $address2)
            || preg_match($poBoxRegexStrict, $address1)
            || preg_match($poBoxRegexStrict, $address2);
    }
}
