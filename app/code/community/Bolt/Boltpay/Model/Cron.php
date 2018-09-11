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
 * Class Bolt_Boltpay_Model_Cron
 *
 * This class implements Bolt specific cron task
 */
class Bolt_Boltpay_Model_Cron
{
    /**
     * After an immutable quote has existed for a week or more, we remove it from the system.
     * At this point, only the order object is relevant for converted orders and any immutable
     * quote that was to be converted will have been handled well before this time.
     *
     * As an artifact, we leave the parent quotes and delegate cleanup responsibility of these to
     * the merchants.
     */
    public function cleanupQuotes() {
        $expiration_time = Mage::getModel('core/date')->date('Y-m-d H:i:s', time()-(60*60*24*7));

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sql = "DELETE FROM sales_flat_quote WHERE (parent_quote_id IS NOT NULL) AND (parent_quote_id < entity_id) AND (updated_at <= '$expiration_time')";
        $connection->query($sql);
    }
}