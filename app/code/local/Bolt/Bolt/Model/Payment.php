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

class Bolt_Bolt_Model_Payment extends Bolt_Boltpay_Model_Payment
{

    protected $_validStateTransitions = array(
        self::TRANSACTION_AUTHORIZED => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_COMPLETED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_PENDING),
        self::TRANSACTION_COMPLETED => array(self::TRANSACTION_REFUND, self::TRANSACTION_NO_NEW_STATE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_PENDING => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_ON_HOLD => array(self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_REVERSIBLE, self::TRANSACTION_REJECTED_IRREVERSIBLE),
        self::TRANSACTION_REJECTED_IRREVERSIBLE => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_REJECTED_REVERSIBLE => array(self::TRANSACTION_AUTHORIZED, self::TRANSACTION_CANCELLED, self::TRANSACTION_REJECTED_IRREVERSIBLE, self::TRANSACTION_COMPLETED),
        self::TRANSACTION_CANCELLED => array(self::TRANSACTION_NO_NEW_STATE),
        self::TRANSACTION_REFUND => array(self::TRANSACTION_REFUND,self::TRANSACTION_NO_NEW_STATE)
    );

    /**
     * @param \Mage_Payment_Model_Info $payment
     *
     * @throws \Exception
     */
    protected function createInvoiceForHookRequest(Mage_Payment_Model_Info $payment)
    {
        $boltCaptures = $this->getNewBoltCaptures($payment);

        $order = $payment->getOrder();
        $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);

        if (count($boltCaptures) == 0 && $order->getGrandTotal() == 0) {
            $boltCaptures = $this->removeInvoicedCaptures($payment, array(0));
        }
        // Create invoices for items from $boltCaptures that are not exists on Magento
        $identifier = count($boltCaptures) > 1 ? 0 : null;

        foreach ($boltCaptures as $captureAmount) {
            $invoice = $this->createInvoice($order, $captureAmount / 100);
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $this->preparePaymentAndAddTransaction($payment, $invoice, $identifier);
            $order->addRelatedObject($invoice);
            $identifier++;
        }

        $order->save();
    }
}
