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
 * Class Bolt_Boltpay_Helper_ShippingAndTax
 *
 * The Magento Helper class that provides shipping and tax related utility methods
 *
 */
class Bolt_Boltpay_Helper_ShippingAndTax extends Mage_Core_Helper_Abstract
{
    /**
     *  Updates the shipping address data and, if necessary, the fills in missing
     *  billing address data with shipping address data
     *
     *
     * @param Mage_Sales_Model_Quote    $quote             The quote to which the address will be applied
     * @param array                     $shippingAddress   The Bolt formatted address data
     *
     * @return  array   The shipping address applied in Magento compatible format
     */
    public function applyShippingAddressToQuote( $quote, $shippingAddress ) {

        $directory = Mage::getModel('directory/region')->loadByName($shippingAddress->region, $shippingAddress->country_code);
        $region = $directory->getName(); // For region field should be the name not a code.
        $regionId = $directory->getRegionId(); // This is require field for calculation: shipping, shopping price rules and etc.

        $addressData = array(
            'email' => $shippingAddress->email ?: $shippingAddress->email_address,
            'firstname' => $shippingAddress->first_name,
            'lastname' => $shippingAddress->last_name,
            'street' => $shippingAddress->street_address1 . ($shippingAddress->street_address2 ? "\n" . $shippingAddress->street_address2 : ''),
            'company' => $shippingAddress->company,
            'city' => $shippingAddress->locality,
            'region' => $region,
            'region_id' => $regionId,
            'postcode' => $shippingAddress->postal_code,
            'country_id' => $shippingAddress->country_code,
            'telephone' => $shippingAddress->phone ?: $shippingAddress->phone_number
        );

        if ($quote->getCustomerId()) {
            $customerSession = Mage::getSingleton('customer/session');
            $customerSession->setCustomerGroupId($quote->getCustomerGroupId());
            $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
            $address = $customer->getPrimaryShippingAddress();

            if (!$address) {
                $address = Mage::getModel('customer/address');

                $address->setCustomerId($customer->getId())
                    ->setCustomer($customer)
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1')
                    ->save();


                $address->addData($addressData);
                $address->save();

                $customer->addAddress($address)
                    ->setDefaultShippingg($address->getId())
                    ->save();
            }
        }
        $quote->removeAllAddresses();
        $quote->save();
        $quote->getShippingAddress()->addData($addressData)->save();

        $billingAddress = $quote->getBillingAddress();

        $quote->getBillingAddress()->addData(
            array(
                'email' => $billingAddress->getEmail() ?: ($shippingAddress->email ?: $shippingAddress->email_address),
                'firstname' => $billingAddress->getFirstname() ?: $shippingAddress->first_name,
                'lastname' => $billingAddress->getLastname() ?: $shippingAddress->last_name,
                'street' => implode("\n", $billingAddress->getStreet()) ?: $shippingAddress->street_address1 . ($shippingAddress->street_address2 ? "\n" . $shippingAddress->street_address2 : ''),
                'company' => $billingAddress->getCompany() ?: $shippingAddress->company,
                'city' => $billingAddress->getCity() ?: $shippingAddress->locality,
                'region' => $billingAddress->getRegion() ?: $region,
                'region_id' => $billingAddress->getRegionId() ?: $regionId,
                'postcode' => $billingAddress->getPostcode() ?: $shippingAddress->postal_code,
                'country_id' => $billingAddress->getCountryId() ?: $shippingAddress->country_code,
                'telephone' => $billingAddress->getTelephone() ?: ($shippingAddress->phone ?: $shippingAddress->phone_number)
            )
        )->save();

        return $addressData;
    }
}