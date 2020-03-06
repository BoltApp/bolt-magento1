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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Bolt_Boltpay_Model_FeatureSwitch
 *
 * The Magento Model class that provides features switches related utility methods
 *
 */
class Bolt_Boltpay_Model_FeatureSwitch extends Bolt_Boltpay_Model_Abstract
{
    /**
     * Set flag to true when bolt plugin is updated and we need to update features
     */
    public static $shouldUpdateFeatureSwitches = false;

    /**
     * This method gets feature switches from Bolt and updates the local DB with
     * the latest values. To be used in upgrade data and webhooks.
     */
    public function updateFeatureSwitches()
    {
        try {
            $switchesResponse = $this->boltHelper()->getFeatureSwitches();
        } catch (\Exception $e) {
            // We already created bugsnag about exception
            return;
        }
        $switchesData = @$switchesResponse->data->plugin->features;

        if (empty($switchesData)) {
            return;
        }

        $switches = array();
        foreach ($switchesData as $switch) {
            $switches[$switch->name] = (object)array(
                'value' => $switch->value,
                'defaultValue' => $switch->defaultValue,
                'rolloutPercentage' => $switch->rolloutPercentage
            );
        }
        Mage::getModel('core/config')->saveConfig('payment/boltpay/featureSwitches', json_encode($switches));
        Mage::getModel('core/config')->cleanCache();
    }
}
