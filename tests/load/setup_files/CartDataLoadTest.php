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
 * Class Bolt_Boltpay_Model_CartDataLoadTest
 *
 * The Magento Model class that creates a cart and saves it to the session for LoadTesting
 *
 */
class Bolt_Boltpay_Model_CartDataLoadTest extends Bolt_Boltpay_Model_Abstract
{
    /**
     * Adds the given items to a cart and saves it to the checkout session
     *
     * @return  void
     *
     */
    public function saveCart($cartItems) {
        // add items to a cart
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        foreach ($cartItems as $item) {
            $product = Mage::getModel('catalog/product')->load($item->id);
            $quote->addProduct($product, 1);
        }
        // save the cart to the session
        $quote->collectTotals()->save();
    }
}