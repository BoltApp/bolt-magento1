<?php
// Activate Bolt and properly set its keys
include_once 'app/Mage.php';

Mage::init();

$api_key=Mage::getModel('core/encryption')->encrypt($argv[1]);
$signing_key=Mage::getModel('core/encryption')->encrypt($argv[2]);
$publishable_key_multipage=$argv[3];
$publishable_key_paymentonly=$argv[4];
$publishable_key_admin=$argv[5];

// Save configs
Mage::getModel('core/config')->saveConfig('general/locale/code','en_US');
Mage::getModel('core/config')->saveConfig('currency/options/allow', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/base', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/default', "USD");
Mage::getModel('core/config')->saveConfig('carriers/flatrate/active', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/active', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/test', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/api_key', $api_key);
Mage::getModel('core/config')->saveConfig('payment/boltpay/signing_key', $signing_key);
Mage::getModel('core/config')->saveConfig('payment/boltpay/publishable_key_multipage', $publishable_key_multipage);
Mage::getModel('core/config')->saveConfig('payment/boltpay/publishable_key_onepage', $publishable_key_paymentonly);
Mage::getModel('core/config')->saveConfig('payment/boltpay/publishable_key_admin', $publishable_key_admin);

// Create discounts
function createDiscountRule($couponCode, $additionalData) {
    $allCustomerGroupIds = Mage::getModel('customer/group')->getCollection()->getAllIds();
    $rule = Mage::getModel('salesrule/rule');
    $rule->setName($couponCode)
        ->setDescription($couponCode)
        ->setFromDate('')
        ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        ->setCouponCode($couponCode)
        ->setUsesPerCustomer(0)
        ->setUsesPerCoupon(0)
        ->setCustomerGroupIds($allCustomerIds)
        ->setIsActive(1)
        ->setConditionsSerialized('')
        ->setActionsSerialized('')
        ->setStopRulesProcessing(0)
        ->setIsAdvanced(1)
        ->setProductIds('')
        ->setSortOrder(0)
        ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
        ->setDiscountAmount(0)
        ->setDiscountStep(0)
        ->setSimpleFreeShipping('0')
        ->setApplyToShipping('0')
        ->setIsRss(1)
        ->setWebsiteIds(array(1))
        ->setStoreLabels(array($couponCode));

    $rule->addData($additionalData);
    $rule->save();

    return $rule->getId();
}

createDiscountRule("30poff", array(
    'discount_amount'         => '30',
));
createDiscountRule("1234centsoff", array(
    'discount_amount'         => '1234',
    'simple_action' => 'Mage_SalesRule_Model_Rule::BY_FIXED_ACTION',
));
createDiscountRule("freeship", array(
    'simple_free_shipping'         => '1',
));
createDiscountRule("expired", array(
    'from_date'         => '2000-01-01',
    'to_date'           => '2000-01-02',
));
    
Mage::app()->getCacheInstance()->flush(); 
