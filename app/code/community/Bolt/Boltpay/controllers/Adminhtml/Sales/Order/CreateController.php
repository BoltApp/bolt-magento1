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

require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Sales'.DS.'Order'.DS.'CreateController.php');

/**
 * Adminhtml sales orders creation process controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Bolt_Boltpay_Adminhtml_Sales_Order_CreateController
    extends Mage_Adminhtml_Sales_Order_CreateController implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_Controller_Traits_OrderControllerTrait;

    /**
     * Add address data to the quote for Bolt.  This is normally deferred to
     * form submission, however, the Bolt order is created prior to that point.
     *
     * @inheritdoc
     */
    public function loadBlockAction()
    {
        $postData = $this->getRequest()->getPost('order');
        $addressData = $this->prepareAddressData($postData);

        Mage::getSingleton('admin/session')->setOrderShippingAddress($addressData);

        parent::loadBlockAction();
    }

    /**
     * @param $postData
     * @return array
     */
    public function prepareAddressData($postData)
    {
        $shippingAddress = $postData['shipping_address'];

        $addressData = array(
            'street_address1' => $shippingAddress['street'][0],
            'street_address2' => $shippingAddress['street'][1],
            'street_address3' => null,
            'street_address4' => null,
            'first_name'      => $shippingAddress['firstname'],
            'last_name'       => $shippingAddress['lastname'],
            'locality'        => $shippingAddress['city'],
            'region'          => Mage::getModel('directory/region')->load($shippingAddress['region_id'])->getCode(),
            'postal_code'     => $shippingAddress['postcode'],
            'country_code'    => $shippingAddress['country_id'],
            'phone'           => $shippingAddress['telephone'],
            'phone_number'    => $shippingAddress['telephone'],
        );

        if (@$postData['account'] && @$postData['account']['email']) {
            $addressData['email'] = $addressData['email_address'] = @$postData['account']['email'];
        }

        return $addressData;
    }

    /**
     * Saving quote and create order.  We add the Bolt reference to the session
     */
    public function saveAction()
    {

        /////////////////////////////////////////////////////////////////////////////
        // Case 1:
        // If there is no bolt reference, then it indicates this is another payment
        // method.  In this case, we differ to Magento to handle this
        /////////////////////////////////////////////////////////////////////////////
        $boltReference = $this->getRequest()->getPost('bolt_reference');
        if (!$boltReference) {
            $this->_normalizeOrderData();  // We must re-normalize the data first
            parent::saveAction();
            return false;
        }
        /////////////////////////////////////////////////////////////////////////////
        
        
        ///////////////////////////////////////////////////
        /// Case 2:
        /// For Bolt orders, we must use the immutable quote to create
        /// this order for subsequent webhooks to succeed.
        ///////////////////////////////////////////////////
        $immutableQuoteId = $this->getImmutableQuoteIdFromTransaction($boltReference);

        $this->_getSession()->setQuoteId($immutableQuoteId);
        
        $this->_normalizeOrderData();
        ///////////////////////////////////////////////////
        

        //////////////////////////////////////////////////////////////
        /// Set variables that will be used in the post order save
        /// Observer event
        //////////////////////////////////////////////////////////////
        Mage::getSingleton('core/session')->setBoltReference($boltReference);
        Mage::getSingleton('core/session')->setWasCreatedByHook(false);
        //////////////////////////////////////////////////////////////

        try {
            $this->_processActionData('save');
            $paymentData = $this->getRequest()->getPost('payment');
            if ($paymentData) {
                $paymentData['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                $this->_getOrderCreateModel()->setPaymentData($paymentData);
                $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
            }

            $orderData = $this->getRequest()->getPost('order');

            $orderCreateModel = $this->_getOrderCreateModel()
                ->setIsValidate(true)
                ->importPostData($orderData);

            $order =  $orderCreateModel->createOrder();

            $this->_postOrderCreateProcessing($order, $immutableQuoteId);

            $this->_getSession()->clear();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The order has been created.'));

            $this->boltHelper()->logInfo("The order {$order->getIncrementId()} has been created.",array('order' => var_export($order->debug(), true)));

            if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
                $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
            } else {
                $this->_redirect('*/sales_order/index');
            }
            ///////////////////////////////////////////////////////

            return true;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            if ($paymentData['method'] == 'boltpay') {
                $this->boltHelper()->notifyException($e);
                $this->boltHelper()->logException($e);
            }
            $this->_getOrderCreateModel()->saveQuote();
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        } catch (Mage_Core_Exception $e){
            if ($paymentData['method'] == 'boltpay') {
                $this->boltHelper()->notifyException($e);
                $this->boltHelper()->logException($e);
            }
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            if ($paymentData['method'] == 'boltpay') {
                $this->boltHelper()->notifyException($e);
                $this->boltHelper()->logException($e);
            }
            $this->_getSession()->addException($e, $this->__('Order saving error: %s', $e->getMessage()));
            $this->_redirect('*/*/');
        }

        return false;
    }

    /**
     * @param $boltReference
     * @return string
     * @throws Exception
     */
    protected function getImmutableQuoteIdFromTransaction($boltReference)
    {
        $transaction = $this->boltHelper()->fetchTransaction($boltReference);
        $immutableQuoteId = $this->boltHelper()->getImmutableQuoteIdFromTransaction($transaction);

        return $immutableQuoteId;
    }

    /**
     * Some versions of Magento store post data for the form with slightly different names
     * and slightly different formats.  Also, over several ajax calls, and several state changes,
     * both in the session data and persisted data, the format of the order data changes.
     * This method normalizes it to the expected format for underlying Magento code to handle our
     * data properly
     */
    protected function _normalizeOrderData()
    {
        $requestPost = $this->getRequest()->getPost();
        if (isset($requestPost['shipping_method']) && $requestPost['shipping_method']) {
            $this->getRequest()->setPost('order', array(
                'shipping_method' => $requestPost['shipping_method']
            ));
        }

        if (@$requestPost['shipping_as_billing']) {
            $this->getRequest()->setPost('shipping_as_billing', @$requestPost['shipping_as_billing']);
        } else {
            $this->getRequest()->setPost('shipping_as_billing', @$requestPost['shipping_same_as_billing']);
        }

        // We must assure that Magento knows to recalculate the shipping
        $this->getRequest()->setPost('collect_shipping_rates', 1);

        unset($requestPost);

        /**
         * Saving order data
         */
        if ($data = $this->getRequest()->getPost('order')) {
            $this->_getOrderCreateModel()->importPostData($data);
        }

        /**
         * init first billing address, need for virtual products
         */
        $this->_getOrderCreateModel()->getBillingAddress();

        /**
         * Flag for using billing address for shipping
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual()) {
            $syncFlag = $this->getRequest()->getPost('shipping_as_billing');
            $shippingMethod = $this->   _getOrderCreateModel()->getShippingAddress()->getShippingMethod();
            if (is_null($syncFlag)
                && $this->_getOrderCreateModel()->getShippingAddress()->getSameAsBilling()
                && empty($shippingMethod)
            ) {
                $this->_getOrderCreateModel()->setShippingAsBilling(1);
            } else {
                $this->_getOrderCreateModel()->setShippingAsBilling((int)$syncFlag);
            }
        }

        /**
         * Change shipping address flag
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual() && $this->getRequest()->getPost('reset_shipping')) {
            $this->_getOrderCreateModel()->resetShippingMethod(true);
        }

        /**
         * Forcibly collect shipping rates if the cart is not virtual
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual()) {
            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()->save();
        }

        Mage::dispatchEvent(
            'bolt_boltpay_admin_normalize_order_data_after',
            array(
                'request' => $this->getRequest(),
                'orderCreateModel' => $this->_getOrderCreateModel()
            )
        );
    }


    /**
     * Function to process the order and the quote directly after order creation
     *
     * @var Mage_Sales_Model_Order $order  the recently created order
     */
    protected function _postOrderCreateProcessing($order)
    {
        ///////////////////////////////////////////////////////
        // Close out session by
        // 1.) deactivating the immutable quote so it can no longer be used
        // 2.) assigning the immutable quote as the parent of its parent quote
        //
        // This creates a circular reference so that we can use the parent quote
        // to look up the used immutable quote
        ///////////////////////////////////////////////////////
        $parentQuote = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->_getSession()->getQuote()->getParentQuoteId());
        $parentQuote->setParentQuoteId($order->getQuoteId())->save();

    }

}
