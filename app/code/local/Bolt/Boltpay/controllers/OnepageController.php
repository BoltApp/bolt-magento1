<?php

require_once 'Mage/Checkout/controllers/OnepageController.php';

class Bolt_Boltpay_OnepageController extends Mage_Checkout_OnepageController {
    public function saveShippingMethodAction() {
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
                    } catch (Mage_Core_Exception $e) {
                        $result['error'] = $e->getMessage();
                    } catch (Exception $e) {
                        Mage::logException($e);
                        $result['error'] = $this->__('Unable to set Payment Method.');
                    }
                }

                if (!$result) {
                    Mage::dispatchEvent(
                        'checkout_controller_onepage_save_shipping_method',
                        array(
                            'request' => $this->getRequest(),
                            'quote'   => $this->getOnepage()->getQuote()));
                    $this->getOnepage()->getQuote()->collectTotals();
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
            $this->getOnepage()->getQuote()->collectTotals()->save();
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    public function saveOrderAction() {
        $payment = $this->getOnepage()->getQuote()->getPayment();
        $method = $payment->getMethod();

        if (strtolower($method) == Bolt_Boltpay_Model_Payment::METHOD_CODE) {
            $data = $this->getRequest()->getPost('payment', array());
            $transactionStatus = $data['transaction_status'];
            $reference = $data['reference'];

            $payment->setAdditionalInformation('bolt_transaction_status', $transactionStatus);
            $payment->setAdditionalInformation('bolt_reference', $reference);
            $payment->setTransactionId($reference);
        }

        parent::saveOrderAction();
    }
}
