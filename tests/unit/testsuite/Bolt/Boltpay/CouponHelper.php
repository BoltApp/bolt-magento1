<?php

class Bolt_Boltpay_CouponHelper
{
    /**
     * Makes a dummy request object for coupon api
     *
     * @param array $additionalData
     *
     * @return object
     */
    public function setUpRequest($additionalData = array()) {

        $requestData = array(
            'type'           => 'discounts.code.apply',
            'discount_code'  => '',
            'cart'           =>
                array(
                    'order_reference' => '00000',
                    'display_id'      => '0000000000|00000',
                    'currency'        =>
                        array(
                            'currency'        => 'USD',
                            'currency_symbol' => '$',
                        ),
                    'subtotal_amount' => null,
                    'total_amount'    =>
                        array(
                            'amount'          => 45500,
                            'currency'        => 'USD',
                            'currency_symbol' => '$',
                        ),
                    'items'           =>
                        array(
                            0 =>
                                array(
                                    'reference'    => '50256',
                                    'name'         => 'Linen Blazer',
                                    'description'  => 'Single vented, notched lapels. Flap pockets. Tonal stitching. Fully lined. Linen. Dry clean.',
                                    'total_amount' =>
                                        array(
                                            'amount'          => 45500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ),
                                    'unit_price'   =>
                                        array(
                                            'amount'          => 45500,
                                            'currency'        => 'USD',
                                            'currency_symbol' => '$',
                                        ),
                                    'quantity'     => 1,
                                    'sku'          => 'msj012c',
                                    'image_url'    => '',
                                    'type'         => 'physical',
                                ),
                        ),
                ),
            'customer_name'  => '',
            'customer_email' => '',
            'customer_phone' => '',
        );

        foreach ($additionalData as $key => $value) {
            $requestData[$key] = $value;
        }

        return json_decode(json_encode($requestData));
    }

    /**
     * Creates dummy quote
     *
     * @param array  $quoteData
     * @param string $customerEmail
     *
     * @return int The Rule ID of the newly created quote
     * @throws Exception
     */
    public static function createDummyQuote($quoteData = array(), $customerEmail = 'bolt@bolt.com') {
        $quote = Mage::getModel('sales/quote');
        $quote->setData($quoteData);
        $quote->setData('customer_email', $customerEmail);

        $quote->save();

        return $quote->getId();
    }

    /**
     * Deletes dummy quote
     *
     * @param $quoteId
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function deleteDummyQuote($quoteId) {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var Magento_Db_Adapter_Pdo_Mysql $writeConnection */
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('sales/quote');

        $query = "DELETE FROM $table WHERE entity_id = :quoteId";
        $bind = array(
            'quoteId' => (int)$quoteId
        );

