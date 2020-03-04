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
 * Trait Bolt_Boltpay_Helper_FeatureSwitch_ManagerTrait
 *
 * Provides method to get feature switches from bolt and save them into DB
 *
 */
trait Bolt_Boltpay_Helper_FeatureSwitch_ManagerTrait
{
    use Bolt_Boltpay_Helper_GraphQLTrait;
    /**
     * This method gets feature switches from Bolt and updates the local DB with
     * the latest values. To be used in upgrade data and webhooks.
     */
    public function updateSwitchesFromBolt()
    {
        $switchesResponse = $this->getFeatureSwitches();
        $switchesData = @$switchesResponse->data->plugin->features;

        if (!is_array($switchesData) || count($switchesData) == 0) {
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
