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

/**
 * Class Bolt_Boltpay_ProductpageController
 */
class Bolt_Boltpay_ProductpageController
    extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_BoltGlobalTrait;

    public function createCartAction()
    {
        try {
            $hmacHeader = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $requestJson = file_get_contents('php://input');

            if (!$this->boltHelper()->verify_hook($requestJson, $hmacHeader)) {
                $exception = new Exception($this->boltHelper()->__("Failed HMAC Authentication"));
                $this->boltHelper()->logWarning($exception->getMessage());
                throw $exception;
            }

            $request = json_decode($requestJson);

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

            $this->boltHelper()->notifyException($e);
            $this->boltHelper()->logException($e);
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
        $this->boltHelper()->setResponseContextHeaders();
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode($httpCode);
        $this->getResponse()->setBody(json_encode($data));
    }
}
