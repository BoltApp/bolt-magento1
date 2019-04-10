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

class Bolt_Boltpay_Model_Order_Detail extends Bolt_Boltpay_Model_Abstract
{
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_DIGITAL = 'digital';

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $order;

    protected $generatedData = array();

    // Billing / shipping address fields that are required when the address data is sent to Bolt.
    protected $requiredAddressFields = array(
        'first_name',
        'last_name',
        'street_address1',
        'locality',
        'region',
        'postal_code',
        'country_code',
    );

    /**
     * Init data for model
     **
     *
     * @param $reference
     *
     * @return $this
     * @throws \Exception
     */
    public function init($reference)
    {
        /** @var Mage_Sales_Model_Order_Payment $orderPayment */
        $orderPayment = Mage::getModel('sales/order_payment')
            ->getCollection()
            ->addFieldToFilter('last_trans_id', array('like' => "$reference%"))
            ->getFirstItem();

        try {
            $this->initWithPayment($orderPayment);
        } catch (Exception $e) {
            if (!$this->initByReference($reference)) {
                throw $e;
            }
        }

        return $this;
    }


    /**
     * Init data for model
     *
     * @param Mage_Sales_Model_Order_Payment $orderPayment
     *
     * @return $this
     * @throws \Mage_Core_Exception
     */
    public function initWithPayment(Mage_Sales_Model_Order_Payment $orderPayment)
    {
        if (!$orderPayment->getId()) {
            Mage::throwException($this->boltHelper()->__("No payment found"));
        }

        $order = Mage::getModel('sales/order')->load($orderPayment->getParentId());

        if (!$order->getId()) {
            Mage::throwException($this->boltHelper()->__("No order found with ID of {$orderPayment->getParentId()}"));
        }

        $this->order = $order;

        return $this;
    }

    /**
     * @param $reference
     *
     * @return $this|bool
     * @throws \Exception
     */
    public function initByReference($reference)
    {

        $transaction = $this->boltHelper()->fetchTransaction($reference);

        /* If display_id has been confirmed and updated on Bolt, then we should look up the order by display_id */
        $order = Mage::getModel('sales/order')->loadByIncrementId($transaction->order->cart->display_id);

        /* If it hasn't been confirmed, or could not be found, we use the quoteId as fallback */
        if ($order->isObjectNew()) {
            $quoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);
            $order = Mage::getModel('boltpay/order')->getOrderByQuoteId($quoteId);
        }

