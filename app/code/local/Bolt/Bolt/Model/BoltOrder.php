<?php

class Bolt_Bolt_Model_BoltOrder extends Bolt_Boltpay_Model_BoltOrder
{
    /**
     * Generates cart submission data for sending to Bolt order cart field.
     *
     * @param Mage_Sales_Model_Quote        $quote      Magento quote instance
     * @param Mage_Sales_Model_Quote_Item[] $items      array of Magento products
     * @param bool                          $multipage  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     *
     * @return array            The cart data part of the order payload to be sent as to bolt in API call as a PHP array
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
                        'sku'          => $item->getSku(),
                        'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                        'total_amount' => round($item->getCalculationPrice() * 100) * $item->getQty(),
                        'unit_price'   => round($item->getCalculationPrice() * 100),
                        'quantity'     => $item->getQty(),
//                        'type'         => $type,
                        'properties' => $this->getItemProperties($item)
                    );
                }, $items
            ),
            'currency' => $quote->getQuoteCurrencyCode(),
        );
        ///////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // Check for discounts and include them in the submission data if found.
        /////////////////////////////////////////////////////////////////////////
        $this->addDiscounts($totals, $cartSubmissionData,$quote);
        $this->dispatchCartDataEvent('bolt_boltpay_discounts_applied_to_bolt_order', $quote, $cartSubmissionData);
        $totalDiscount = isset($cartSubmissionData['discounts']) ? array_sum(array_column($cartSubmissionData['discounts'], 'amount')) : 0;

        $calculatedTotal -= $totalDiscount;
        /////////////////////////////////////////////////////////////////////////

        if ($multipage) {
            /////////////////////////////////////////////////////////////////////////////////////////
            // For multi-page checkout type send only subtotal, do not include shipping and tax info.
            /////////////////////////////////////////////////////////////////////////////////////////
            $totalKey = @$totals['subtotal'] ? 'subtotal' : 'grand_total';

            $cartSubmissionData['total_amount'] = round($totals[$totalKey]->getValue() * 100);
            $cartSubmissionData['total_amount'] -= $totalDiscount;
            /////////////////////////////////////////////////////////////////////////////////////////
        } else {

            $billingAddress  = $quote->getBillingAddress();
            $shippingAddress = $quote->getShippingAddress();

            $this->correctBillingAddress($billingAddress, $shippingAddress);

            $customerEmail = $this->getCustomerEmail($quote);

            $billingRegion = $billingAddress->getRegion();
            if (
                empty($billingRegion) &&
                !in_array($billingAddress->getCountry(), $this->countriesRequiringRegion)
            )
            {
                $billingRegion = $billingAddress->getCity();
            }

            ///////////////////////////////////////////
            // Include billing address info if defined.
            ///////////////////////////////////////////
            if ($billingAddress) {
                $cartSubmissionData['billing_address'] = array(
                    'street_address1' => $billingAddress->getStreet1(),
                    'street_address2' => $billingAddress->getStreet2(),
                    'street_address3' => $billingAddress->getStreet3(),
                    'street_address4' => $billingAddress->getStreet4(),
                    'first_name'      => $billingAddress->getFirstname(),
                    'last_name'       => $billingAddress->getLastname(),
                    'locality'        => $billingAddress->getCity(),
                    'region'          => $billingRegion,
                    'postal_code'     => $billingAddress->getPostcode() ?: '-',
                    'country_code'    => $billingAddress->getCountry(),
                    'phone'           => $billingAddress->getTelephone(),
                    'email'           => $billingAddress->getEmail() ?: ($customerEmail ?: $shippingAddress->getEmail()),
                );
            }
            $this->dispatchCartDataEvent('bolt_boltpay_correct_billing_address_for_bolt_order', $quote, $cartSubmissionData['billing_address']);

            ///////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////////////
            // For one page checkout type include tax and shipment / address data in submission.
            ////////////////////////////////////////////////////////////////////////////////////


            if (Mage::app()->getStore()->isAdmin()) {
                /* @var Mage_Adminhtml_Block_Sales_Order_Create_Totals $totalsBlock */
                $totalsBlock =  Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_totals_shipping");

                /* @var Mage_Sales_Model_Quote_Address_Total $grandTotal */
                $grandTotal = $totalsBlock->getTotals()['grand_total'];
                $cartSubmissionData['total_amount'] = (int) round($grandTotal->getValue() * 100);

