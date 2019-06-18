<?php

class Bolt_Bolt_Model_Observer extends Amasty_Rules_Model_Observer
{
    const XML_PATH_BUG_SNAG_LOG = 'payment/boltpay/bugsnag_log';

    /**
     * @param $observer
     * Process quote item validation and discount calculation
     * @return $this
     */
    public function handleValidation($observer)
    {
        $promotions =  Mage::getModel('amrules/promotions');
        $promotions->process($observer);
        return $this;
    }

    /**
     * Log quote delete to Bugsnag and DataDog
     *
     * @param $observer
     */
    public function logDeletedQuote($observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();
        try{
            if ($this->DidQuoteUseBolt($quote) && $this->isQuoteInUse($quote)) {
                if ($this->isBugSnagLogEnabled()) {
                    $message = Mage::helper('boltpay')->__("The quote [{$quote->getId()}] was removed");
                    Mage::helper('boltpay/bugsnag')->notifyException(new Exception($message));
                }
            }
        }catch (Exception $e){}
    }

    /**
     * @return bool
     */
    protected function isBugSnagLogEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_BUG_SNAG_LOG);
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    protected function DidQuoteUseBolt(Mage_Sales_Model_Quote $quote)
    {
        $payment = $quote->getPayment();
        $method = $payment->getMethod();

        return strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    protected function isQuoteInUse(Mage_Sales_Model_Quote $quote)
    {
        $quoteId = $quote->getId();
        $parentQuoteId = $quote->getData('parent_quote_id');
        if ($quoteId && $parentQuoteId) {
            if ($this->DoesQuoteHaveOrder($quoteId)) {
                return true;
            }

            /** @var Mage_Sales_Model_Quote $potentialImmutableQuote */
            $potentialImmutableQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($parentQuoteId);

            if ($this->DoesQuoteHaveOrder($potentialImmutableQuote->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $quoteId
     *
     * @return bool
     */
    protected function DoesQuoteHaveOrder($quoteId)
    {
        $order = Mage::getModel('boltpay/order')->getOrderByQuoteId($quoteId);

        if ($order->getId()) {
            return true;
        }

        return false;
    }
}