        if (!$order->getId()) {
            return false;
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Generate order detail for validating order on Bolt side
     *
     * @return array
     * @throws \Mage_Core_Exception
     */
    public function generateOrderDetail()
    {
        if (!$this->validateOrderDetail()) {
            return $this->generatedData;
        }

        $this->addOrderDetails();
        $this->addItemDetails();
        $this->addTotals();

        return $this->generatedData;
    }

    /**
     * Validate order for generating data for Bolt
     *
     * @return bool
     * @throws \Mage_Core_Exception
     */
    protected function validateOrderDetail()
    {
        if (!$this->order->getId()) {
            Mage::throwException($this->boltHelper()->__("No order found"));

        }

        if ($this->order->getPayment()->getMethod() != 'boltpay') {
            Mage::throwException($this->boltHelper()->__("Payment method is not 'boltpay'"));
        }

        return true;
    }

    protected function addOrderDetails()
    {
        $this->addOrderReference();
        $this->addDisplayId();
        $this->addCurrency();
        $this->addBillingAddress();
        $this->addShipments();
    }

    protected function addOrderReference()
    {
        $this->generatedData['order_reference'] = $this->order->getQuoteId();
    }

    protected function addDisplayId()
    {
        $this->generatedData['display_id'] = $this->order->getIncrementId();
    }

    protected function addCurrency()
    {
        $this->generatedData['currency'] = $this->order->getOrderCurrencyCode();
    }

    protected function addBillingAddress()
    {
        $this->generatedData['billing_address'] = $this->getGeneratedBillingAddress();
    }

    protected function addShipments()
    {
        $this->generatedData['shipments'] = $this->getGeneratedShipments();
    }

    protected function addItemDetails()
    {
        $this->generatedData['items'] = $this->getGeneratedItems();
    }

    protected function addTotals()
    {
        $this->addDiscounts();
        $this->addTotalAmount();
        $this->addTaxAmount();
    }

    protected function addDiscounts()
    {
        $this->generatedData['discounts'] = $this->getGeneratedDiscounts();
    }

    protected function addTotalAmount()
    {
        $this->generatedData['total_amount'] = (int)round($this->order->getGrandTotal() * 100);
    }

    protected function addTaxAmount()
    {
        $this->generatedData['tax_amount'] = (int)round($this->order->getTaxAmount() * 100);
    }

    /**
     *
     * @return array
     */
    protected function getGeneratedItems()
    {
        $items = $this->order->getAllVisibleItems();

        return array_map(
            function ($item) {
                $imageUrl = $this->boltHelper()->getItemImageUrl($item);
                /** @var Mage_Catalog_Model_Product $product */
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                $type = $product->getTypeId() == 'virtual' ? self::ITEM_TYPE_DIGITAL : self::ITEM_TYPE_PHYSICAL;

                $unitPrice = (int)round($item->getPrice() * 100);
                $quantity = (int)($item->getQtyOrdered());
                $totalAmount = (int)round($unitPrice * $quantity);

                return array(
                    'reference'    => $this->order->getQuoteId(),
                    'image_url'    => $imageUrl,
                    'name'         => $item->getName(),
                    'sku'          => $item->getSku(),
                    'description'  => substr($product->getDescription(), 0, 8182) ?: '',
                    'total_amount' => $totalAmount,
                    'unit_price'   => $unitPrice,
                    'quantity'     => $quantity,
                    'type'         => $type
                );
            }, $items
        );
    }

    /**
     *
     * @return mixed
     */
    protected function getGeneratedDiscounts()
    {
        $discounts = array();

        $discountAmount = abs((int)round($this->order->getDiscountAmount() * 100));

        if ($discountAmount) {
            $discounts[] = array(
                'amount'      => $discountAmount,
                'description' => $this->order->getDiscountDescription(),
                'type'        => 'fixed_amount',
            );
        }

        return $discounts;
    }

    /**
     *
     * @return mixed
     */
    protected function getGeneratedBillingAddress()
    {
        $billingAddress = $this->order->getBillingAddress();

        if (!$billingAddress) {
            return false;
        }

        $generatedBillingAddress = array(
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
            'email'           => $billingAddress->getEmail() ?: $this->order->getCustomerEmail(),
            'phone_number'    => $billingAddress->getTelephone(),
            'email_address'   => $billingAddress->getEmail() ?: $this->order->getCustomerEmail(),
        );

        foreach ($this->requiredAddressFields as $field) {
            if (empty($generatedBillingAddress[$field])) {
                return false;
            }
        }

        return $generatedBillingAddress;
    }

    /**
     *
     * @return mixed
     */
    protected function getGeneratedShipments()
    {
        $order = $this->order;
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            return false;
        }

        $region = $shippingAddress->getRegion();
        if (empty($region) && !in_array($shippingAddress->getCountry(), array('US', 'CA'))) {
            $region = $shippingAddress->getCity();
        }

        $cartShippingAddress = array(
            'street_address1' => $shippingAddress->getStreet1(),
            'street_address2' => $shippingAddress->getStreet2(),
            'street_address3' => $shippingAddress->getStreet3(),
            'street_address4' => $shippingAddress->getStreet4(),
            'first_name'      => $shippingAddress->getFirstname(),
            'last_name'       => $shippingAddress->getLastname(),
            'locality'        => $shippingAddress->getCity(),
            'region'          => $region,
            'postal_code'     => $shippingAddress->getPostcode(),
            'country_code'    => $shippingAddress->getCountry(),
            'phone'           => $shippingAddress->getTelephone(),
            'email'           => $shippingAddress->getEmail() ?: $order->getCustomerEmail(),
            'phone_number'    => $shippingAddress->getTelephone(),
            'email_address'   => $shippingAddress->getEmail() ?: $order->getCustomerEmail(),
        );

        return array(
            array(
                'shipping_address' => $cartShippingAddress,
                'tax_amount'       => (int)round($order->getShippingTaxAmount() * 100),
                'service'          => $order->getShippingDescription(),
                'carrier'          => $order->getShippingMethod(),
                'reference'        => $order->getShippingMethod(),
                'cost'             => (int)round($order->getShippingAmount() * 100),
            )
        );
    }
}
