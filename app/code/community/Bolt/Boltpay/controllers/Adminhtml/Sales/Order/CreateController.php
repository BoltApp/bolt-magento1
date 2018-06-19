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
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2016 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Sales'.DS.'Order'.DS.'CreateController.php');

/**
 * Adminhtml sales orders creation process controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Bolt_Boltpay_Adminhtml_Sales_Order_CreateController extends Mage_Adminhtml_Sales_Order_CreateController
{

    /**
     * Add address data to the quote for Bolt.  This is normally deferred to
     * form submission, however, the Bolt order is created prior to that point.
     *
     * @inheritdoc
     */
    public function loadBlockAction()
    {
        $quote = $this->_getQuote();
        $postData = $this->getRequest()->getPost('order');
        $shippingAddress = $postData['shipping_address'];

        $addressData = array(
            'street_address1' => $shippingAddress['street'][0],
            'street_address2' => $shippingAddress['street'][1],
            'street_address3' => null,
            'street_address4' => null,
            'first_name'      => $shippingAddress['firstname'],
            'last_name'       => $shippingAddress['lastname'],
            'locality'        => $shippingAddress['city'],
            'region'          => Mage::getModel('directory/region')->load($shippingAddress['region_id'])->getCode(),
            'postal_code'     => $shippingAddress['postcode'],
            'country_code'    => $shippingAddress['country_id'],
            'phone'           => $shippingAddress['telephone'],
            'phone_number'    => $shippingAddress['telephone'],
        );

        if (@$postData['account'] && @$postData['account']['email']) {
            $addressData['email'] = $addressData['email_address'] = @$postData['account']['email'];
        }

        Mage::getSingleton('admin/session')->setOrderShippingAddress($addressData);

        parent::loadBlockAction();
    }

    /**
     * Saving quote and create order.  We add the Bolt reference to the session
     */
    public function saveAction()
    {
        $boltReference = $this->getRequest()->getPost('bolt_reference');
        Mage::getSingleton('core/session')->setBoltReference($boltReference);

        parent::saveAction();
    }
}
