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
 * Class Bolt_Boltpay_Model_Cron
 *
 * This class implements Bolt specific cron task
 */
class Bolt_Boltpay_Model_Cron
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * We will have a conservative tolerance of 30 minutes for non-confirmed pre-auth orders before we allow the system
     * to remove them
     */
    const PRE_AUTH_STATE_TIME_LIMIT_MINUTES = 30;

    /**
     * 60*60*24*7*2=1209600 is the amount of time in seconds we allow immutable quotes to exist before we delete them
     */
    const IMMUTABLE_QUOTE_EXPIRATION_SECONDS = 1209600;

    /**
     * After an immutable quote / unused generated session quote on PDP has existed for 2 weeks or more, we remove it from the system.
     * At this point, only the order object is relevant for converted orders and any immutable
     * quote that was to be converted will have been handled well before this time.
     *
     * As an artifact, we leave the parent quotes, (i.e. all Magento created quotes), and converted
     * immutable quotes. We delegate cleanup responsibility of these to the merchants.
     */
    public function cleanupQuotes() {
        try {
            $sales_flat_quote_table = Mage::getSingleton('core/resource')->getTableName('sales/quote');
            $sales_flat_order_table = Mage::getSingleton('core/resource')->getTableName('sales/order');

            $expiration_time = Mage::getModel('core/date')->date('Y-m-d H:i:s', time()-self::IMMUTABLE_QUOTE_EXPIRATION_SECONDS);

            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

            $sql = "DELETE sfq
                    FROM $sales_flat_quote_table sfq
                    LEFT JOIN $sales_flat_order_table sfo
                    ON sfq.entity_id = sfo.quote_id
                    WHERE (((sfq.parent_quote_id IS NOT NULL) AND (sfq.parent_quote_id < sfq.entity_id)) OR
                            ((sfq.parent_quote_id IS NULL) AND (sfq.is_bolt_pdp = true)))
                    AND (sfq.updated_at <= '$expiration_time')
                    AND (sfo.entity_id IS NULL)";

            $connection->query($sql);
        } catch ( Exception $e ) {
            $this->boltHelper()->notifyException($e, array(), 'warning');
            $this->boltHelper()->logWarning($e->getMessage());
        }
    }

    /**
     * After a pre-auth pending order has existed for 15 minutes or more, we remove it from the system.
     * At this point, Bolt should have called the "failed_payment" webhook to do this. We are catching
     * the anomaly cases where the "failed_payment" mechanism is not implemented
     *
     * ( e.g. when the Bolt server-side is configured to ignore the pre-auth flow on timeouts or "abnormal responses"
     * and authorization fails. )
     */
    public function cleanupOrders()
    {
        try {
            $expiration_time = gmdate(
                'Y-m-d H:i:s',
                time() - (60 * self::PRE_AUTH_STATE_TIME_LIMIT_MINUTES)
            );  // Magento uses GMT to save in DB

            /* @var Mage_Sales_Model_Resource_Order_Collection $orderCollection */
            $orderCollection = Mage::getModel('sales/order')->getCollection();
            $orderCollection->addFieldToFilter('created_at', array('gteq' => $expiration_time))
                ->setOrder('created_at', 'ASC');

            /** @var Mage_Sales_Model_Order $deletePendingPaymentOrdersBeforeThis */
            $deletePendingPaymentOrdersBeforeThis = $orderCollection->getFirstItem();

            $firstNonExpiredId = $deletePendingPaymentOrdersBeforeThis->getId();
            /* @var Mage_Sales_Model_Resource_Order_Collection $expiredPendingPaymentOrderCollection */
            $expiredPendingPaymentOrderCollection = Mage::getModel('sales/order')->getCollection();
            if ($firstNonExpiredId) {
                $expiredPendingPaymentOrderCollection->addFieldToFilter(
                    'entity_id',
                    array('lt' => $firstNonExpiredId)
                );
            }
            $expiredPendingPaymentOrderCollection->addFieldToFilter(
                'status',
                Bolt_Boltpay_Model_Payment::TRANSACTION_PRE_AUTH_PENDING
            );

            $ordersToRemove = $expiredPendingPaymentOrderCollection->getItems();

            /** @var Bolt_Boltpay_Model_Order $orderModel */
            $orderModel = Mage::getModel('boltpay/order');

            /** @var Mage_Sales_Model_Order $order */
            foreach ($ordersToRemove as $order) {
                try {
                    $orderModel->removePreAuthOrder($order);
                } catch (Exception $e) {
                    // catch, report and clobber so that we can continue with the queue of orders
                    $this->boltHelper()->notifyException($e, array(), 'warning');
                    $this->boltHelper()->logWarning($e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Catch-all for unexpected exceptions
            $this->boltHelper()->notifyException($e, array(), 'warning');
            $this->boltHelper()->logWarning($e->getMessage());
        }
    }

    /**
     * Deactivate quotes which belong to created orders
     */
    public function deactivateQuote()
    {
        try {
            $sales_flat_quote_table = Mage::getSingleton('core/resource')->getTableName('sales/quote');
            $sales_flat_order_table = Mage::getSingleton('core/resource')->getTableName('sales/order');
            $sales_flat_order_payment_table = Mage::getSingleton('core/resource')->getTableName('sales/order_payment');

            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

            $sql = sprintf(
                'UPDATE %s SET is_active = 0 ' .
                                'WHERE is_active = 1 ' .
                                'AND entity_id IN ' .
                                    '(SELECT sfo.quote_id ' .
                                    'FROM %s AS sfo ' .
                                    'INNER JOIN %s AS sfop ' .
                                    'ON sfo.entity_id = sfop.parent_id ' .
                                    'WHERE sfop.method = "%s")',
                $sales_flat_quote_table,
                $sales_flat_order_table,
                $sales_flat_order_payment_table,
                Bolt_Boltpay_Model_Payment::METHOD_CODE
            );

            $connection->query($sql);
        } catch (\Exception $exception) {
            $this->boltHelper()->notifyException($exception);
        }
    }

}