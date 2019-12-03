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
 * Class Bolt_Boltpay_Model_BoltOrder
 *
 * The Magento Model class that provides order related utility methods
 *
 */
class Bolt_Boltpay_Model_BoltOrder extends Bolt_Boltpay_Model_Abstract
{
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL  = 'digital';
    protected $itemOptionKeys = array('attributes_info', 'options', 'additional_options', 'bundle_options');

    /**
     * @var int The amount of time in seconds that an order token is preserved in cache
     */
    public static $cached_token_expiration_time = 360;

    /**
     * @var array  Store discount types, internal and 3rd party.
     *             Can appear as keys in Quote::getTotals result array.
     */
    protected $discountTypes = array(
        'discount',
        'giftcardcredit',
        'giftcardcredit_after_tax',
        'giftvoucher',
        'giftvoucher_after_tax',
        'aw_storecredit',
        'credit', // magestore-customer-credit
        'amgiftcard', // https://amasty.com/magento-gift-card.html
        'amstcred', // https://amasty.com/magento-store-credit.html
        'awraf',    //https://ecommerce.aheadworks.com/magento-extensions/refer-a-friend.html#magento1
        'rewardpoints_after_tax', // Magestore_RewardPoints
    );

    /**
     * @var array  list of country codes for which we will require a region for validation to succeed.
     */
    protected $countriesRequiringRegion = array(
        'US', 'CA',
    );

    /**
     * Generates order data for sending to Bolt.
     *
     * @param Mage_Sales_Model_Quote        $quote      Magento quote instance
     * @param bool                          $isMultiPage  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     *
     * @return array            The order payload to be sent as to bolt in API call as a PHP array
     *
     * @throws Mage_Core_Model_Store_Exception if the store cannot be determined
     */
    public function buildOrder($quote, $isMultiPage)
    {
        $cart = $this->buildCart($quote, $isMultiPage);
        $boltOrder = ['cart' => $cart];
        return $this->boltHelper()->dispatchFilterEvent(
            "bolt_boltpay_filter_bolt_order",
            $boltOrder,
            ['quote' => $quote, 'isMultiPage' => $isMultiPage]
        );
    }

    /**
     * Generates cart submission data for sending to Bolt order cart field.
     *
     * @param Mage_Sales_Model_Quote        $quote      Magento quote instance
     * @param bool                          $isMultipage  Is checkout type Multi-Page Checkout, the default is true, set to false for One Page Checkout
     *
     * @return array            The cart data part of the order payload to be sent as to bolt in API call as a PHP array
     *
     * @throws Mage_Core_Model_Store_Exception if the store cannot be determined
     */
    public function buildCart($quote, $isMultipage)
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

        $this->boltHelper()->collectTotals($quote)->save();

        $totals = $quote->getTotals();
        ///////////////////////////////////////////////////////////////////////////////////

        ///////////////////////////////////////////////////////////
        // Generate base cart data, quote, order and items related.
        ///////////////////////////////////////////////////////////
        $cartSubmissionData = array(
            'order_reference' => $quote->getParentQuoteId(),
            'display_id'      => $quote->getReservedOrderId().'|'.$quote->getId(),
            'items'           => array_map(
                function ($item) use ($quote, &$calculatedTotal) {
                    /** @var Mage_Sales_Model_Quote_Item $item */
                    $imageUrl = $this->boltHelper()->getItemImageUrl($item);
                    $product   = Mage::getModel('catalog/product')->load($item->getProductId());
                    $type = $product->getTypeId() == 'virtual' ? self::ITEM_TYPE_DIGITAL : self::ITEM_TYPE_PHYSICAL;
                    $calculatedTotal += round($item->getPrice() * 100 * $item->getQty());
                    return array(
                        'reference'    => $item->getId(),
                        'image_url'    => $imageUrl,
                        'name'         => $item->getName(),
                        'sku'          => $item->getSku(),
                        'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                        'total_amount' => round($item->getCalculationPrice() * 100) * $item->getQty(),
                        'unit_price'   => round($item->getCalculationPrice() * 100),
                        'quantity'     => $item->getQty(),
                        'type'         => $type,
                        'properties'   => $this->getItemProperties($item)
                    );
                }, $quote->getAllVisibleItems()
            ),
            'currency' => $quote->getQuoteCurrencyCode(),
        );
        ///////////////////////////////////////////////////////////

