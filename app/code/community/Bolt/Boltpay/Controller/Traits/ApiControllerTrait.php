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
 * Trait Bolt_Boltpay_Controller_Traits_ApiControllerTrait
 *
 * Defines generalized actions associated with API calls to and from the Bolt server
 *
 * @method Mage_Core_Controller_Response_Http getResponse()
 * @method Mage_Core_Model_Layout getLayout()
 *
 */
trait Bolt_Boltpay_Controller_Traits_ApiControllerTrait {

    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * @var string The body of the request made to this controller
     */
    protected $payload;

    /**
     * @var string The signed payload which used the stores signing secret
     */
    protected $signature;

    /**
     * @var bool determines if JSON is expected return type for preDispatch optimization.
     */
    protected $willReturnJson = true;

    /**
     * @var bool mandates that all request to this controller must be signed
     */
    protected $requestMustBeSigned = true;

    /**
     * For JSON, clears response body and header, and sets headers.
     * After this, verifies request is from Bolt, if not, sends error message response
     *
     * @return Mage_Core_Controller_Front_Action
     *
     * @throws Exception Thrown if request cannot be verified as originating from Bolt
     */
    public function preDispatch()
    {
        if ($this->willReturnJson) {
            $this->getResponse()->clearAllHeaders()->clearBody();
            $this->boltHelper()->setResponseContextHeaders();
            $this->getResponse()
                ->setHeader('Content-type', 'application/json', true);

            $this->getLayout()->setDirectOutput(true);
        }

        if ( $this->requestMustBeSigned ) $this->verifyBoltSignature($this->payload, $this->signature);

        return parent::preDispatch();
    }

    /**
     * Verifies that a request originated from and was signed by Bolt.  If not,
     * an error response is sent to caller and the execution of the script is halted
     * immediately
     *
     * @param string $payload       The body to be compared against a signature
     * @param string $signature     The signature against the payload with the signing secret
     *
     * @throws Zend_Controller_Response_Exception if an invalid HTTP response code is set
     */
    function verifyBoltSignature($payload, $signature ) {
        if (!$this->boltHelper()->verify_hook($payload, $signature)) {
            $exception = new Bolt_Boltpay_OrderCreationException(
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR,
                Bolt_Boltpay_OrderCreationException::E_BOLT_GENERAL_ERROR_TMPL_HMAC
            );

            $this->getResponse()
                ->setHttpResponseCode($exception->getHttpCode())
                ->setBody($exception->getJson())
                ->setException($exception)
                ->sendResponse();

            $this->boltHelper()->notifyException($exception, array(), 'warning');
            exit;
        }
    }
}