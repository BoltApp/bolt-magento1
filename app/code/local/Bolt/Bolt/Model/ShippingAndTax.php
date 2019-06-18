<?php

class Bolt_Bolt_Model_ShippingAndTax extends Bolt_Boltpay_Model_ShippingAndTax
{
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
                    new Exception( Mage::helper('boltpay')->__('Rate code is empty. ') . var_export($rate->debug(), true) ),
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

        return $response;
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
            $methodId = explode('amtable_amtable', $rate->getCode());
            $methodId = isset($methodId[1]) ? $methodId[1] : false;
            $estimateDelivery = $methodId ? Mage::helper('arrivaldates')->getEstimateHtml($methodId, false) : '';
            return $title . ' -- ' . $estimateDelivery;
        }
        if ($carrier === "United Parcel Service" && substr( $title, 0, 3 ) === "UPS") {
            return $title;
        }
        if (strncasecmp( $carrier, $title, strlen($carrier) ) === 0) {
            return $title;
        }
        return $carrier . " - " . $title;
    }
}
