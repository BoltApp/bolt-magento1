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
 * Class Bolt_Boltpay_Model_BoltOrder
 *
 * The Magento Model class that provides order related utility methods
 *
 */
class Bolt_Boltpay_Model_BoltOrder extends Mage_Core_Model_Abstract
{
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL  = 'digital';

    /**
     * @var int The amount of time in seconds that an order token is preserved in cache
     */
    public static $cached_token_expiration_time = 60 * 60;

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
    );

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

            $billingAddress  = $quote->getBillingAddress();
            $shippingAddress = $quote->getShippingAddress();

            $customerEmail = $this->getCustomerEmail($quote);

            $billingRegion = $billingAddress->getRegion();
            if (empty($shippingRegion) && !in_array($billingAddress->getCountry(), array('US', 'CA'))) {
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
            ///////////////////////////////////////////

            ////////////////////////////////////////////////////////////////////////////////////
            // For one page checkout type include tax and shipment / address data in submission.
            ////////////////////////////////////////////////////////////////////////////////////
            $cartSubmissionData['total_amount'] = round($totals["grand_total"]->getValue() * 100);

            if (@$totals['tax']) {
                $cartSubmissionData['tax_amount'] = round($totals['tax']->getValue() * 100);
                $calculatedTotal += $cartSubmissionData['tax_amount'];
            }

            $shippingRegion = $shippingAddress->getRegion();
            if (empty($shippingRegion) && !in_array($shippingAddress->getCountry(), array('US', 'CA'))) {
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

                if (@$totals['shipping']) {

                    $cartSubmissionData['shipments'] = array(array(
                        'shipping_address' => $cartShippingAddress,
                        'tax_amount'       => (int) round($shippingAddress->getShippingTaxAmount() * 100),
                        'service'          => $shippingAddress->getShippingDescription(),
                        'carrier'          => $shippingAddress->getShippingMethod(),
                        'reference'        => $shippingAddress->getShippingMethod(),
                        'cost'             => (int) round($totals['shipping']->getValue() * 100),
                    ));
                    $calculatedTotal += round($totals['shipping']->getValue() * 100);

                } else if (Mage::app()->getStore()->isAdmin()) {
                    $cartShippingAddress = Mage::getSingleton('admin/session')->getOrderShippingAddress();

                    if (empty($cartShippingAddress['email'])) {
                        $cartShippingAddress['email'] = $cartShippingAddress['email_address'] = $customerEmail;
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
                    }else{
                        if($quote->isVirtual()){
                            $cartSubmissionData['shipments'] = array(array(
                                'shipping_address' => $cartShippingAddress,
                                'tax_amount'       => (int) round($shippingAddress->getShippingTaxAmount() * 100),
                                'service'          => Mage::helper('boltpay')->__('No Shipping Required'),
                                'reference'        => "noshipping",
                                'cost'             => 0
                            ));
                        }
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
     * Get's the customer's email from the given quote, if provided.  Otherwise, attempts
     * to retrieve it via contextual hints from the session
     *
     * @param Mage_Sales_Model_Quote|null $quote    A quote from which to retrieve the customer's email
     *
     * @return string    The customer's email address, if found
     */
    protected function getCustomerEmail($quote = null) {
        $customerEmail = $quote ? $quote->getCustomerEmail() : "";
        if (!$customerEmail && Mage::app()->getStore()->isAdmin()) {
            //////////////////////////////////////////////////
            // In the admin, guest customer's email will be stored in the order for
            // order edits and reorders
            //////////////////////////////////////////////////
            /** @var Mage_Adminhtml_Model_Session_Quote $session */
            $session = Mage::getSingleton('adminhtml/session_quote');
            $orderId = $session->getOrderId() ?: $session->getReordered();

            if ($orderId) {
                /** @var Mage_Sales_Model_Order $order */
                $order = Mage::getModel('sales/order')->load($orderId);
                $customerEmail = $order->getCustomerEmail();
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
    private function calculateCartCacheKey( $quote, $checkoutType ) {
        $boltCartArray = is_array($quote) ? $quote : $this->buildOrder($quote, $checkoutType === 'multi-page');
        if ($boltCartArray['cart']) {
            unset($boltCartArray['cart']['display_id']);
            unset($boltCartArray['cart']['order_reference']);
        }
        return $quote->getId().'_'.md5(json_encode($boltCartArray));
    }


    /**
     * Get cached copy of the Bolt order token if it exist and has not expired
     *
     * @param Mage_Sales_Model_Quote $quote         The quote for which the cached token is sought
     * @param string                 $checkoutType  'multi-page' | 'one-page' | 'admin'
     *
     * @return string|null  If it exist, the cached order token
     */
    public function getCachedCartData($quote, $checkoutType) {

        $cachedCartDataJS = Mage::getSingleton('core/session')->getCachedCartData();

        if (
            $cachedCartDataJS
            && (($cachedCartDataJS['creation_time'] + self::$cached_token_expiration_time) > time())
            && ($cachedCartDataJS['key'] === $this->calculateCartCacheKey($quote, $checkoutType))
        )
        {
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
    public function cacheCartData($cartData, $quote, $checkoutType) {
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

        /** @var Bolt_Boltpay_Helper_Api $boltHelper */
        $boltHelper = Mage::helper('boltpay/api');
        $isMultiPage = $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_MULTI_PAGE;

        $items = $quote->getAllVisibleItems();

        $hasAdminShipping = false;
        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form $shippingMethodBlock */
            $shippingMethodBlock = Mage::app()->getLayout()->createBlock("adminhtml/sales_order_create_shipping_method_form");
            $hasAdminShipping = $shippingMethodBlock->getActiveMethodRate();
        }

        if (empty($items)) {

            return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('Your shopping cart is empty. Please add products to the cart.').'"}');

        } else if (
            !$isMultiPage
            && !$quote->getShippingAddress()->getShippingMethod()
            && !$hasAdminShipping
        ) {

            if (!$quote->isVirtual()){
                return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('A valid shipping method must be selected.  Please check your address data and that you have selected a shipping method, then, refresh to try again.').'"}');
            }

            if (!$this->validateVirtualQuote($quote)){
                return json_decode('{"token" : "", "error": "'.Mage::helper('boltpay')->__('Billing address is required.').'"}');
            }
        }

        // Generates order data for sending to Bolt create order API.
        $orderRequest = Mage::getModel('boltpay/boltOrder')->buildOrder($quote, $isMultiPage);

        // Calls Bolt create order API
        return $boltHelper->transmit('orders', $orderRequest);
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
            $checkoutTokenUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/firecheckoutcreate');
            $parameters = 'checkout.getFormData ? checkout.getFormData() : Form.serialize(checkout.form, true)';
        } else if ( $checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN ) {
            $checkoutTokenUrl = Mage::helper('boltpay/url')->getMagentoUrl("adminhtml/sales_order_create/create/checkoutType/$checkoutType", array(), true);
            $parameters = "''";
        } else {
            $checkoutTokenUrl = Mage::helper('boltpay/url')->getMagentoUrl("boltpay/order/create/checkoutType/$checkoutType");
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
}
