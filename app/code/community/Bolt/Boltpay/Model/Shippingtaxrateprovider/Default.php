<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Model_Shippingtaxrateprovider_Default extends Bolt_Boltpay_Model_Shippingtaxrateprovider_Abstract {
    public function getRate() {
        $taxCalculationModel = $this->getTaxCalculationModel();

        return $taxCalculationModel->getRate($this->createRateRequest());
    }

    protected function getTaxCalculationModel() {
        return Mage::getSingleton('tax/calculation');
    }

    protected function createRateRequest() {
        $quote = $this->getQuote();

        $rateRequest = Mage::getSingleton('tax/calculation')->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $this->getStore()
        );

        $rateRequest->setProductClassId($this->getShippingTaxClassId());

        return $rateRequest;
    }

    protected function getStore() {
        return Mage::getModel('core/store')->load($this->getQuote()->getStoreId());
    }

    protected function getShippingTaxClassId() {
        return Mage::getStoreConfig('tax/classes/shipping_tax_class', $this->getQuote()->getStoreId());
    }
}