        /////////////////////////////////////////////////////////////////////////
        // Check for discounts and include them in the submission data if found.
        /////////////////////////////////////////////////////////////////////////
        $this->addDiscounts($totals, $cartSubmissionData, $quote);
        $this->dispatchCartDataEvent('bolt_boltpay_discounts_applied_to_bolt_order', $quote, $cartSubmissionData);
        $totalDiscount = isset($cartSubmissionData['discounts']) ? array_sum(array_column($cartSubmissionData['discounts'], 'amount')) : 0;

        $calculatedTotal -= $totalDiscount;
        /////////////////////////////////////////////////////////////////////////

        if ($isMultipage) {
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
            // For one page checkout/admin type include tax and shipment / address data in submission.
            ////////////////////////////////////////////////////////////////////////////////////
            if ($this->isAdmin()) {
                /* @var Mage_Adminhtml_Block_Sales_Order_Create_Totals $totalsBlock */
                $totalsBlock = $this->createLayoutBlock("adminhtml/sales_order_create_totals_shipping");

                /* @var Mage_Sales_Model_Quote_Address_Total $grandTotal */
                $grandTotal = $totalsBlock->getTotals()['grand_total'];
                $cartSubmissionData['total_amount'] = (int) round($grandTotal->getValue() * 100);

                /* @var Mage_Sales_Model_Quote_Address_Total $taxTotal */
                $taxTotal = isset($totalsBlock->getTotals()['tax']) ? $totalsBlock->getTotals()['tax'] : 0;
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

                } else if ($this->isAdmin()) {
                    $cartShippingAddress = Mage::getSingleton('admin/session')->getOrderShippingAddress();

                    if (empty($cartShippingAddress['email'])) {
                        $cartShippingAddress['email'] = $cartShippingAddress['email_address'] = $customerEmail;
                    }
                    $this->dispatchCartDataEvent('bolt_boltpay_correct_shipping_address_for_bolt_order', $quote, $cartShippingAddress);

                    /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
                    $shippingMethodBlock = $this->createLayoutBlock("adminhtml/sales_order_create_shipping_method_form");
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
                                'service'          => $this->boltHelper()->__('No Shipping Required'),
                                'reference'        => "noshipping",
                                'cost'             => 0
                            ));
                        }
                    }
                }

                $calculatedTotal += isset($cartSubmissionData['shipments']) ? array_sum(array_column($cartSubmissionData['shipments'], 'cost')) : 0;
            }

            # It is possible that no shipments were added which could be used as indicative of an In-Store Pickup/No Shipping Required
            $this->dispatchCartDataEvent('bolt_boltpay_shipping_applied_to_bolt_order', $quote, $cartSubmissionData);
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
     * Adds the calculated discounts to the Bolt order
     *
     * @param array                  $totals             totals array from collectTotals
     * @param array                  $cartSubmissionData data to be sent to Bolt
     * @param Mage_Sales_Model_Quote $quote              the quote associated with the order
     *
     * @return int    The total discount in cents as a positive number
     */
    protected function addDiscounts( $totals, &$cartSubmissionData, $quote = null ) {
        $cartSubmissionData['discounts'] = array();
        $totalDiscount = 0;

        foreach ($this->discountTypes as $discount) {

            $amount = (@$totals[$discount])
                ? $this->boltHelper()->doFilterEvent(
                    'bolt_boltpay_filter_discount_amount',
                    $totals[$discount]->getValue(),
                    array('quote' => $quote, 'discount'=>$discount)
                )
                : 0;

            if (!$amount) {continue;}

            // Some extensions keep discount totals as positive values,
            // others as negative, which is the Magento default.
            // Using the absolute value.
            $discountAmount = (int) abs(round($amount * 100));
            $data = array(
                'amount'      => $discountAmount,
                'description' => $totals[$discount]->getTitle(),
                'type'        => 'fixed_amount',
            );

            if ($discount === 'discount' && $quote->getCouponCode()) {
                /////////////////////////////////////////////////////////////
                /// We want to get the apply the coupon code as a reference.
                /// Potentially, we will have several 'discount' entries
                /// but only one coupon code, so we must find the right entry
                /// to map to.  Magento stores the records rule description or
                /// the coupon code when the rule description is empty in
                /// the totals object wrapped in the string "Discount()", and
                /// keeps no separate reference to the rule or coupon code.
                /// Here, we use the coupon code to look up the rule description
                /// and compare it and the coupon code to the total object's title.
                /////////////////////////////////////////////////////////////
                $coupon = Mage::getModel('salesrule/coupon')->load($quote->getCouponCode(), 'code');
                $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());

                if (
                    in_array(
                        $totals[$discount]->getTitle(),
                        [
                            Mage::helper('sales')->__('Discount (%s)', (string)$rule->getName()),
                            Mage::helper('sales')->__('Discount (%s)', (string)$quote->getCouponCode())
                        ]
                    )
                ) {
                    $data['reference'] = $quote->getCouponCode();
                }
            }

            $cartSubmissionData['discounts'][] = $data;
            $totalDiscount += $discountAmount;
        }

        return $totalDiscount;
    }

    /**
     * Gets the calculated tax for the order
     *
     * @param array                  $totals             totals array from collectTotals
     *
     * @return int    The total tax in cents
     */
    protected function getTax($totals) {
        return (@$totals['tax']) ? (int) round($totals['tax']->getValue() * 100) : 0;
    }

    /**
     * Adds the calculated shipping to the Bolt order
     *
     * @param array                                 $totals             totals array from collectTotals
     * @param array                                 $cartSubmissionData data to be sent to Bolt
     * @param Mage_Customer_Model_Address_Abstract  $shippingAddress    order shipping address
     * @param array                                 $boltFormatAddress  shipping address in Bolt format
     * @param Mage_Sales_Model_Quote                $quote              the quote associated with the order
     *
     * @return int    The total shipping in cents
     */
    protected function addShipping( $totals, &$cartSubmissionData, $shippingAddress, $boltFormatAddress, $quote = null ) {
        $totalShipping = (int) round($totals['shipping']->getValue() * 100);
        $cartSubmissionData['shipments'] = array(array(
            'shipping_address' => $boltFormatAddress,
            'tax_amount'       => (int) round($shippingAddress->getShippingTaxAmount() * 100),
            'service'          => $shippingAddress->getShippingDescription(),
            'carrier'          => $shippingAddress->getShippingMethod(),
            'reference'        => $shippingAddress->getShippingMethod(),
            'cost'             => $totalShipping,
        ));

        return $totalShipping;
    }

    /**
     * Adds the calculated shipping to the Bolt order from the admin context
     *
     * @param Mage_Sales_Model_Quote_Address_Total  $shippingTotal      calculated shipping totals
     * @param array                                 $cartSubmissionData data to be sent to Bolt
     * @param Mage_Sales_Model_Quote_Address_Rate   $shippingRate       shipping rate meta data
     * @param array                                 $boltFormatAddress  shipping address in Bolt format
     * @param Mage_Sales_Model_Quote                $quote              the quote associated with the order
     *
     * @return int    The total shipping in cents
     */
    protected function addShippingForAdmin( $shippingTotal, &$cartSubmissionData, $shippingRate, $boltFormatAddress, $quote ) {
        $totalShipping = $shippingTotal ? (int) round($shippingTotal->getValue() * 100) : 0;
        $cartSubmissionData['shipments'] = array(array(
            'shipping_address' => $boltFormatAddress,
            'tax_amount'       => 0,
            'service'          => $shippingRate->getMethodTitle(),
            'carrier'          => $shippingRate->getCarrierTitle(),
            'cost'             => $totalShipping,
        ));
        return $totalShipping;
    }

    /**
     * Gets the calculated tax for the Bolt order in the admin context
     *
     * @param Mage_Sales_Model_Quote_Address_Total  $taxTotal           calculated tax totals
     *
     * @return int    The total tax in cents
     */
    protected function getTaxForAdmin( $taxTotal ) {
        return ($taxTotal ? (int) round($taxTotal->getValue() * 100) : 0);
    }

    /**
     * Utility method that attempts to correct totals if the projected total that was calculated from
     * all items and the given discount, does not match the $magento calculated total.  The totals may vary
     * do to an error in the internal Magento code
     *
     * @param int $projectedTotal            total calculated from items, discounts, taxes and shipping
     * @param int $magentoDerivedCartData    totals returned by magento and formatted for Bolt
     *
     * @return array  the corrected Bolt formatted cart data.
     */
    protected function getCorrectedTotal($projectedTotal, $magentoDerivedCartData)
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
     * Checks if billing address of a quote has all the expected fields.
     * If not, and therefore invalid, the address is replaced by using the
     * provided shipping address.
     *
     * @param Mage_Sales_Model_Quote_Address $billingAddress   The billing address
     *                                                         to be checked
     * @param Mage_Sales_Model_Quote_Address $fallbackAddress  The address to fallback
     *                                                         to in case of invalid billing
     *                                                         address
     *
     *
     * @return bool  true if a correction is made, otherwise false
     *
     * TODO: evaluate necessity of this code by auditing Bugsnag for corrections made.
     * If it has not been triggered by April 2019, this code may be safely removed.
     *
     */
    public function correctBillingAddress(&$billingAddress, $fallbackAddress = null, $notifyBugsnag = true )
    {
        if (!$fallbackAddress) {
            return false;
        }

        $quote = $billingAddress->getQuote();

        $wasCorrected = false;

        if (
            !trim($billingAddress->getStreetFull()) ||
            !$billingAddress->getCity() ||
            !$billingAddress->getCountry()
        )
        {
            if ($notifyBugsnag) {
                $this->boltHelper()->notifyException(
                    new Exception("Missing critical billing data. "
                        ." Street: ". $billingAddress->getStreetFull()
                        ." City: ". $billingAddress->getCity()
                        ." Country: ". $billingAddress->getCountry()
                    ),
                    array(),
                    "info"
                );
            }

            $billingAddress
                ->setCity($fallbackAddress->getCity())
                ->setRegion($fallbackAddress->getRegion())
                ->setRegionId($fallbackAddress->getRegionId())
                ->setPostcode($fallbackAddress->getPostcode())
                ->setCountryId($fallbackAddress->getCountryId())
                ->setStreet($fallbackAddress->getStreet())
                ->save();

            $wasCorrected = true;
        }

        if (!trim($billingAddress->getName())) {

            if ($notifyBugsnag) {
                $this->boltHelper()->notifyException(
                    new Exception("Missing billing name."),
                    array(),
                    "info"
                );
            }

            $billingAddress
                ->setPrefix($fallbackAddress->getPrefix())
                ->setFirstname($fallbackAddress->getFirstname())
                ->setMiddlename($fallbackAddress->getMiddlename())
                ->setLastname($fallbackAddress->getLastname())
                ->setSuffix($fallbackAddress->getSuffix())
                ->save();

            $wasCorrected = true;
        }

        if (!trim($billingAddress->getTelephone())) {
            if ($notifyBugsnag) {
                $this->boltHelper()->notifyException(
                    new Exception("Missing billing telephone."),
                    array(),
                    "info"
                );
            }
            $billingAddress->setTelephone($fallbackAddress->getTelephone());
            $wasCorrected = true;
        }

        if ( $wasCorrected && !trim($billingAddress->getCompany())) {
            $billingAddress->setCompany($fallbackAddress->getCompany());
        }

        if (!trim($billingAddress->getEmail())) {
            $billingAddress->setEmail($fallbackAddress->getEmail());
            $wasCorrected = true;
        }

        if ($wasCorrected) {
            $billingAddress->save();
            $quote->save();
        }

        return $wasCorrected;
    }

    /**
     * Accessor to discount types supported by Bolt
     *
     * @return array    collection of strings used by Magento to specify type of discount
     */
    public function getDiscountTypes() {
        return $this->discountTypes;
    }

    /**
     * Get's the customer's email from the given quote, if provided.  Otherwise, attempts
     * to retrieve it via contextual hints from the session
     *
     * @param Mage_Sales_Model_Quote|null $quote    A quote from which to retrieve the customer's email
     *
     * @return string    The customer's email address, if found
     */
    protected function getCustomerEmail($quote = null) {
        $customerEmail = $quote ? $quote->getCustomerEmail() : "";
        if (!$customerEmail && $this->isAdmin()) {
            //////////////////////////////////////////////////
            // In the admin, we first check form session data.
            // For order edits or reorders, the guest customer's email
            // will be stored in the order
            //////////////////////////////////////////////////
            $shippingAddressData = Mage::getSingleton('admin/session')->getOrderShippingAddress() ?: array( 'email_address' => '', 'email' => '' );
            $customerEmail = $shippingAddressData['email_address'] ?: $shippingAddressData['email'];

            if (empty($customerEmail)) {
                /** @var Mage_Adminhtml_Model_Session_Quote $session */
                $session = Mage::getSingleton('adminhtml/session_quote');

                $orderId = $session->getOrderId() ?: $session->getReordered();

                if ($orderId) {
                    /** @var Mage_Sales_Model_Order $order */
                    $order = Mage::getModel('sales/order')->load($orderId);
                    $customerEmail = $order->getCustomerEmail();
                }
            }
        }

        return $customerEmail;
    }

    /**
     * Calculates and returns the key for storing a Bolt order token
     *
     * @param Mage_Sales_Model_Quote|array $quote         The quote whose key that will be
     *                                                    generated or the bolt cart array
     *                                                    repesentation of the quote.
     *
     * @param string                       $checkoutType  'multi-page' | 'one-page' | 'admin'
     *
     * @return string   The calculated key for this quote's cart.
     *                  Format is {quote id}_{md5 hash of cart content}
     */
    private function calculateCartCacheKey( $quote, $checkoutType )
    {
        $boltCartArray = is_array($quote) ? $quote : $this->buildOrder($quote, $checkoutType === 'multi-page');
        if ($boltCartArray['cart']) {
            unset($boltCartArray['cart']['display_id']);
            unset($boltCartArray['cart']['order_reference']);
        }
        return $quote->getId() . '_' . md5(json_encode($boltCartArray));
    }

    /**
     * Get cached copy of the Bolt order token if it exist and has not expired
     *
     * @param Mage_Sales_Model_Quote $quote         The quote for which the cached token is sought
     * @param string                 $checkoutType  'multi-page' | 'one-page' | 'admin'
     *
     * @return string|null  If it exist, the cached order token
     */
    public function getCachedCartData($quote, $checkoutType)
    {

        $cachedCartDataJS = Mage::getSingleton('core/session')->getCachedCartData();

        if (
            $cachedCartDataJS
            && (($cachedCartDataJS['creation_time'] + self::$cached_token_expiration_time) > time())
            && ($cachedCartDataJS['key'] === $this->calculateCartCacheKey($quote, $checkoutType))
        ) {
            return unserialize($cachedCartDataJS["cart_data"]);
        }

        Mage::getSingleton('core/session')->unsCachedCartData();
        return null;
    }

    /**
     * Caches cart data that is sent to the front end to be used for duplicate request
     *
     * @param array $cartData  The cart data containing the bolt order token.  This is the
     *                         php representation of the 'cart' parameter passed to the
     *                         javascript Bolt.configure method
     */
    public function cacheCartData($cartData, $quote, $checkoutType)
    {
        Mage::getSingleton('core/session')->setCachedCartData(
            array(
                'creation_time' => time(),
                'key' => $this->calculateCartCacheKey($quote, $checkoutType),
                'cart_data' => serialize($cartData)
            )
        );
    }

    /**
     * Get an order token for a Bolt order either by creating it or making a Promise to create it
     *
     * @param Mage_Sales_Model_Quote|null $quote            Magento quote object which represents
     *                                                      order/cart data.
     * @param string                      $checkoutType     'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     *
     * @return object|string  json based PHP object or a javascript Promise string for initializing BoltCheckout
     */
    public function getBoltOrderToken($quote, $checkoutType)
    {
        $isMultiPage = $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;

        $items = $quote->getAllVisibleItems();

        $hasAdminShipping = false;
        if ($this->isAdmin()) {
            /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
            $shippingMethodBlock = $this->createLayoutBlock('adminhtml/sales_order_create_shipping_method_form');
            $hasAdminShipping = $shippingMethodBlock->getActiveMethodRate();
        }

        if (empty($items)) {
            return json_decode('{"token" : "", "error": "'.$this->boltHelper()->__('Your shopping cart is empty. Please add products to the cart.').'"}');
        } else if (
            !$isMultiPage
            && !$quote->getShippingAddress()->getShippingMethod()
            && !$hasAdminShipping
        ) {
            if (!$quote->isVirtual()){
                return json_decode('{"token" : "", "error": "'.$this->boltHelper()->__('A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.').'"}');
            }

            if (!$this->validateVirtualQuote($quote)){
                return json_decode('{"token" : "", "error": "'.$this->boltHelper()->__('Billing address is required.').'"}');
            }
        }

        // Generates order data for sending to Bolt create order API.
        $orderRequest = $this->buildOrder($quote, $isMultiPage);

        // Calls Bolt create order API
        return $this->boltHelper()->transmit('orders', $orderRequest);
    }

    /**
     * Get Promise to create an order token
     *
     * @param string  $checkoutType  'multi-page' | 'one-page' | 'admin' | 'firecheckout'
     *
     * @return string javascript Promise string used for initializing BoltCheckout
     */
    public function getBoltOrderTokenPromise($checkoutType) {

        if ( $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT ) {
            $checkoutTokenUrl = $this->boltHelper()->getMagentoUrl('boltpay/order/firecheckoutcreate');
            $parameters = 'checkout.getFormData ? checkout.getFormData() : Form.serialize(checkout.form, true)';
        } else if ( $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN ) {
            $checkoutTokenUrl = $this->boltHelper()->getMagentoUrl("adminhtml/sales_order_create/create/checkoutType/$checkoutType", array(), true);
            $parameters = "''";
        } else {
            $checkoutTokenUrl = $this->boltHelper()->getMagentoUrl("boltpay/order/create/checkoutType/$checkoutType");
            $parameters = "''";
        }

        return <<<PROMISE
                    new Promise( 
                        function (resolve, reject) {
                            new Ajax.Request('$checkoutTokenUrl', {
                                method:'post',
                                parameters: $parameters,
                                onSuccess: function(response) {
                                    if(response.responseJSON.error) {                                                        
                                        reject(response.responseJSON.error_messages);
                                        
                                        // BoltCheckout is currently not doing anything reasonable to alert the user of a problem, so we will do something as a backup
                                        alert(response.responseJSON.error_messages);
                                        location.reload();
                                    } else {                                     
                                        resolve(response.responseJSON.cart_data);
                                    }                   
                                },
                                 onFailure: function(error) { reject(error); }
                            });                            
                        }
                    )
PROMISE;

    }

    /**
     * Creates a clone of a quote including items, addresses, customer details,
     * and shipping and tax options when
     *
     * @param Mage_Sales_Model_Quote $sourceQuote The quote to be cloned
     *
     * @param string                 $checkoutType
     *
     * @return Mage_Sales_Model_Quote  The cloned copy of the source quote
     * @throws \Exception
     */
    public function cloneQuote(
        Mage_Sales_Model_Quote $sourceQuote,
        $checkoutType = Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE
    )
    {
        /* @var Mage_Sales_Model_Quote $clonedQuote */
        $clonedQuote = Mage::getSingleton('sales/quote');

        try {
            // overridden quote classes may throw exceptions in post merge events.  We report
            // these in bugsnag, but these are non-fatal exceptions, so, we continue processing
            $clonedQuote->merge($sourceQuote);
        } catch (Exception $e) {
            $this->boltHelper()->notifyException($e);
        }

        if ($checkoutType != Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE ) {
            // For the checkout page we want to set the
            // billing and shipping, and shipping method at this time.
            // For multi-page, we add the addresses during the shipping and tax hook
            // and the chosen shipping method at order save time.

            $shippingAddress = $sourceQuote->getShippingAddress();
            $billingAddress = $sourceQuote->getBillingAddress();

            if ($checkoutType == Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN){
                $shippingData = $shippingAddress->getData();
                unset($shippingData['address_id']);
                $shippingAddress = Mage::getSingleton('sales/quote_address')->setData($shippingData);
            }

            $clonedQuote
                ->setBillingAddress($billingAddress)
                ->setShippingAddress($shippingAddress)
                ->getShippingAddress()
                ->setShippingMethod($sourceQuote->getShippingAddress()->getShippingMethod())
                ->save();
        }

        //////////////////////////////////////////////////////////////////////////////////////////////////
        // Attempting to reset some of the values already set by merge affects the totals passed to
        // Bolt in such a way that the grand total becomes 0.  Since we do not need to reset these values
        // we ignore them all.
        //////////////////////////////////////////////////////////////////////////////////////////////////
        $fieldsSetByMerge = array(
            'coupon_code',
            'subtotal',
            'base_subtotal',
            'subtotal_with_discount',
            'base_subtotal_with_discount',
            'grand_total',
            'base_grand_total',
            'auctaneapi_discounts',
            'applied_rule_ids',
            'items_count',
            'items_qty',
            'virtual_items_qty',
            'trigger_recollect',
            'can_apply_msrp',
            'totals_collected_flag',
            'global_currency_code',
            'base_currency_code',
            'store_currency_code',
            'quote_currency_code',
            'store_to_base_rate',
            'store_to_quote_rate',
            'base_to_global_rate',
            'base_to_quote_rate',
            'is_changed',
            'created_at',
            'updated_at',
            'entity_id'
        );

        // Add all previously saved data that may have been added by other plugins
        foreach ($sourceQuote->getData() as $key => $value) {
            if (!in_array($key, $fieldsSetByMerge)) {
                $clonedQuote->setData($key, $value);
            }
        }

        /////////////////////////////////////////////////////////////////
        // Generate new increment order id and associate it with current quote, if not already assigned
        // Save the reserved order ID to the session to check order existence at frontend order save time
        /////////////////////////////////////////////////////////////////
        $reservedOrderId = $sourceQuote->reserveOrderId()->save()->getReservedOrderId();
        Mage::getSingleton('core/session')->setReservedOrderId($reservedOrderId);

        $clonedQuote
            ->setIsActive(false)
            ->setCustomer($sourceQuote->getCustomer())
            ->setCustomerGroupId($sourceQuote->getCustomerGroupId())
            ->setCustomerIsGuest((($sourceQuote->getCustomerId()) ? false : true))
            ->setReservedOrderId($reservedOrderId)
            ->setStoreId($sourceQuote->getStoreId())
            ->setParentQuoteId($sourceQuote->getId())
            ->save();

        if ($checkoutType == Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN){
            $clonedQuote->getShippingAddress()->collectTotals();
            $clonedQuote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()->save();
        }

        return $this->boltHelper()->dispatchFilterEvent(
            "bolt_boltpay_filter_cloned_quote",
            $clonedQuote,
            ['sourceQuote' => $sourceQuote, 'checkoutType' => $checkoutType]
        );
    }

    /**
     * Item properties are the order options selected by the customer e.g. color and size
     * @param Mage_Sales_Model_Quote_Item $item
     * @return array
     */
    protected function getItemProperties(Mage_Sales_Model_Quote_Item $item)
    {
        $properties = array();
        foreach($this->getProductOptions($item) as $option) {
            /** @var Mage_Sales_Model_Quote_Item_Option $option */
            $optionValue = $this->getOptionValue($option);

            if ($optionValue) {
                $properties[] = array('name' => $option['label'], 'value' => $optionValue);
            }
        }

        return $properties;
    }

    /**
     * @param Mage_Sales_Model_Quote_Item $item
     * @return array
     */
    protected function getProductOptions(Mage_Sales_Model_Quote_Item $item)
    {
        $options = $item->getProductOrderOptions();
        if (!$options) {
            $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
        }

        $productOptions = array();
        if ($options) {
            foreach ($this->itemOptionKeys as $itemOptionKey) {
                if (isset($options[$itemOptionKey])) {
                    $productOptions = array_merge($productOptions, $options[$itemOptionKey]);
                }
            }
        }
        return $productOptions;
    }

    /**
     * @param $option
     * @return bool|string
     */
    protected function getOptionValue($option)
    {
        if (is_array(@$option['value'])) {
            return $this->getBundleProductOptionValue($option);
        }
        return @$option['value'];
    }

    /**
     * @param $option
     * @return string
     */
    protected function getBundleProductOptionValue($option)
    {
        $optionValues = array();
        foreach ($option['value'] as $value) {
            if ((@$value['qty']) && (@$value['title']) && (@$value['price'])) {
                $optionValues[] = @$value['qty'] . ' x ' . @$value['title'] . " " . Mage::helper('core')->currency(@$value['price'], true, false);
            }
        }
        return join(', ', $optionValues);
    }

    /**
     * Validate virtual quote
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    protected function validateVirtualQuote($quote)
    {
        if (!$quote->isVirtual()){
            return true;
        }

        $address = $quote->getBillingAddress();

        if (
            !$address->getLastname() ||
            !$address->getStreet1() ||
            !$address->getCity() ||
            !$address->getPostcode() ||
            !$address->getTelephone() ||
            !$address->getCountryId()
        ){
            return false;
        }

        return true;
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
                'cart_data_wrapper' => $cartDataWrapper
            )
        );
        $cartSubmissionData = $cartDataWrapper->getCartData();
    }
}
