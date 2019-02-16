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
 * Class Bolt_Boltpay_Helper_Data
 *
 * Base Magento Bolt Helper class
 *
 */
class Bolt_Boltpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var bool    a flag set to true if the class is instantiated from web hook call, otherwise false
     */
    static $fromHooks = false;

    /**
     * Determines if the Bolt payment method can be used in the system
     *
     * @param Mage_Sales_Model_Quote $quote         Magento quote object
     * @param bool                   $checkCountry Set to true if the billing country should be checked, otherwise false
     *
     * @return bool     true if Bolt can be used, false otherwise
     *
     * TODO: consider store base currency and possibly add conversion logic
     * @throws Mage_Core_Model_Store_Exception
     */
    public function canUseBolt($quote, $checkCountry = true)
    {
        /**
         * If called from hooks always return true
         */
        if (self::$fromHooks) return true;

        return $this->isBoltPayActive()
            && (!$checkCountry || ($checkCountry && $this->canUseForCountry($quote->getBillingAddress()->getCountry())))
            && (Mage::app()->getStore()->getCurrentCurrencyCode() == 'USD')
            && (Mage::app()->getStore()->getBaseCurrencyCode() == 'USD');
    }

    /**
     * @return bool
     */
    public function isBoltPayActive()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/active');
    }

    /**
     * Check if the Bolt payment method can be used for specific country
     *
     * @param string $country   the country to be compared in check for allowing Bolt as a payment method
     * @return bool   true if Bolt can be used, otherwise false
     */
    public function canUseForCountry($country)
    {

        if(!$this->isBoltPayActive()) {
            return false;
        }

        if (Mage::getStoreConfig('payment/boltpay/skip_payment') == 1) {
            return true;
        }

        if (Mage::getStoreConfig('payment/boltpay/allowspecific') == 1) {
            $availableCountries =
                explode(',', Mage::getStoreConfig('payment/boltpay/specificcountry'));
            if (!in_array($country, $availableCountries)){
                return false;
            }
        }

        return true;
    }

    /**
     * Resets rounding deltas before calling collect totals which fixes bug in collectTotals that causes rounding errors
     * when a percentage discount is applied to a quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param bool                   $clearTotalsCollectedFlag
     * @return Mage_Sales_Model_Quote
     */
    public function collectTotals($quote, $clearTotalsCollectedFlag = false)
    {
        Mage::getSingleton('salesrule/validator')->resetRoundingDeltas();

        if($clearTotalsCollectedFlag) {
            $quote->setTotalsCollectedFlag(false);
        }

        $quote->collectTotals();

        return $quote;
    }

    /**
     * Get config value
     *
     * @return bool
     */
    public function shouldAddButtonEverywhere()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/add_button_everywhere');
    }

    /**
     * Get publishable key used in cart page.
     *
     * @return string
     */
    public function getPublishableKeyMultiPage()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_multipage');
        return $key;
    }

    /**
     * Get publishable key used in checkout page.
     *
     * @return string
     */
    public function getPublishableKeyOnePage()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_onepage');
        return $key;
    }

    /**
     *  Returns the primary color customized for Bolt
     *
     * @return string   If set, a 6 or 8 digit hexadecimal color value preceded by a '#' character, otherwise an empty string
     */
    public function getBoltPrimaryColor()
    {
        return $this->getExtraConfig('boltPrimaryColor');
    }

    /**
     * Get publishable key used in magento admin.
     *
     * @return string
     */
    public function getPublishableKeyBackOffice()
    {
        $key = Mage::getStoreConfig('payment/boltpay/publishable_key_admin');
        return $key;
    }

    /**
     *
     * @return string
     */
    public function getAdditionalButtonClasses()
    {
        return Mage::getStoreConfig('payment/boltpay/button_classes');
    }

    /**
     * @return bool
     */
    public function isEnabledProductPageCheckout()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/enable_product_page_checkout');
    }

    /**
     * @return string
     */
    public function getProductPageCheckoutSelector()
    {
        return Mage::getStoreConfig('payment/boltpay/product_page_checkout_selector');
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
            Mage::helper('boltpay/bugsnag')->notifyException($e);
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
            $clonedQuote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()->save();
        }

        return $clonedQuote;
    }

    /**
     * Gets the connect.js url depending on the sandbox state of the application
     *
     * @return string  Sandbox connect.js URL for Sanbox mode, otherwise production
     */
    public function getConnectJsUrl()
    {
        return Mage::helper('boltpay/url')->getJsUrl() . "/connect.js";
    }

    /**
     * Initiates the Bolt order creation / token receiving and sets up BoltCheckout with generated data.
     * In BoltCheckout.configure success callback the order is saved in additional ajax call to
     * Bolt_Boltpay_OrderController save action.
     *
     * @param string $checkoutType  'multi-page' | 'one-page' | 'admin'
     * @return string               BoltCheckout javascript
     */
    public function getCartDataJs($checkoutType = 'multi-page')
    {
        return Mage::app()->getLayout()->createBlock('boltpay/checkout_boltpay')->getCartDataJs($checkoutType);
    }

    /**
     * Checking the config
     *
     * @return bool
     */
    public function canUseEverywhere()
    {
        $active = $this->isBoltPayActive();
        $isEverywhere = $this->shouldAddButtonEverywhere();

        return ($active && $isEverywhere);
    }

    /**
     * Get config value from specific bolt config and depending from checkoutType.
     *
     * @param $configPath
     * @param $checkoutType
     * @return string
     */
    public function getPaymentBoltpayConfig($configPath, $checkoutType)
    {
        /** @var string $configValue */
        $configValue = Mage::getStoreConfig('payment/boltpay/'.$configPath);

        return ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) && !Mage::getStoreConfig('payment/boltpay/use_javascript_in_admin') ? '' : $configValue;
    }

    /**
     * @param $item
     *
     * @return string
     */
    public function getItemImageUrl($item)
    {
        /** @var Mage_Catalog_Helper_Image $imageHelper */
        $imageHelper = Mage::helper('catalog/image');

        /** @var Mage_Catalog_Model_Product $_product */
        $_product = $item->getProduct();

        $image = '';
        try {
            if ($_product->getThumbnail()) {
                /** @var Mage_Catalog_Helper_Image $image */
                $image = $imageHelper->init($_product, 'thumbnail', $_product->getThumbnail());
            }
        } catch (Exception $e) {  }

        return (string) $image;
    }

    /**
     * Set customer session based on the quote id passed in
     *
     * @param $quoteId
     */
    public function setCustomerSessionByQuoteId($quoteId)
    {
        $customerId = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId)->getCustomerId();
        $this->setCustomerSessionById($customerId);
    }

    /**
     * Set customer session based on the customer id passed in
     *
     * @param $customerId
     */
    public function setCustomerSessionById($customerId)
    {
        if ($customerId) {
            Mage::getSingleton('customer/session')->loginById($customerId);
        }
    }

    /**
     * Collect Bolt callbacks for js config.
     *
     * @param string $checkoutType
     * @param bool $isVirtualQuote
     *
     * @return string
     */
    public function getBoltCallbacks($checkoutType, $isVirtualQuote = false)
    {
        //////////////////////////////////////////////////////
        // Collect the event Javascripts
        // We execute these events as early as possible, typically
        // before Bolt defined event JS to give merchants the
        // opportunity to do full overrides
        //////////////////////////////////////////////////////

        $checkCustom = $this->getPaymentBoltpayConfig('check', $checkoutType);
        $onCheckoutStartCustom = $this->getPaymentBoltpayConfig('on_checkout_start', $checkoutType);
        $onEmailEnterCustom = $this->getPaymentBoltpayConfig('on_email_enter', $checkoutType);
        $onShippingDetailsCompleteCustom = $this->getPaymentBoltpayConfig('on_shipping_details_complete', $checkoutType);
        $onShippingOptionsCompleteCustom = $this->getPaymentBoltpayConfig('on_shipping_options_complete', $checkoutType);
        $onPaymentSubmitCustom = $this->getPaymentBoltpayConfig('on_payment_submit', $checkoutType);
        $successCustom = $this->getPaymentBoltpayConfig('success', $checkoutType);
        $closeCustom = $this->getPaymentBoltpayConfig('close', $checkoutType);

        $onCheckCallback = $this->buildOnCheckCallback($checkoutType, $isVirtualQuote);
        $onSuccessCallback = $this->buildOnSuccessCallback($successCustom, $checkoutType);
        $onCloseCallback = $this->buildOnCloseCallback($closeCustom, $checkoutType);

        $requiredCheck = ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT)
            ? ""
            : "
                    if (typeof json_cart !== 'undefined' && !json_cart.orderToken) {
                        if (typeof BoltPopup !== \"undefined\") {
                            BoltPopup.addMessage(json_cart.error).show();
                        } else {
                            alert(json_cart.error);
                        }
                        return false;
                    }
            "
        ;

        return "{
                  check: function() {
                    $requiredCheck
                    $checkCustom
                    $onCheckCallback
                    return true;
                  },
                  onCheckoutStart: function() {
                    // This function is called after the checkout form is presented to the user.
                    $onCheckoutStartCustom
                  },
                  onEmailEnter: function(email) {
                    // This function is called after the user enters their email address.
                    $onEmailEnterCustom
                  },
                  onShippingDetailsComplete: function() {
                    // This function is called when the user proceeds to the shipping options page.
                    // This is applicable only to multi-step checkout.
                    $onShippingDetailsCompleteCustom
                  },
                  onShippingOptionsComplete: function() {
                    // This function is called when the user proceeds to the payment details page.
                    // This is applicable only to multi-step checkout.
                    $onShippingOptionsCompleteCustom
                  },
                  onPaymentSubmit: function() {
                    // This function is called after the user clicks the pay button.
                    $onPaymentSubmitCustom
                  },
                  success: $onSuccessCallback,
                  close: function() {
                     $onCloseCallback
                  }
                }";
    }

    /**
     * @param      $checkoutType
     * @param bool $isVirtualQuote
     *
     * @return string
     */
    public function buildOnCheckCallback($checkoutType, $isVirtualQuote = false)
    {
        switch ($checkoutType) {
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN:
                return
                    "
                    if ((typeof editForm !== 'undefined') && (typeof editForm.validate === 'function')) {
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
        
                        var is_valid = true;
        
                        if (!editForm.validate()) {
                            is_valid = false;
                        } ". ($isVirtualQuote ? "" : " else {
                            var shipping_method = $$('input:checked[type=\"radio\"][name=\"order[shipping_method]\"]')[0] || $$('input:checked[type=\"radio\"][name=\"shipping_method\"]')[0];
                            if (typeof shipping_method === 'undefined') {
                                alert('".Mage::helper('boltpay')->__('Please select a shipping method.')."');
                                is_valid = false;
                            }
                        } "). "
        
                        bolt_hidden.classList.add('required-entry');
                        return is_valid;
                    }
                    ";
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT:
                return
                    "
                    return (isFireCheckoutFormValid = checkout.validate());
                    ";
            default:
                return '';
        }
    }

    /**
     * @param string $successCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnSuccessCallback($successCustom, $checkoutType)
    {
        $saveOrderUrl = Mage::helper('boltpay/url')->getMagentoUrl('boltpay/order/save');

        return ($checkoutType === Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN) ?
            "function(transaction, callback) {
                $successCustom

                var input = document.createElement('input');
                input.setAttribute('type', 'hidden');
                input.setAttribute('name', 'bolt_reference');
                input.setAttribute('value', transaction.reference);
                document.getElementById('edit_form').appendChild(input);

                // order and order.submit should exist for admin
                if ((typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                    order_completed = true;
                    callback();
                }
            }"
            : "function(transaction, callback) {
                new Ajax.Request(
                    '$saveOrderUrl',
                    {
                        method:'post',
                        onSuccess:
                            function() {
                                $successCustom
                                order_completed = true;
                                callback();
                            },
                        parameters: 'reference='+transaction.reference
                    }
                );
            }";
    }

    /**
     * @param $closeCustom
     * @param $checkoutType
     * @return string
     */
    public function buildOnCloseCallback($closeCustom, $checkoutType)
    {
        $successUrl = Mage::helper('boltpay/url')->getMagentoUrl(Mage::getStoreConfig('payment/boltpay/successpage'));
        $javascript = "";
        switch ($checkoutType) {
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_ADMIN:
                $javascript .=
                    "
                    if (order_completed && (typeof order !== 'undefined' ) && (typeof order.submit === 'function')) {
                        $closeCustom
                        var bolt_hidden = document.getElementById('boltpay_payment_button');
                        bolt_hidden.classList.remove('required-entry');
                        order.submit();
                    }
                    ";
                break;
            case Bolt_Boltpay_Block_Checkout_Boltpay::CHECKOUT_TYPE_FIRECHECKOUT:
                $javascript .=
                    "
                    isFireCheckoutFormValid = false;
                    initBoltButtons();
                    ";
                break;
            default:
                $javascript .= "
                    if (order_completed) {
                        location.href = '$successUrl';
                    }
                ";
        }

        return $javascript;
    }

    /**
     * Gets the value of a Bolt non-publicized or non-emphasized
     * configuration value after passing it through an optionally
     * defined filter method.
     *
     * @param string $configName        The name of the config as defined
     *                                  the configuration JSON
     * @param array $filterParameters   Optional set of parameters passed to
     *                                  the optionally defined filter method
     *                                  of the config
     *
     * @return mixed    Typically a string representing the config value, but
     *                  is not limited to this type.  If the config is not defined,
     *                  an empty string is returned
     */
    public function getExtraConfig($configName, $filterParameters = array() ) {
        /** @var Bolt_Boltpay_Model_Admin_ExtraConfig $extraConfigModel */
        $extraConfigModel = Mage::getSingleton('boltpay/admin_extraConfig');
        return $extraConfigModel->getExtraConfig($configName, $filterParameters);
    }


    /**
     * Dispatches event to filter a value
     *
     * @param string                    $eventName              The name of the event to be dispatched
     * @param mixed                     $valueToFilter          The value to filter
     * @param array                     $additionalParameters   any extra parameters used in filtering
     *
     * @return mixed   the value after it has been filtered
     */
    public function doFilterEvent($eventName, $valueToFilter, $additionalParameters = array()) {
        $valueWrapper = new Varien_Object();
        $valueWrapper->setValue($valueToFilter);
        Mage::dispatchEvent(
            $eventName,
            array(
                'valueWrapper' => $valueWrapper,
                'parameters' => $additionalParameters
            )
        );

        return $valueWrapper->getValue();
    }
}
