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

/**
 * Class Bolt_Boltpay_ShippingController
 *
 * Shipping And Tax endpoint.
 */
class Bolt_Boltpay_ShippingController extends Mage_Core_Controller_Front_Action
{

    /**
     * Receives json formated request from Bolt,
     * containing cart identifier and the address entered in the checkout popup.
     * Responds with available shipping options and calculated taxes
     * for the cart and address specified.
     */
    public function indexAction() 
    {

        try {
            $hmac_header = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json);

            //Mage::log('SHIPPING AND TAX REQUEST: ' . json_encode($request_data, JSON_PRETTY_PRINT), null, 'shipping_and_tax.log');

            /** @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');
            if (!$boltHelper->verify_hook($request_json, $hmac_header)) {
                throw new Exception("Failed HMAC Authentication");
            }

            $shipping_address = $request_data->shipping_address;

            $region = Mage::getModel('directory/region')->loadByName($shipping_address->region, $shipping_address->country_code)->getCode();

            $address_data = array(
                'email' => $shipping_address->email,
                'firstname' => $shipping_address->first_name,
                'lastname' => $shipping_address->last_name,
                'street' => $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
                'company' => $shipping_address->company,
                'city' => $shipping_address->locality,
                'region' => $region,
                'postcode' => $shipping_address->postal_code,
                'country_id' => $shipping_address->country_code,
                'telephone' => $shipping_address->phone
            );

            $display_id = $request_data->cart->display_id;

            /** @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')
                ->getCollection()
                ->addFieldToFilter('reserved_order_id', $display_id)
                ->getFirstItem();

            /***********************/
            # Set session quote to real customer quote
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($quote->getId());
            /**************/

            if ($quote->getCustomerId()) {
                $customer = Mage::getModel("customer/customer")->load($quote->getCustomerId());
                $address = $customer->getPrimaryShippingAddress();

                if (!$address) {
                    $address = Mage::getModel('customer/address');

                    $address->setCustomerId($customer->getId())
                        ->setCustomer($customer)
                        ->setIsDefaultShipping('1')
                        ->setSaveInAddressBook('1')
                        ->save();


                    $address->addData($address_data);
                    $address->save();

                    $customer->addAddress($address)
                        ->setDefaultShippingg($address->getId())
                        ->save();
                }
            }

            $quote->removeAllAddresses();
            $quote->save();
            $quote->getShippingAddress()->addData($address_data)->save();

            $billingAddress = $quote->getBillingAddress();

            $quote->getBillingAddress()->addData(
                array(
                'email' => $billingAddress->getEmail() ?: $shipping_address->email,
                'firstname' => $billingAddress->getFirstname() ?: $shipping_address->first_name,
                'lastname' => $billingAddress->getLastname() ?: $shipping_address->last_name,
                'street' => implode("\n", $billingAddress->getStreet()) ?: $shipping_address->street_address1 . ($shipping_address->street_address2 ? "\n" . $shipping_address->street_address2 : ''),
                'company' => $billingAddress->getCompany() ?: $shipping_address->company,
                'city' => $billingAddress->getCity() ?: $shipping_address->locality,
                'region' => $billingAddress->getRegion() ?: $region,
                'postcode' => $billingAddress->getPostcode() ?: $shipping_address->postal_code,
                'country_id' => $billingAddress->getCountryId() ?: $shipping_address->country_code,
                'telephone' => $billingAddress->getTelephone() ?: $shipping_address->phone
                )
            )->save();

            ////////////////////////////////////////////////////////////////////////////////////////
            // Check session cache for estimate.  If the shipping city or postcode, and the country code match,
            // then use the cached version.  Otherwise, we have to do another calculation
            ////////////////////////////////////////////////////////////////////////////////////////
            $cacheKeyLocation = $quote->getId() . '_' . $address_data['country_id'];
            $prefetchCacheKey = $this->generateCacheKey($quote, $address_data);
            $cached_address = unserialize(Mage::app()->getCache()->load('quote_location_' . $cacheKeyLocation));

            if ($cached_address && ((strtoupper($cached_address["city"]) == strtoupper($address_data["city"])) || ($cached_address["postcode"] == $address_data["postcode"])) && ($cached_address["country_id"] == $address_data["country_id"])) {
                //Mage::log('Using cached address: '.var_export($cached_address, true), null, 'shipping_and_tax.log');
                $response = unserialize(Mage::app()->getCache()->load($prefetchCacheKey));
            } else {
                //Mage::log('Generating address from quote', null, 'shipping_and_tax.log');
                //Mage::log('Live address: '.var_export($address_data, true), null, 'shipping_and_tax.log');
                $response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
            }

            ////////////////////////////////////////////////////////////////////////////////////////

            $response = json_encode($response, JSON_PRETTY_PRINT);

            //Mage::log('SHIPPING AND TAX RESPONSE: ' . $response, null, 'shipping_and_tax.log');

            $this->getResponse()->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            Mage::helper('boltpay/api')->setResponseContextHeaders();

            $this->getResponse()->setBody($response);
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * Prefetches and stores the shipping and tax estimate and stores it in the session
     *
     * This expects to receive JSON with the values:
     *        city, region_code, zip_code, country_code
     */
    function prefetchEstimateAction()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $shippingAddress = $quote->getShippingAddress();

        $countItems = $quote->getItemsCollection()->getSize();
        $countryId = $shippingAddress->getCountryId();
        if ($shippingAddress && empty($countryId) && $countItems)
        {
            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json);

            $address_data = array(
                'city'          => $request_data->city,
                'region'        => $request_data->region_code,
                'region_name'   => $request_data->region_name,
                'postcode'      => $request_data->zip_code,
                'country_id'    => $request_data->country_code
            );

            /** @var Mage_Directory_Model_Country $countryModel */
            $countryModel = Mage::getModel('directory/country');
            $countryObj = $countryModel->load($address_data['country_id']);
            $isRegionAvailable = ($countryObj->getRegionCollection()->getSize() > 0);
            if (!$isRegionAvailable) {
                // If country does not have region options for dropdown.
                $address_data['region'] = $address_data['region_name'];
            }

            /** @var Mage_Directory_Model_Region $regionModel */
            $regionModel = Mage::getModel('directory/region');
            $regionObj= $regionModel->loadByCode($address_data['region'], $address_data['country_id']);
            $address_data['region_id'] = ($regionObj) ? $regionObj->getId() : null;

            ////////////////////////////////////////////////////////////////////////
            // Clear previously set estimates.  This helps if this
            // process fails or is aborted, which will force the actual index action
            // to get fresh data instead of reading from the session cache
            ////////////////////////////////////////////////////////////////////////
            $cacheKeyLocation = $quote->getId() . '_' . $address_data['country_id'];
            $prefetchCacheKey = $this->generateCacheKey($quote, $address_data);
            Mage::app()->getCache()->remove('quote_location_' . $cacheKeyLocation);
            Mage::app()->getCache()->remove($prefetchCacheKey);
            ////////////////////////////////////////////////////////////////////////

            $quote->getShippingAddress()->addData($address_data);
            $quote->getBillingAddress()->addData($address_data);
            try {
                $estimate_response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
            } catch (Exception $e) {
                $estimate_response = null;
            }

            $lifeTime = 600; //10 minutes
            Mage::app()->getCache()->save(
                serialize($address_data),
                'quote_location_' . $cacheKeyLocation,
                array('BOLT_QUOTE_LOCATION'),
                $lifeTime
            );

            Mage::app()->getCache()->save(
                serialize($estimate_response),
                $prefetchCacheKey,
                array('BOLT_PREFETCH_SHIPPING_AND_TAX'),
                $lifeTime
            );
        } else {
            $address_data = array();
        }

