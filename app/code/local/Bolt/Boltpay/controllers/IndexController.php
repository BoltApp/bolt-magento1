<?php

class Bolt_Boltpay_IndexController extends Mage_Adminhtml_Controller_Action {
    public function oauthAction() {
        $this->loadLayout()->renderLayout();
    }

    public function statusAction() {
        $this->loadLayout()->renderLayout();
    }

    public function saveAction() {
        try {

            $req = $this->getRequest();
            $consumerKey = $req->getParam('consumer_key');
            $consumerToken = $req->getParam('consumer_token');
            $token = $req->getParam('access_token');
            $tokenSecret = $req->getParam('access_token_secret');

            $boltHelper = Mage::helper('boltpay/api');

            $reqData = array(
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerToken,
                'access_token' => $token,
                'access_token_secret' => $tokenSecret,
                'type' => 'magento_oauth1'
            );

            $boltHelper->transmit('oauth', $reqData, 'merchant', 'division');
            $this->_getSession()->addSuccess($this->__('Publish Successful'));
            $this->loadLayout()->renderLayout();
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }
}
