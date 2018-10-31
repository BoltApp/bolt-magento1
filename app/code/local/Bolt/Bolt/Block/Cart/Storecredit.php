<?php
class Bolt_Bolt_Block_Cart_Storecredit extends Amasty_StoreCredit_Block_Checkout_Onepage_Payment_Additional
{
    /**
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('bolt/cart/applyStoreCredit', array('_secure' => $this->_isSecure()));
    }
}