        $response = Mage::helper('core')->jsonEncode(array('address_data' => $address_data));
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHeader('X-Bolt-Cache-Hit', 'HIT');
        $this->getResponse()->setBody($response);
    }

    /**
     * @param        $quote Mage_Sales_Model_Quote
     * @param        $addressData
     * @return string
     */
    public function generateCacheKey($quote, $addressData)
    {
        /* @var $shippingAddress Mage_Sales_Model_Quote_Address */
        $shippingAddress = $quote->getShippingAddress();
        $itemIDs = $quote->getItemsCollection()->getAllIds();
        $quoteTotalAmount = round($quote->getGrandTotal()*100);
        $isCustomerGuest = $quote->getCustomerIsGuest();
        $discountCode = str_replace(' ', '', $shippingAddress->getDiscountDescription());

        $key = $quote->getId() . '_' . $isCustomerGuest . '_' . $discountCode . '_' .$quoteTotalAmount;

        if (!$shippingAddress->getCountryId() && !$shippingAddress->getPostcode()) {
            $countryCode = $addressData['country_id'];
            $region = $addressData['region'];
            $postCode = str_replace(' ', '', $addressData['postcode']);
        } else {
            $countryCode = $shippingAddress->getCountryId();
            $region = $shippingAddress->getRegionCode();
            $postCode = str_replace(' ', '', $shippingAddress->getPostcode());
        }

        $key .= ($countryCode) ? '_' . $countryCode : '';
        $key .= ($region) ? '_' . $region : '';
        $key .= ($postCode) ? '_' . $postCode : '';

        foreach ($itemIDs as $id) {
            $key .= '_' .$id;
        }

        return 'BOLT_PREFETCH_' . md5('quote_shipping_and_tax_estimate_' . $key);
    }
}
