<?php
require_once('TestHelper.php');

class Bolt_Boltpay_OrderHelper
{
    /**
     * Get dummy transaction object
     *
     * @param       $grandTotal
     * @param array $items
     *
     * @return mixed
     */
    public function getDummyTransactionObject($grandTotal, $items = array())
    {

        $dummyData = array(
            'order' => array(
                'cart' =>
                    array(
                        'order_reference' => '00000',
                        'display_id' => '0000000000|00000',
                        'currency' =>
                            array(
                                'currency' => 'USD',
                                'currency_symbol' => '$',
                            ),
                        'discounts' => array(),
                        'discount_amount' => array(
                            'amount' => 0
                        ),
                        'tax_amount' => array(
                            'amount' => 0
                        ),
                        'shipping_amount' => array(
                            'amount' => 0
                        ),
                        'subtotal_amount' => array(
                            'amount' => 0
                        ),
                        'total_amount' => array(
                            'amount' => round($grandTotal * 100)
                        )
                    )
            ),
            'status' => 'payment_review'
        );

        foreach ($items as $item) {
            $dummyData['order']['cart']['items'] =
                array(
                    0 =>
                        array(
                            'reference' => '50256',
                            'name' => 'Linen Blazer',
                            'description' => 'Single vented, notched lapels. Flap pockets. Tonal stitching. Fully lined. Linen. Dry clean.',
                            'total_amount' =>
                                array(
                                    'amount' => round($item['amount'] * $item['qty'] * 100),
                                    'currency' => 'USD',
                                    'currency_symbol' => '$',
                                ),
                            'unit_price' =>
                                array(
                                    'amount' => round($item['amount'] * 100),
                                    'currency' => 'USD',
                                    'currency_symbol' => '$',
                                ),
                            'quantity' => $item['qty'],
                            'sku' => $item['sku'],
                            'image_url' => '',
                            'type' => 'physical',
                        ),
                );
        }

        return json_decode(json_encode($dummyData));
    }

    /**
     * Creates dummy order
     *
     * @param int $productId to be used for order creation
     * @param int $qty of the product provided
     * @param string $paymentMethod code used for order creation
     * @param bool $overrideObserverStates determines if this method should override what the observer sets for
     *                                     {@see Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced} and
     *                                     {@see Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus}
     * @param bool $orderWasJustPlaced determines if Bolt order should be considered as just placed for safe-guarding
     * @param bool $canChangePreAuthStatus sets if Bolt order's pre-auth status should be safe-guarded
     *
     * @return Mage_Sales_Model_Order dummy order
     *
     * @throws Mage_Core_Exception if provided payment method is not available
     */
    public static function createDummyOrder(
        $productId,
        $qty = 2,
        $paymentMethod = 'boltpay',
        $overrideObserverStates = true,
        $orderWasJustPlaced = false,
        $canChangePreAuthStatus = true
    )
    {
        $testHelper = new Bolt_Boltpay_TestHelper();
        $testHelper->addTestBillingAddress();
        $addressData = array(
            'firstname' => 'Luke',
            'lastname' => 'Skywalker',
            'street' => 'Sample Street 10',
            'city' => 'Los Angeles',
            'postcode' => '90014',
            'telephone' => '+1 867 345 123 5681',
            'country_id' => 'US',
            'region_id' => 12
        );
        $testHelper->addTestFlatRateShippingAddress($addressData, $paymentMethod);
        $cart = $testHelper->addProduct($productId, $qty);
        $quote = $cart->getQuote();
        $quote->reserveOrderId();

        // Set payment method for the quote
        $quote->getPayment()->importData(array('method' => $paymentMethod));
        $service = Mage::getModel('sales/service_quote', $quote);

        $previousHookValue = Bolt_Boltpay_Helper_Data::$fromHooks;
        if ($paymentMethod == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            //temporarily set flag to true in order to pass order validation
            Bolt_Boltpay_Helper_Data::$fromHooks = true;
        }
        $service->submitAll();
        if ($paymentMethod == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            Bolt_Boltpay_Helper_Data::$fromHooks = $previousHookValue;
            if ($overrideObserverStates) {
                Bolt_Boltpay_Helper_Data::$boltOrderWasJustPlaced = $orderWasJustPlaced;
                Bolt_Boltpay_Helper_Data::$canChangePreAuthStatus = $canChangePreAuthStatus;
            }
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $service->getOrder();

        return $order;
    }

    /**
     * Deletes dummy order by increment id
     *
     * @param $incrementId
     * @throws Zend_Db_Adapter_Exception
     *
     */
    public static function deleteDummyOrderByIncrementId($incrementId)
    {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var Magento_Db_Adapter_Pdo_Mysql $writeConnection */
        $writeConnection = $resource->getConnection('core_write');
        $orderTable = $resource->getTableName('sales/order');
        $orderGridTable = $resource->getTableName('sales/order_grid');

        $query = "DELETE ot, ogt FROM $orderTable ot LEFT JOIN $orderGridTable ogt ON ot.increment_id = ogt.increment_id WHERE ot.increment_id = :increment_id";
        $bind = array(
            'increment_id' => (int)$incrementId
        );

        $writeConnection->query($query, $bind);
    }

    /**
     * Deletes dummy order
     *
     * @param Mage_Sales_Model_Order $order
     */
    public static function deleteDummyOrder(Mage_Sales_Model_Order $order)
    {
        Mage::register('isSecureArea', true);
        $order->delete();
        Mage::unregister('isSecureArea');

    }
}
