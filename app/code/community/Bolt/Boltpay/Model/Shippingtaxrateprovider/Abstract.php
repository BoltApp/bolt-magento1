<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Shipping
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */

/**
 * Class Bolt_Boltpay_Model_Shippingtaxrateprovider_Abstract
 *
 * The Magento model that provides utility methods for the following operations:
 *
 * 1. Getting tax rates
 */

abstract class Bolt_Boltpay_Model_Shippingtaxrateprovider_Abstract extends Varien_Object {
    protected $quote;

    /**
     * Gets tax rate
     *
     * @abstract
     * @return float Tax rate
     */
    abstract public function getRate();

    public function setQuote($quote) {
        if(isset($quote)) {
            $this->quote = $quote;
        }

        return $this;
    }

    protected function getQuote() {
        if(!isset($this->quote)) {
            $this->quote = Mage::getModel('checkout/cart')->getQuote();
        }

        return $this->quote;
    }
}
