<?php

class Bolt_Bolt_Model_BoltOrder extends Bolt_Boltpay_Model_BoltOrder
{
    
    /**
     * Function get store credit balance of customer
     *
     * @param $customerId
     *
     * @return mixed
     */
    protected function getStoreCreditBalance($customerId)
    {
        return Mage::getModel('amstcred/balance')->getCollection()
            ->addFilter('customer_id', $customerId)->getFirstitem()->getAmount();
    }

    protected function addDiscounts($totals, &$cartSubmissionData, $quote = null)
    {
        $cartSubmissionData['discounts'] = array();
        $totalDiscount = 0;

        foreach ($this->discountTypes as $discount) {
            if (@$totals[$discount] && $amount = $totals[$discount]->getValue()) {
                // Some extensions keep discount totals as positive values,
                // others as negative, which is the Magento default.
                // Using the absolute value.
                $discountAmount = (int) abs(round($amount * 100));

                if($discount=='amgiftcard'){
                    $giftCardsBalance = $quote->getAmGiftCardsAmount();
                    $discountAmount = abs(round(($giftCardsBalance) * 100));
                }elseif ($discount == 'amstcred') {
                    $customerId = $quote->getCustomer()->getId();
                    if ($customerId) $discountAmount = abs(round($this->getStoreCreditBalance($customerId) * 100));
                }

                $description = $totals[$discount]->getAddress()->getDiscountDescription();
                $description = Mage::helper('boltpay')->__('Discount (%s)', $description);

                $cartSubmissionData['discounts'][] = array(
                    'amount'      => $discountAmount,
                    'description' => $description,
                    'type'        => 'fixed_amount',
                );
                $totalDiscount += $discountAmount;
            }
        }

        return $totalDiscount;
    }


}
