<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_IndexController
 *
 * @deprecated OAuth will be removed in future versions
 */
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
