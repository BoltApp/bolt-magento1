<?php

require_once __DIR__ . '/../../shell/abstract.php';

/**
 * Bolt install discount Shell Script
 */
class Bolt_Shell_InstallDiscounts extends Mage_Shell_Abstract
{
    protected function _getRuleConfig()
    {
        return array(
            array(
                'name'              => 'Integration Test: 30POFF',
                'description'       => 'Integration Test: discount 30%',
                'coupon_type'       => Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC,
                'coupon_code'       => '30POFF',
                'discount_amount'   => '30',
                'simple_action'     => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
                'simple_free_shipping'  => '0',
                'apply_to_shipping'     => '0',
            ),
            array(
                'name'              => 'Integration Test: $12.34',
                'description'       => 'Integration Test: discount $12.34',
                'coupon_type'       => Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC,
                'coupon_code'       => '1234CENTSOFF',
                'discount_amount'   => '12.34',
                'simple_action'     => Mage_SalesRule_Model_Rule::BY_FIXED_ACTION,
                'simple_free_shipping'  => '0',
                'apply_to_shipping'     => '0',
            ),
            array(
                'name'              => 'Integration Test FREESHIP',
                'description'       => 'Integration Test: discount free shipping',
                'coupon_type'       => Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC,
                'coupon_code'       => 'FREESHIP',
                'discount_amount'   => '0',
                'simple_action'     => Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
                'simple_free_shipping'  => '2',
                'apply_to_shipping'     => '0',
            )
        );
    }

    /**
     * Run script
     */
    public function run()
    {
        $discountConfig = $this->_getRuleConfig();
        try {
            foreach ($discountConfig as $discountData) {
                $this->setupRuleData($discountData);
            }
        } catch (Mage_Core_Exception $e) {
            var_dump($e->getMessage());
            die;
        } catch (Exception $e) {
            var_dump($e->getMessage());
            die;
        }
    }

    /**
     * @param array $discountData
     *
     * @throws Exception
     */
    public function setupRuleData($discountData = array())
    {
        if (!count($discountData)) {
            throw new Exception('There is no discount data from config');
        }

        // All customer group ids
        $customerGroupIds = Mage::getModel('customer/group')->getCollection()->getAllIds();
        // SalesRule Rule model
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::getModel('salesrule/rule');

        // Rule data
        $rule->setName($discountData['name'])
            ->setDescription($discountData['description'])
            ->setFromDate('')
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setCustomerGroupIds($customerGroupIds)
            ->setIsActive(1)
            ->setCouponType($discountData['coupon_type'])
            ->setCouponCode($discountData['coupon_code'])
            ->setConditionsSerialized('')
            ->setActionsSerialized('')
            ->setStopRulesProcessing(0)
            ->setIsAdvanced(1)
            ->setProductIds('')
            ->setSortOrder(0)
            ->setSimpleAction($discountData['simple_action'])
            ->setDiscountAmount($discountData['discount_amount'])
            ->setSimpleFreeShipping($discountData['simple_free_shipping'])
            ->setApplyToShipping($discountData['apply_to_shipping'])
            ->setIsRss(0)
            ->setWebsiteIds(array(1))
            ->setStoreLabels(array($discountData['description']));

        $rule->save();
    }
}

$shell = new Bolt_Shell_InstallDiscounts();
$shell->run();
