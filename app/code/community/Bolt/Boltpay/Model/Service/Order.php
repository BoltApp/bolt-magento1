<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Model_Service_Order extends Mage_Sales_Model_Service_Order
{
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

            Mage::helper('boltpay/bugsnag')->notifyException($e, $metaData);
            throw $e;
        }

        return $invoice;
    }
}