        $writeConnection->query($query, $bind);
    }

    /**
     * Creates dummy rule
     *
     * @param string $couponCode
     * @param array  $additionalData
     * @param array  $couponData
     *
     * @return int The Rule ID of the pre-existing or newly created product
     * @throws Varien_Exception
     */
    public static function createDummyRule($couponCode = 'percent-coupon', $additionalData = array(), $couponData = array())
    {
        if(self::getCouponIdByCode($couponCode)){
            $rule = Mage::getModel('salesrule/rule')->load(self::getCouponByCode($couponCode)->getRuleId());
            if (!$rule->isObjectNew()) return $rule->getId();
        }

        // All customer group ids
        $customerGroupIds = Mage::getModel('customer/group')->getCollection()->getAllIds();
        // SalesRule Rule model
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::getModel('salesrule/rule');

        // Rule data
        $rule->setName('Dummy Percent Rule')
            ->setDescription('Dummy Percent Rule description')
            ->setFromDate('')
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($couponCode)
            ->setUsesPerCustomer(0)
            ->setUsesPerCoupon(0)
            ->setCustomerGroupIds($customerGroupIds)
            ->setIsActive(1)
            ->setConditionsSerialized('')
            ->setActionsSerialized('')
            ->setStopRulesProcessing(0)
            ->setIsAdvanced(1)
            ->setProductIds('')
            ->setSortOrder(0)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(30)
            ->setDiscountStep(0)
            ->setSimpleFreeShipping('0')
            ->setApplyToShipping('0')
            ->setIsRss(1)
            ->setWebsiteIds(array(1))
            ->setStoreLabels(array('Dummy Percent Rule Frontend Label'));

        $rule->addData($additionalData);

        $rule->save();

        if ($couponData) {
            $coupon = $rule->getPrimaryCoupon();
            $coupon->addData($couponData);
            $coupon->save();
        }

        return $rule->getId();
    }

    /**
     * Deletes dummy rule
     *
     * @param $ruleId
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function deleteDummyRule($ruleId) {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        /** @var Magento_Db_Adapter_Pdo_Mysql $writeConnection */
        $writeConnection = $resource->getConnection('core_write');
        $ruleTable = $resource->getTableName('salesrule/rule');
        $couponTable = $resource->getTableName('salesrule/coupon');

        $query = "DELETE $ruleTable, $couponTable FROM $ruleTable INNER JOIN $couponTable ON $ruleTable.rule_id = $couponTable.rule_id WHERE $ruleTable.rule_id = :ruleId";

        $bind = array(
            'ruleId' => (int)$ruleId
        );

        $writeConnection->query($query, $bind);
    }

    /**
     * Creates dummy customer
     *
     * @param array  $additionalData
     * @param string $email
     *
     * @return mixed
     *
     * @throws Mage_Core_Model_Store_Exception if the store can not be found
     */
    public static function createDummyCustomer($additionalData = array(), $email = "bolt@bolt.com") {
        $customer = Mage::getModel("customer/customer");
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $customer->loadByEmail($email);
        if (!$customer->getId()) {
            $customer->setData($additionalData);
            $customer->setEmail($email);
            $customer->save();
        }

        return $customer->getId();
    }

    /**
     * Deletes dummy customer
     * @param $customerId
     *
     * @throws Varien_Exception
     */
    public static function deleteDummyCustomer($customerId) {
        $resource = Mage::getSingleton('core/resource');
        $writeAdapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('customer/entity');

        $query = "DELETE FROM $table WHERE entity_id = :customer_id ";
        $bind = array(
            'customer_id' => (int)$customerId,
        );

        $writeAdapter->query($query, $bind);
    }

    /**
     *
     * @param $ruleId
     * @param $customerId
     * @param $timesUsed
     *
     * @return mixed
     * @throws Varien_Exception
     */
    public static function createDummyRuleCustomerUsageLimits($ruleId, $customerId, $timesUsed)
    {
        $ruleCustomer = Mage::getModel('salesrule/rule_customer')
            ->setData('rule_id', $ruleId)
            ->setData('customer_id', $customerId)
            ->setData('times_used', $timesUsed)
            ->save();

        return $ruleCustomer->getData('rule_customer_id');
    }

    /**
     * Function create dummy coupon customer usage limits
     *
     * @param $customerId
     * @param $couponId
     * @param $timesUsed
     *
     * @throws Varien_Exception
     */
    public static function createDummyCouponCustomerUsageLimits($couponId, $customerId, $timesUsed)
    {
        $resource = Mage::getSingleton('core/resource');
        $writeAdapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('salesrule/coupon_usage');
        $query = "INSERT INTO {$table} (`customer_id`,`coupon_id`,`times_used`) VALUES ({$customerId},{$couponId},{$timesUsed});";
        $writeAdapter->query($query);
    }

    /**
     * Function get coupon by coupon code
     *
     * @param $code
     *
     * @return object
     */
    public static function getCouponByCode($code)
    {
        return Mage::getModel('salesrule/coupon')->load($code, 'code');
    }

    /**
     * Function get coupon id by coupon code
     *
     * @param $code
     *
     * @return mixed
     */
    public static function getCouponIdByCode($code)
    {
        return self::getCouponByCode($code)->getId();
    }
}
