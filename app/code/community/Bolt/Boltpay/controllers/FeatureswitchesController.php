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
 * Class Bolt_Boltpay_FeatureswitchesController
 */
class Bolt_Boltpay_FeatureswitchesController
    extends Mage_Core_Controller_Front_Action implements Bolt_Boltpay_Controller_Interface
{
    use Bolt_Boltpay_Controller_Traits_WebHookTrait;

    /**
     * Initializes Controller member variables
     */
    public function _construct()
    {
        // Bolt server doesn't sign this request because it doesn't send any payload
        $this->requestMustBeSigned = false;
    }

    /**
     * Receives push directive to pull feature switches from Bolt and
     * to update them locally.  The success of failure of the local update
     * is then reported back to Bolt
     *
     * @throws Zend_Controller_Response_Exception if response code is out of expected range
     */
    public function updateAction()
    {
        try {
            Mage::getSingleton("boltpay/featureSwitch")->updateFeatureSwitches();
            $this->sendResponse(self::HTTP_OK, array('status' => 'success'));
        } catch (GuzzleHttp\Exception\GuzzleException $exception) {
            $this->boltHelper()->logException($exception);
            $this->boltHelper()->notifyException($exception, array(), 'error');
            $this->sendResponse(self::HTTP_UNPROCESSABLE_ENTITY, array('status' => 'failure'));
        }
    }
}
