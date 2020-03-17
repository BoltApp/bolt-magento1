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
     * Received empty request from Bolt.
     * Send to bolt API request to update feature switches
     * Send response with status - success or failure
     */
    public function updateAction()
    {
        $updateResult = Mage::getSingleton("boltpay/featureSwitch")->updateFeatureSwitches();
        if ($updateResult) {
            $this->sendResponse(self::HTTP_OK, array('status' => 'success'));
        } else {
            $this->sendResponse(self::HTTP_UNPROCESSABLE_ENTITY, array('status' => 'failure'));
        }
    }
}
