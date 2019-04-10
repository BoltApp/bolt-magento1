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
     *  Updates the shipping address data and, if necessary, the fills in missing
     *  billing address data with shipping address data
     *
     *
     * @param Mage_Sales_Model_Quote    $quote             The quote to which the address will be applied
     * @param array                     $shippingAddress   The Bolt formatted address data
     *
     * @return  array   The shipping address applied in Magento compatible format
     */
    public function applyShippingAddressToQuote( $quote, $shippingAddress ) {

        $directory = Mage::getModel('directory/region')->loadByName($shippingAddress->region, $shippingAddress->country_code);
        $region = $directory->getName(); // For region field should be the name not a code.
        $regionId = $directory->getRegionId(); // This is require field for calculation: shipping, shopping price rules and etc.

        if (!property_exists($shippingAddress, 'postal_code') || !property_exists($shippingAddress, 'country_code')) {
            throw new Exception($this->boltHelper()->__("Address must contain postal_code and country_code."));
        }

        $shippingStreet = trim(
            (@$shippingAddress->street_address1 ?: '') . "\n"
            . (@$shippingAddress->street_address2 ?: '') . "\n"
            . (@$shippingAddress->street_address3 ?: '') . "\n"
            . (@$shippingAddress->street_address4 ?: '')
        );
            
        $addressData = array(
            'email' => @$shippingAddress->email ?: $shippingAddress->email_address,
            'firstname' => @$shippingAddress->first_name,
            'lastname' => @$shippingAddress->last_name,
            'street' => $shippingStreet,
            'company' => @$shippingAddress->company,
            'city' => @$shippingAddress->locality,
            'region' => $region,
            'region_id' => $regionId,
            'postcode' => $shippingAddress->postal_code,
            'country_id' => $shippingAddress->country_code,
            'telephone' => @$shippingAddress->phone ?: $shippingAddress->phone_number
        );

        if ($quote->getCustomerId()) {
            $customerSession = Mage::getSingleton('customer/session');
            $customerSession->setCustomerGroupId($quote->getCustomerGroupId());
            $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
            $address = $customer->getPrimaryShippingAddress();

            if (!$address) {
                $address = Mage::getModel('customer/address');

                $address->setCustomerId($customer->getId())
                    ->setCustomer($customer)
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1')
                    ->save();


                $address->addData($addressData);
                $address->save();

                $customer->addAddress($address)
                    ->setDefaultShippingg($address->getId())
                    ->save();
            }
        }

        // https://github.com/BoltApp/bolt-magento1/pull/255
        if (strpos(Mage::getVersion(), '1.7') !== 0){
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
     * @param Mage_Sales_Model_Quote  $quote    A quote object with pre-populated addresses
     *
     * @return array    Bolt shipping and tax response array to be converted to JSON
     */
    public function getShippingAndTaxEstimate( $quote )
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

            $originalDiscountedSubtotal = $quote->getSubtotalWithDiscount();

            $rates = $this->getSortedShippingRates($shippingAddress);

            foreach ($rates as $rate) {

                if ($rate->getErrorMessage()) {
                    $metaData = array('quote' => var_export($quote->debug(), true));
                    $this->boltHelper()->notifyException(
                        new Exception(
                            $this->boltHelper()->__("Error getting shipping option for %s: %s", $rate->getCarrierTitle(), $rate->getErrorMessage())
                        ),
                        $metaData
                    );
                    continue;
                }

                if ($parentQuote) $quote->setCouponCode($parentQuote->getCouponCode());
                $this->applyShippingRate($quote, $rate->getCode());

                $rateCode = $rate->getCode();

                if (empty($rateCode)) {
                    $metaData = array('quote' => var_export($quote->debug(), true));

                    $this->boltHelper()->notifyException(
                        new Exception( $this->boltHelper()->__('Rate code is empty. ') . var_export($rate->debug(), true) ),
                        $metaData
                    );
                }

                $adjustedShippingAmount = $this->getAdjustedShippingAmount($originalDiscountedSubtotal, $quote);

                $option = array(
                    "service" => $this->getShippingLabel($rate),
                    "reference" => $rateCode,
                    "cost" => round($adjustedShippingAmount * 100),
                    "tax_amount" => abs(round($shippingAddress->getTaxAmount() * 100))
                );

                $response['shipping_options'][] = $option;
            }
        } finally {
            $quote->setCouponCode($originalCouponCode);
        }

        return $response;
    }

    /**
     * Applies shipping rate to quote. Clears previously calculated discounts by clearing address id.
     *
     * @param Mage_Sales_Model_Quote $quote    Quote which has been updated to use new shipping rate
     * @param string $shippingRateCode         Shipping rate code composed of {carrier}_{method}
     */
    public function applyShippingRate($quote, $shippingRateCode, $clearTotalsCollectedFlag = true ) {
        $shippingAddress = $quote->getShippingAddress();

        if (!empty($shippingAddress)) {
            // Flagging address as new is required to force collectTotals to recalculate discounts
            $shippingAddress->isObjectNew(true);
            $shippingAddressId = $shippingAddress->getData('address_id');

            $shippingAddress
                ->setShippingMethod($shippingRateCode)
                ->setCollectShippingRates(true);

            //            // When multiple shipping methods apply a discount to the sub-total, collect totals doesn't clear the
            //            // previously set discount, so the previous discount gets added to each subsequent shipping method that
            // includes a discount. Here we reset it to the original amount to resolve this bug.
            $quoteItems = $quote->getAllItems();
            foreach ($quoteItems as $item) {
                $item->setData('discount_amount', $item->getOrigData('discount_amount'));
                $item->setData('base_discount_amount', $item->getOrigData('base_discount_amount'));
            }

            $this->boltHelper()->collectTotals($quote, $clearTotalsCollectedFlag);

            if(!empty($shippingAddressId) && $shippingAddressId != $shippingAddress->getData('address_id')) {
                $shippingAddress->setData('address_id', $shippingAddressId);
            }

            Mage::dispatchEvent(
                'bolt_boltpay_shipping_method_applied',
                array(
                    'quote'=> $quote,
                    'shippingMethodCode' => $shippingRateCode
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
     * @param float $originalDiscountedSubtotal    Original discounted subtotal
     * @param Mage_Sales_Model_Quote    $quote    Quote which has been updated to use new shipping rate
     *
     * @return float    Discount modified as a result of the new shipping method
     */
    public function getAdjustedShippingAmount($originalDiscountedSubtotal, $quote) {
        return $quote->getShippingAddress()->getShippingAmount() + $quote->getSubtotalWithDiscount() - $originalDiscountedSubtotal;
    }

    /**
     * Returns user-visible label for given shipping rate.
     *
     * @param   object rate
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
        return $carrier . " - " . $title;
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
}
