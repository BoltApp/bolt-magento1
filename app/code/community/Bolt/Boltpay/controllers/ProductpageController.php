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
 * Class Bolt_Boltpay_ProductpageController
 */
class Bolt_Boltpay_ProductpageController extends Mage_Core_Controller_Front_Action
{
    public function createCartAction()
    {
        try {
            $hmacHeader = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $requestJson = file_get_contents('php://input');

            /* @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');

            if (!$boltHelper->verify_hook($requestJson, $hmacHeader)) {
                throw new Exception(Mage::helper('boltpay')->__("Failed HMAC Authentication"));
            }

            $request = json_decode($requestJson);
            //            //region Hard code request data for now
            //            $request = json_decode(
            //                json_encode(
            //                    array(
            //                        'currency' => 'USD',
            //                        'items'    => array(
            //                            array(
            //                                'reference' => '905',
            //                                'price'      => '16000',
            //                                'image'      => '',
            //                                'name'       => 'Plaid Cotton Shirt-Royal Blue-L',
            //                                'color'      => 'Blue',
            //                                'quantity'   => 1,
            //                                'size'       => 'L'
            //                            )
            //                        ),
            //                        'total'    => 0
            //                    )
            //                )
            //            );
            //            //endregion

            /** @var Bolt_Boltpay_Model_Productpage_Cart $productCartModel */
            $productCartModel = Mage::getModel('boltpay/productpage_cart');
            $productCartModel->init($request);
            $productCartModel->generateData();

            $this->sendResponse($productCartModel->getResponseHttpCode(), $productCartModel->getResponseBody());
        } catch (Exception $e) {
            // unexpected error
            $this->sendResponse(422, array(
                'status' => 'failure',
                'error'  =>
                    array(
                        'code'    => 6009,
                        'message' => $e->getMessage()
                    )
            ));

            Mage::helper('boltpay/bugsnag')->notifyException($e);
        }
    }

    /**
     * @param int   $httpCode
     * @param array $data
     *
     * @throws \Zend_Controller_Response_Exception
     */
    protected function sendResponse($httpCode, $data = array())
    {
        Mage::helper('boltpay/api')->setResponseContextHeaders();
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode($httpCode);
        $this->getResponse()->setBody(json_encode($data));
    }
}
