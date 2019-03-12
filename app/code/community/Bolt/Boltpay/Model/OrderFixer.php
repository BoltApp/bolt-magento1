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

class Bolt_Boltpay_Model_OrderFixer extends Bolt_Boltpay_Model_Abstract
{
    protected $boltTransaction = null;

    /** @var \Mage_Sales_Model_Order */
    protected $magentoOrder = null;

    protected $originalMagentoGrandTotal = null;

    /**
     * Sets up any variables needed
     *
     * @param \Mage_Sales_Model_Order $magentoOrder
     * @param                         $boltTransaction
     *
     * @throws \Bolt_Boltpay_BadInputException
     */
    public function setupVariables(Mage_Sales_Model_Order $magentoOrder, $boltTransaction)
    {
        $this->magentoOrder = $magentoOrder;
        $this->boltTransaction = $boltTransaction;
        $this->validateOrderAndTransaction();
        $this->originalMagentoGrandTotal = $this->magentoOrder->getGrandTotal();
    }

    /**
     * @throws \Bolt_Boltpay_BadInputException
     */
    public function updateOrderToMatchBolt()
    {
        try {
            $this->validateOrderAndTransaction();
            $this->updateOrderItemPrices();
            $this->updateOrderTotals();
            $this->notifyAndSaveOrder();
        } catch (\Bolt_Boltpay_BadInputException $e) {
            $this->boltHelper()->notifyException($e);
            throw $e;
        }
    }

    /**
     * Determines if Magento order should be overwritten by Bolt order due to mismatch
     *
     * @return boolean
     */
    public function requiresOrderUpdateToMatchBolt()
    {
        try {
            $this->validateOrderAndTransaction();

            if (
                !$this->overrideMagnetoOrderOnMismatch() ||
                $this->getMismatchPriceDifference() > $this->getMismatchPriceToleranceConfig() ||
                $this->getBoltGrandTotal() == $this->magentoOrder->getGrandTotal() ||
                !$this->itemsAndQuantitiesAreIdentical()
            ) {
                return false;
            }

            return true;
        } catch (\Bolt_Boltpay_BadInputException $e) {
            $this->boltHelper()->notifyException($e);
            return false;
        }
    }

    /**
     * @throws \Bolt_Boltpay_BadInputException
     */
    protected function validateOrderAndTransaction()
    {
        if (!$this->magentoOrder || !$this->magentoOrder->getId() || !$this->boltTransaction) {
            throw new \Bolt_Boltpay_BadInputException(
                $this->boltHelper()->__('Need to set setup variables in order to use class %s', get_class($this))
            );
        }
    }

    /**
     * Set item price based on bolt price if mismatch issue occurs
     */
    protected function updateOrderItemPrices()
    {
        $magentoItems = $this->magentoOrder->getAllVisibleItems();
        if ($this->doesOrderItemsHaveTheSameSku($magentoItems)) {
            return;
        }

        $boltItems = $this->getBoltItems();

        /** @var \Mage_Sales_Model_Order_Item $magentoItem */
        foreach ($magentoItems as $magentoItem) {
            foreach ($boltItems as $boltItem) {
                if (
                    $magentoItem->getSku() == $boltItem->sku &&
                    $magentoItem->getPrice() != $this->getBoltItemPrice($boltItem)
                ) {
                    $this->bugsnagTheItemChange($magentoItem, $boltItem);
                    $this->updateOrderItemPrice($magentoItem, $boltItem);
                }
            }
        }
    }

    protected function updateOrderTotals()
    {
        $boltSubTotal = $this->getBoltSubtotal();
        $boltShippingAmount = $this->getBoltShippingAmount();
        $boltTaxAmount = $this->getBoltTaxAmount();
        $boltGrandTotal = $this->getBoltGrandTotal();
        $boltDiscountAmount = $this->getBoltDiscountAmount();
        $boltDiscountDescription = $this->getBoltDiscountDescription();

        $this->magentoOrder->setSubtotal($boltSubTotal);
        $this->magentoOrder->setBaseSubtotal($boltSubTotal);
        $this->magentoOrder->setShippingAmount($boltShippingAmount);
        $this->magentoOrder->setBaseShippingAmount($boltShippingAmount);
        $this->magentoOrder->setTaxAmount($boltTaxAmount);
        $this->magentoOrder->setBaseTaxAmount($boltTaxAmount);
        $this->magentoOrder->setGrandTotal($boltGrandTotal);
        $this->magentoOrder->setBaseGrandTotal($boltGrandTotal);
        $this->magentoOrder->setDiscountAmount(-$boltDiscountAmount);
        $this->magentoOrder->setBaseDiscountAmount(-$boltDiscountAmount);
        $this->magentoOrder->setDiscountDescription($boltDiscountDescription);
    }

    /**
     * @throws \Exception
     */
    protected function notifyAndSaveOrder()
    {
        $msg = $this->boltHelper()->__(
            "There is a price mismatch issue when saving the order, forcing the price from $%s to $%s",
            $this->originalMagentoGrandTotal,
            $this->getBoltGrandTotal());
        $this->magentoOrder->setState(
            Bolt_Boltpay_Model_Payment::transactionStatusToOrderStatus($this->boltTransaction->status),
            true,
            $msg
        )->save();
    }

