<?php

class Bolt_Bolt_CartController extends Mage_Core_Controller_Front_Action
{
    public function addCommentAction() {
        $quote = $this->getCart()->getQuote();
        $comment = $this->getRequest()->getParam('comment');

        $quote->setCustomerNote($comment)->save();
        echo $quote->getCustomerNote();
    }

    /**
     * Action to apply store credit
     */
    public function applyStoreCreditAction()
    {
        $quote = $this->getCart()->getQuote();
        if (
            !$quote ||
            !$quote->getId() ||
            !$quote->getCustomerId() ||
            !$quote->getItemsCount() ||
            $quote->getBaseGrandTotal() + $quote->getBaseAmstcredAmountUsed() <= 0
        ) {
            $this->_redirect('checkout/cart');
            return;
        }

        $shouldUseBalance = $this->getRequest()->getParam('storecreditbalance');
        $store = Mage::app()->getStore($quote->getStoreId());

        $quote->setAmstcredUseCustomerBalance($shouldUseBalance);

        if ($shouldUseBalance) {
            $balance = Mage::getModel('amstcred/balance')
                ->setCustomerId($quote->getCustomerId())
                ->setWebsiteId($store->getWebsiteId())
                ->loadByCustomer();

            if ($balance) {
                $quote->setAmstcredCustomerBalanceInstance($balance);
            }
        }

        $quote->save();

        $this->_redirect('checkout/cart');
    }

    protected function getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }
}
