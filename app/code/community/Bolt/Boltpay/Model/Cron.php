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
     * After an immutable quote has existed for 2 weeks or more, we remove it from the system.
     * At this point, only the order object is relevant for converted orders and any immutable
     * quote that was to be converted will have been handled well before this time.
     *
     * As an artifact, we leave the parent quotes, (i.e. all Magento created quotes), and converted
     * immutable quotes.  We delegate cleanup responsibility of these to the merchants.
     */
    public function cleanupQuotes() {
        $sales_flat_quote_table = Mage::getSingleton('core/resource')->getTableName('sales/quote');
        $sales_flat_order_table = Mage::getSingleton('core/resource')->getTableName('sales/order');

        $expiration_time = Mage::getModel('core/date')->date('Y-m-d H:i:s', time()-(60*60*24*7*2));

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $sql = "DELETE sfq FROM $sales_flat_quote_table sfq LEFT JOIN $sales_flat_order_table sfo ON sfq.entity_id = sfo.quote_id WHERE (sfq.parent_quote_id IS NOT NULL) AND (sfq.parent_quote_id < sfq.entity_id) AND (sfq.updated_at <= '$expiration_time') AND (sfo.entity_id IS NULL)";
        $connection->query($sql);
    }

    /**
     * After a "pending_bolt" order has existed for 15 minutes or more, we remove it from the system.
     * At this point, Bolt should have called the "failed_payment" webhook to do this. We are catching
     * the anomaly cases where this mechanism fails.
     */
    public function cleanupOrders() {
        $expiration_time = date('Y-m-d H:i:s', time()-(60*15));  // Magento uses GMT to save in DB

        /* @var Mage_Sales_Model_Resource_Order_Collection $orderCollection */
        $orderCollection = Mage::getModel('sales/order')->getCollection();
        $orderCollection
            ->addFieldToFilter('created_at', array( 'lt' => $expiration_time))
            ->addFieldToFilter('status', array('in' => array('pending_bolt','canceled_bolt')))
        ;

        /** @var Mage_Sales_Model_Order $order */
        foreach($orderCollection->getItems() as $order) {
            try {
                /** @var Bolt_Boltpay_Model_Order $orderModel */
                $orderModel = Mage::getModel('boltpay/order');
                $orderModel->removePreAuthOrder($order);
            } catch (Exception $e) {
                $this->boltHelper()->notifyException($e);
            }
        }
    }
}
