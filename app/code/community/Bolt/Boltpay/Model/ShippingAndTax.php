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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
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
     * @param array                     $boltAddressData   The Bolt formatted address data
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

            if (!$primaryShippingAddress) {
                /** @var Mage_Customer_Model_Address $primaryShippingAddress */
                $primaryShippingAddress = Mage::getModel('customer/address');

                $primaryShippingAddress
                    ->setCustomerId($customer->getId())
                    ->setCustomer($customer)
                    ->addData($addressData)
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1')
                    ->save();

                $customer->addAddress($primaryShippingAddress)
                    ->setDefaultShipping($primaryShippingAddress->getId())
                    ->save();
            }

            if (!$primaryBillingAddress) {
                /** @var Mage_Customer_Model_Address $primaryBillingAddress */
                $primaryBillingAddress = Mage::getModel('customer/address');

                $primaryBillingAddress->setCustomerId($customer->getId())
                    ->setCustomer($customer)
                    ->addData($addressData)
                    ->setIsDefaultBilling('1')
                    ->setSaveInAddressBook('1')
                    ->save();

                $customer->addAddress($primaryBillingAddress)
                    ->setDefaultBilling($primaryBillingAddress->getId())
                    ->save();
            }
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
     * @param Mage_Sales_Model_Quote  $quote      A quote object with pre-populated addresses
     * @param object                  $boltOrder  The order information sent by Bolt to the shipping and tax endpoint
     *
     * @return array    Bolt shipping and tax response array to be converted to JSON
     */
    public function getShippingAndTaxEstimate( Mage_Sales_Model_Quote $quote, $boltOrder = null )
    {
        /** @var Mage_Sales_Model_Quote $parentQuote */
        $parentQuote = $quote->getParentQuoteId()
            ? Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getParentQuoteId())
            : null;

        $response = array(
            'shipping_options' => array(),
            'tax_result' => array(
                "amount" => 0
            ),
        );

        try {
            $originalCouponCode = $quote->getCouponCode();
            if ($parentQuote) $quote->setCouponCode($parentQuote->getCouponCode());
            $this->boltHelper()->collectTotals(Mage::getModel('sales/quote')->load($quote->getId()), true);

            //we should first determine if the cart is virtual
            if($quote->isVirtual()){
                $this->boltHelper()->collectTotals($quote, true);
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

            $this->applyShippingRate($quote, null);

            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->save();

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

            $rates = $this->getSortedShippingRates($shippingAddress);

            /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
            foreach ($rates as $rate) {

                if ($rate->getErrorMessage()) {
                    $exception = new Exception($this->boltHelper()->__("Error getting shipping option for %s: %s", $rate->getCarrierTitle(), $rate->getErrorMessage()));
                    $metaData = array('quote' => var_export($quote->debug(), true));
                    $this->boltHelper()->logWarning($exception->getMessage(),$metaData);
                    $this->boltHelper()->notifyException($exception->getMessage(), $metaData);
                    continue;
                }

                if ($parentQuote) $quote->setCouponCode($parentQuote->getCouponCode());

                $quote->setShouldSkipThisShippingMethod(false);
                $this->applyShippingRate($quote, $rate->getCode());

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
                    }
                    continue;
                }

                $adjustedShippingAmount = $this->getAdjustedShippingAmount($originalDiscountTotal, $quote);

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
            }

        } finally {
            $quote->setCouponCode($originalCouponCode);
        }

        return $response;
    }

    /**
     * Applies shipping rate to quote. Clears previously calculated discounts by clearing address id.
     *
     * @param Mage_Sales_Model_Quote $quote              Quote which has been updated to use new shipping rate
     * @param string                 $shippingRateCode   Shipping rate code composed of {carrier}_{method}
     */
    public function applyShippingRate($quote, $shippingRateCode) {

        $shippingAddress = $quote->getShippingAddress();

        if (!empty($shippingAddress)) {

            // Flagging address as new is required to force collectTotals to recalculate discounts
            $shippingAddress->isObjectNew(true);
            $shippingAddressId = $shippingAddress->getData('address_id');

            $shippingAddress
                ->setShippingMethod($shippingRateCode)
                ->setCollectShippingRates(true);

            // When multiple shipping methods apply a discount to the sub-total, collect totals doesn't clear the
            // previously set discount, so the previous discount gets added to each subsequent shipping method that
            // includes a discount. Here we reset it to the original amount to resolve this bug.
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                $item->setData('discount_amount', $item->getOrigData('discount_amount'));
                $item->setData('base_discount_amount', $item->getOrigData('base_discount_amount'));
            }

            Mage::dispatchEvent(
                'bolt_boltpay_shipping_method_applied_before',
                array(
                    'quote'=> $quote,
                    'shipping_method_code' => $shippingRateCode
                )
            );

            $this->boltHelper()->collectTotals($quote, true);

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
     * @param float                     $originalDiscountTotal    Original discount
     * @param Mage_Sales_Model_Quote    $quote    Quote which has been updated to use new shipping rate
     *
     * @return float    Discount modified as a result of the new shipping method
     */
    public function getAdjustedShippingAmount($originalDiscountTotal, $quote ) {
        $newDiscountTotal = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();
        return $quote->getShippingAddress()->getShippingAmount() + $originalDiscountTotal - $newDiscountTotal;
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
        if (!$title) {
            return $carrier;
        }

        // Apply adhoc rules to return concise string.
        if ($carrier === "Shipping Table Rates") {
            return $title;
        }
        if ($carrier === "United Parcel Service" && substr( $title, 0, 3 ) === "UPS") {
            return $title;
        }
        if (strncasecmp( $carrier, $title, strlen($carrier) ) === 0) {
            return $title;
        }

        return $this->boltHelper()->doFilterEvent( 'bolt_boltpay_filter_shipping_label', $carrier . " - " . $title, $rate);
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
     * @param $address1      The address to be checked for a P.O. Box matching string
     * @param $address2      If set, second address to be checked.  Useful for checking both shipping and billing in one call.
     *
     * @return bool     returns true only if any of the provided addresses contain a P.O. Box.  Otherwise, false
     */
    public function doesAddressContainPOBox($address1, $address2 = null)
    {
        $poBoxRegex = '/^\s*((P(OST)?.?\s*(O(FF(ICE)?)?|B(IN|OX))+.?\s+(B(IN|OX))?)|B(IN|OX))/i';
        $poBoxRegexStrict = '/(?:P(?:ost(?:al)?)?[\.\-\s]*(?:(?:O(?:ffice)?[\.\-\s]*)?B(?:ox|in|\b|\d)|o(?:ffice|\b)(?:[-\s]*\d)|code)|box[-\s\b]*\d)/i';

        return preg_match($poBoxRegex, $address1)
            || preg_match($poBoxRegex, $address2)
            || preg_match($poBoxRegexStrict, $address1)
            || preg_match($poBoxRegexStrict, $address2);
    }

    /**
     * Applies the address data provide by Bolt to the Magento quote and customer
     *
     * @param Mage_Sales_Model_Quote    $quote             The quote to which the address will be applied
     * @param array                     $shippingAddress   The Bolt formatted address data
     *
     * @return  array   The shipping address applied in Magento compatible format
     *
     * @throws Exception  if the bolt address does not contain an postal or country code
     * @throws Exception  if there is a failure saving the customer or address data to the database
     *
     * @deprecated Use {@see Bolt_Boltpay_Model_ShippingAndTax::applyBoltAddressData()} instead
     */
    public function applyShippingAddressToQuote($quote, $shippingAddress) {
        return $this->applyBoltAddressData($quote, $shippingAddress);
    }
}