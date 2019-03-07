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

class Bolt_Boltpay_Model_Service_Order extends Mage_Sales_Model_Service_Order
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Prepare order invoice without any items
     *
     * @param $amount
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function prepareInvoiceWithoutItems($amount)
    {
        try {
            $invoice = $this->_convertor->toInvoice($this->_order);
            $invoice->setBaseGrandTotal($amount);
            $invoice->setSubtotal($amount);
            $invoice->setBaseSubtotal($amount);
            $invoice->setGrandTotal($amount);

            $this->_order->getInvoiceCollection()->addItem($invoice);
        } catch(Exception $e) {
            $metaData = array(
                'amount'   => $amount,
                'order' => var_export($this->_order->debug(), true)
            );

            static::helper()->notifyException($e, $metaData);
            throw $e;
        }

        return $invoice;
    }
}
