<?php
/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'Mage/Checkout/controllers/OnepageController.php';

/**
 * Class Bolt_Boltpay_OnepageController
 *
 * @deprecated This will be removed after the skip payment option is resolved.
 */
class Bolt_Boltpay_OnepageController
    extends Mage_Checkout_OnepageController implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_BoltGlobalTrait;

    public function saveShippingMethodAction() 
    {
        try {
            if ($this->_expireAjax()) {
                return;
            }

            if ($this->getRequest()->isPost()) {
                $data = $this->getRequest()->getPost('shipping_method', '');
                $result = $this->getOnepage()->saveShippingMethod($data);
                // $result will contain error data if shipping method is empty
                if (!$result) {
                    if (Mage::getStoreConfig('payment/boltpay/skip_payment')) {
                        try {
                            $data = array('method' => Bolt_Boltpay_Model_Payment::METHOD_CODE);
                            $result = $this->getOnepage()->savePayment($data);
                        } catch (Mage_Payment_Exception $e) {
                            if ($e->getFields()) {
                                $result['fields'] = $e->getFields();
                            }

                            $result['error'] = $e->getMessage();
                            Mage::helper('boltpay/bugsnag')->notifyException($e);
                        } catch (Mage_Core_Exception $e) {
                            $result['error'] = $e->getMessage();
                            Mage::helper('boltpay/bugsnag')->notifyException($e);
                        } catch (Exception $e) {
                            //Mage::logException($e);
                            $result['error'] = $this->__('Unable to set Payment Method.');
                            Mage::helper('boltpay/bugsnag')->notifyException($e);
                        }
                    }

                    if (!$result) {
                        Mage::dispatchEvent(
                            'checkout_controller_onepage_save_shipping_method',
                            array(
                                'request' => $this->getRequest(),
                            'quote' => $this->getOnepage()->getQuote())
                        );
                        Mage::helper('boltpay')->collectTotals($this->getOnepage()->getQuote());
                        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                        if (Mage::getStoreConfig('payment/boltpay/skip_payment')) {
                            $this->loadLayout('checkout_onepage_review');
                            $result['goto_section'] = 'review';
                            $result['update_section'] = array(
                                'name' => 'review',
                                'html' => $this->_getReviewHtml());
                        } else {
                            $result['goto_section'] = 'payment';
                            $result['update_section'] = array(
                                'name' => 'payment-method',
                                'html' => $this->_getPaymentMethodsHtml());
                        }
                    }
                }

                Mage::helper('boltpay')->collectTotals($this->getOnepage()->getQuote())->save();
                $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            }
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }
}
