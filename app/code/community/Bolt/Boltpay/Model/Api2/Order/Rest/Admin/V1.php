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
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_Api2_Order_Rest_Admin_V1
 *
 * This class implements this magento store's webhooks
 * @deprecated Oauth will no longer be supported in future additions
 */
class Bolt_Boltpay_Model_Api2_Order_Rest_Admin_V1 extends Bolt_Boltpay_Model_Api2_Order
{
    /**
     * @var array  The response payload for successful order creation
     * @return array
     */
    public static function successOrderCreated()
    {
        return array(
                'message' => Mage::helper('boltpay')->__('New Order created'),
                'status' => 'success',
                'http_response_code' => 201
            );
    }

    /**
     * @var array  The response payload for successful order updates
     * @return array
     */
    public static function successOrderUpdated()
    {
        return array(
            'message' => Mage::helper('boltpay')->__('Updated existing order'),
            'status' => 'success',
            'http_response_code' => 200
        );
    }

    /**
     * @inheritdoc
     */
    public function dispatch() 
    {
        try {
            parent::dispatch();
            $this->getResponse()->clearHeader("Location");
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }

    }

    /**
     * API Hook endpoint that processes all web hook requests
     *
     * @param array $couponData    Currently this array is not being used but it called at some points with "filteredData"
     * @return null
     */
    function _create($couponData)
    {
        try {
            //Mage::log('Initiating webhook call', null, 'bolt.log');

            $bodyParams = $this->getRequest()->getBodyParams();
            //Mage::log(trim(json_encode($bodyParams)), null, 'bolt.log');

            $reference = $bodyParams['reference'];
            $transactionId = $bodyParams['transaction_id'];
            $hookType = $bodyParams['notification_type'];

            $boltHelper = Mage::helper('boltpay/api');

            $boltHelperBase = Mage::helper('boltpay');
            $boltHelperBase::$fromHooks = true;

            $transaction = $boltHelper->fetchTransaction($reference);
            $displayId = $transaction->order->cart->display_id;

            $order = Mage::getModel('sales/order')->loadByIncrementId($displayId);

            if (sizeof($order->getData()) > 0) {
                //Mage::log('Order Found. Updating it', null, 'bolt.log');
                $orderPayment = $order->getPayment();

                $newTransactionStatus = Bolt_Boltpay_Model_Payment::translateHookTypeToTransactionStatus($hookType);

                $prevTransactionStatus = $orderPayment->getAdditionalInformation('bolt_transaction_status');
                $merchantTransactionId = $orderPayment->getAdditionalInformation('bolt_merchant_transaction_id');
                if ($merchantTransactionId == null || $merchantTransactionId == '') {
                    $orderPayment->setAdditionalInformation('bolt_merchant_transaction_id', $transactionId);
                    $orderPayment->save();
                } elseif ($merchantTransactionId != $transactionId) {
                    $this->_critical(
                        Mage::helper('boltpay')->__(
                            'Transaction id mismatch. Expected: %s got: %s', $merchantTransactionId, $transactionId
                        ),
                        Mage_Api2_Model_Server::HTTP_BAD_REQUEST
                    );
                }

                $orderPayment->setData('auto_capture', $newTransactionStatus == 'completed');
                $orderPayment->getMethodInstance()
                    ->setStore($order->getStoreId())
                    ->handleTransactionUpdate($orderPayment, $newTransactionStatus, $prevTransactionStatus);

                $orderUpdatedResponse = $this->successOrderUpdated();
                $this->getResponse()->addMessage(
                    $orderUpdatedResponse['message'], $orderUpdatedResponse['http_response_code'],
                    array(), 'success'
                );
                $this->getResponse()->setHttpResponseCode(Mage_Api2_Model_Server::HTTP_OK);
                $this->_render($this->getResponse()->getMessages());
                //Mage::log('Order update was successful', null, 'bolt.log');
                return;
            }

            //Mage::log('Order not found. Creating one', null, 'bolt.log');

            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $displayId)
                ->getFirstItem();

            $quoteId = $bodyParams['quote_id'] ?: $quote->getId();

            if (sizeof($quote->getData()) == 0) {
                //Mage::log("Quote not found: $quoteId. Quote must have been already processed.", null, 'bolt.log');
                $this->_critical(
                    Mage::helper('boltpay')->__("Quote not found: %s.  Quote must have been already processed.", $quoteId), Mage_Api2_Model_Server::HTTP_BAD_REQUEST
                );
            }

            if (empty($reference) || empty($transactionId)) {
                $this->_critical(
                    Mage::helper('boltpay')->__('Reference and/or transaction_id is missing'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST
                );
            }
            /** @var Bolt_Boltpay_Model_Order $boltOrderModel */
            $boltOrderModel = Mage::getModel('boltpay/order');
            $boltOrderModel->createOrder($reference, $sessionQuoteId = null);

            $orderUpdatedResponse = $this->successOrderUpdated();
            $this->getResponse()->addMessage(
                $orderUpdatedResponse['message'], $orderUpdatedResponse['http_response_code'],
                array(), 'success'
            );
            //Mage::log('Order creation was successful', null, 'bolt.log');
            $this->_render($this->getResponse()->getMessages());
        } catch (Bolt_Boltpay_OrderCreationException $invalid) {
            // An invalid transition is treated as a late queue event and hence will be ignored
            $error = $invalid->getMessage();
            //Mage::log($error, null, 'bolt.log');
            //Mage::log("Late queue event. Returning as OK", null, 'bolt.log');


            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    "API HOOKS late queue event" => array (
                        "message" => $error,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            $this->_critical($error, Mage_Api2_Model_Server::HTTP_OK);
        } catch (Exception $e) {
            $error = $e->getMessage();
            //Mage::log($error, null, 'bolt.log');

            Mage::helper('boltpay/bugsnag')->addBreadcrumb(
                array(
                    "API HOOKS Exception" => array (
                        "message" => $error,
                        "class" => __CLASS__,
                        "method" => __METHOD__,
                    )
                )
            );

            Mage::helper('boltpay/bugsnag')->notifyException($e);

            $this->_critical($error, Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
    }
}