    /**
     * @param $magentoItems
     *
     * @return boolean
     */
    protected function doesOrderItemsHaveTheSameSku($magentoItems)
    {
        $skus = array();
        foreach ($magentoItems as $magentoItem) {
            $skus[] = $magentoItem->getSku();
        }

        $values = array_count_values($skus);
        foreach ($values as $key => $value) {
            if ($value > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Mage_Sales_Model_Order_Item $magentoItem
     * @param                              $boltItem
     */
    protected function bugsnagTheItemChange(Mage_Sales_Model_Order_Item $magentoItem, $boltItem)
    {
        $this->boltHelper()->notifyException(
            new Exception(
                $this->boltHelper()->__(
                    "The order item %s price of order #%s has been updated from %s to %s.",
                    $magentoItem->getSku(),
                    $this->magentoOrder->getIncrementId(),
                    $magentoItem->getPrice(),
                    $this->getBoltItemPrice($boltItem)
                )
            )
        );
    }

    /**
     * @param \Mage_Sales_Model_Order_Item $magentoItem
     * @param                              $boltItem
     */
    protected function updateOrderItemPrice(Mage_Sales_Model_Order_Item $magentoItem, $boltItem)
    {
        $boltItemPrice = $this->getBoltItemPrice($boltItem);
        $magentoItem->setPrice($boltItemPrice);
        $magentoItem->setBasePrice($boltItemPrice);
        $magentoItem->setOriginalPrice($boltItemPrice);
        $magentoItem->setBaseOriginalPrice($boltItemPrice);
        $magentoItem->setRowTotal($this->getBoltItemTotalPrice($boltItem));
        $magentoItem->setBaseRowTotal($this->getBoltItemTotalPrice($boltItem));
    }

    /**
     * @return boolean
     */
    protected function overrideMagnetoOrderOnMismatch()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/override_magento_order_on_mismatch');
    }

    /**
     * @return float
     */
    protected function getMismatchPriceToleranceConfig()
    {
        return Mage::getStoreConfig('payment/boltpay/mismatch_price_tolerance');
    }

    /**
     * @return float
     */
    protected function getMismatchPriceDifference()
    {
        return abs($this->getBoltGrandTotal() - $this->magentoOrder->getGrandTotal());
    }

    /**
     * @return bool
     */
    protected function itemsAndQuantitiesAreIdentical()
    {
        $magentoSkuQuantities = array();

        foreach ($this->magentoOrder->getAllVisibleItems() as $magentoItem) {
            $sku = strtoupper($magentoItem->getSku());
            $qty = $magentoItem->getQtyOrdered();

            if (!array_key_exists($sku, $magentoSkuQuantities)) {
                $magentoSkuQuantities[$sku] = 0;
            }

            $magentoSkuQuantities[$sku] += intval($qty);
        }

        $boltSkuQuantities = array();

        foreach ($this->getBoltItems($this->boltTransaction) as $boltItem) {
            $sku = strtoupper($boltItem->sku);
            $qty = $boltItem->quantity;

            if (!array_key_exists($sku, $boltSkuQuantities)) {
                $boltSkuQuantities[$sku] = 0;
            }

            $boltSkuQuantities[$sku] += intval($qty);
        }

        return $magentoSkuQuantities == $boltSkuQuantities;
    }

    /**
     * @return mixed
     */
    protected function getBoltItems()
    {
        return $this->boltTransaction->order->cart->items;
    }

    /**
     * @param $boltItem
     *
     * @return float|int
     */
    protected function getBoltItemPrice($boltItem)
    {
        return $boltItem->unit_price->amount / 100;
    }

    /**
     * @param $boltItem
     *
     * @return float|int
     */
    protected function getBoltItemTotalPrice($boltItem)
    {
        return $boltItem->total_amount->amount / 100;
    }

    /**
     * @return float|int
     */
    protected function getBoltGrandTotal()
    {
        return $this->boltTransaction->order->cart->total_amount->amount / 100;
    }

    /**
     * @return float|int
     */
    protected function getBoltSubtotal()
    {
        return $this->boltTransaction->order->cart->subtotal_amount->amount / 100;
    }

    /**
     * @return float|int
     */
    protected function getBoltShippingAmount()
    {
        return $this->boltTransaction->order->cart->shipping_amount->amount / 100;
    }

    /**
     * @return float|int
     */
    protected function getBoltTaxAmount()
    {
        return $this->boltTransaction->order->cart->tax_amount->amount / 100;
    }

    /**
     * @return float|int
     */
    protected function getBoltDiscountAmount()
    {
        return $this->boltTransaction->order->cart->discount_amount->amount / 100;
    }

    /**
     * @return string
     */
    protected function getBoltDiscountDescription()
    {
        $boltDiscounts = $this->boltTransaction->order->cart->discounts;
        $boltDiscountDescriptions = array();
        foreach ($boltDiscounts as $boltDiscount) {
            $discountDescription = ltrim($boltDiscount->description, 'Discount (');
            $discountDescription = rtrim($discountDescription, ')');
            $boltDiscountDescriptions[] = $discountDescription;
        }
        $boltDiscountDescription = join(", ", $boltDiscountDescriptions);

        return $boltDiscountDescription;
    }
}
