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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Handles validation and updating of the the config API Key (payment/boltpay/api_key)
 */
class Bolt_Boltpay_Model_Admin_ApiKey extends Mage_Adminhtml_Model_System_Config_Backend_Encrypted
{
    use Bolt_Boltpay_BoltGlobalTrait;

    /**
     * Updates feature switches if the API Key is changed to a valid non-empty value
     *
     * @return Mage_Core_Model_Abstract  $this
     *
     * @throws Exception when there is an error saving the extra config to the database
     */
    public function save()
    {
        parent::save();
        $newValueDecrypted = Mage::helper('core')->decrypt($this->getValue());
        $oldValueDecrypted = $this->getOldValue();

        if (!empty($newValueDecrypted) && ($newValueDecrypted !== $oldValueDecrypted)) {
            //store config reinit happens after save, overwrite config directly
            Mage::app()->getStore()->setConfig('payment/boltpay/api_key', $this->getValue());
            try {
                Mage::getSingleton('boltpay/featureSwitch')->updateFeatureSwitches();
            } catch (GuzzleHttp\Exception\GuzzleException $guzzleException) {

                // If there is a problem updating feature switches, then we'll restore old key value
                $this->setValue($oldValueDecrypted);

                Mage::getSingleton('core/session')->addError(
                    $this->boltHelper()->__('Error updating API Key: ' . $guzzleException->getMessage())
                );

                parent::save();
            }
        }

        return $this;
    }
}