                /* @var Mage_Sales_Model_Quote_Address_Total $taxTotal */
                $taxTotal = $totalsBlock->getTotals()['tax'];
                $cartSubmissionData['tax_amount'] = $this->getTaxForAdmin($taxTotal);
            } else {
                $cartSubmissionData['total_amount'] = round($totals["grand_total"]->getValue() * 100);
                $cartSubmissionData['tax_amount'] = $this->getTax($totals);
            }
            $this->dispatchCartDataEvent('bolt_boltpay_tax_applied_to_bolt_order', $quote, $cartSubmissionData);
            $calculatedTotal += $cartSubmissionData['tax_amount'];

            $shippingRegion = $shippingAddress->getRegion();
            if (
                empty($shippingRegion) &&
                !in_array($shippingAddress->getCountry(), $this->countriesRequiringRegion)
            ) {
                $shippingRegion = $shippingAddress->getCity();
            }

            if ($shippingAddress) {
                $cartShippingAddress = array(
                    'street_address1' => $shippingAddress->getStreet1(),
                    'street_address2' => $shippingAddress->getStreet2(),
                    'street_address3' => $shippingAddress->getStreet3(),
                    'street_address4' => $shippingAddress->getStreet4(),
                    'first_name'      => $shippingAddress->getFirstname(),
                    'last_name'       => $shippingAddress->getLastname(),
                    'locality'        => $shippingAddress->getCity(),
                    'region'          => $shippingRegion,
                    'postal_code'     => $shippingAddress->getPostcode() ?: '-',
                    'country_code'    => $shippingAddress->getCountry(),
                    'phone'           => $shippingAddress->getTelephone(),
                    'email'           => $shippingAddress->getEmail() ?: ($customerEmail ?: $billingAddress->getEmail()),
                );
                $this->dispatchCartDataEvent('bolt_boltpay_correct_shipping_address_for_bolt_order', $quote, $cartShippingAddress);

                if (@$totals['shipping']) {

                    $this->addShipping($totals, $cartSubmissionData, $shippingAddress, $cartShippingAddress);

                } else if (Mage::app()->getStore()->isAdmin()) {
                    $cartShippingAddress = Mage::getSingleton('admin/session')->getOrderShippingAddress();

                    if (empty($cartShippingAddress['email'])) {
                        $cartShippingAddress['email'] = $cartShippingAddress['email_address'] = $customerEmail;
                    }
                    $this->dispatchCartDataEvent('bolt_boltpay_correct_shipping_address_for_bolt_order', $quote, $cartShippingAddress);

                    /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
                    $shippingMethodBlock =  Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_shipping_method_form");
                    $shippingRate = $shippingMethodBlock->getActiveMethodRate();

                    if ($shippingRate) {
                        /* @var Mage_Sales_Model_Quote_Address_Total $shippingTotal */
                        $shippingTotal = $totalsBlock->getTotals()['shipping'];

                        $this->addShippingForAdmin( $shippingTotal, $cartSubmissionData, $shippingRate, $cartShippingAddress);
                    }else{
                        if($quote->isVirtual()){
                            $cartSubmissionData['shipments'] = array(array(
                                'shipping_address' => $cartShippingAddress,
                                'tax_amount'       => 0,
                                'service'          => Mage::helper('boltpay')->__('No Shipping Required'),
                                'reference'        => "noshipping",
                                'cost'             => 0
                            ));
                        }
                    }
                }
                $this->dispatchCartDataEvent('bolt_boltpay_shipping_applied_to_bolt_order', $quote, $cartSubmissionData);
                $calculatedTotal += isset($cartSubmissionData['shipments']) ? array_sum(array_column($cartSubmissionData['shipments'], 'cost')) : 0;
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

    protected function addDiscounts($totals, &$cartSubmissionData, $quote = null)
    {
        $cartSubmissionData['discounts'] = array();
        $totalDiscount = 0;

        foreach ($this->discountTypes as $discount) {
            if (@$totals[$discount] && $amount = $totals[$discount]->getValue()) {
                // Some extensions keep discount totals as positive values,
                // others as negative, which is the Magento default.
                // Using the absolute value.
                $discountAmount = (int) abs(round($amount * 100));

                if($discount=='amgiftcard'){
                    $giftCardsBalance = $quote->getAmGiftCardsAmount();
                    $discountAmount = abs(round(($giftCardsBalance) * 100));
                }elseif ($discount == 'amstcred') {
                    $customerId = $quote->getCustomer()->getId();
                    if ($customerId) $discountAmount = abs(round($this->getStoreCreditBalance($customerId) * 100));
                }

                $description = $totals[$discount]->getAddress()->getDiscountDescription();
                $description = Mage::helper('boltpay')->__('Discount (%s)', $description);

                $cartSubmissionData['discounts'][] = array(
                    'amount'      => $discountAmount,
                    'description' => $description,
                    'type'        => 'fixed_amount',
                );
                $totalDiscount += $discountAmount;
            }
        }

        return $totalDiscount;
    }

    /**
     * Dispatches events related to Bolt order cart data changes
     *
     * @param string                    $eventName          The name of the event to be dispatched
     * @param Mage_Sales_Model_Quote    $quote              Magento reference to the order
     * @param array                     $cartSubmissionData The data to be sent to Bolt
     */
    private function dispatchCartDataEvent($eventName, $quote, &$cartSubmissionData) {
        $cartDataWrapper = new Varien_Object();
        $cartDataWrapper->setCartData($cartSubmissionData);
        Mage::dispatchEvent(
            $eventName,
            array(
                'quote'=>$quote,
                'cartDataWrapper' => $cartDataWrapper
            )
        );
        $cartSubmissionData = $cartDataWrapper->getCartData();
    }
}
