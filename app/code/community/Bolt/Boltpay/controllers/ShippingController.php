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
    const CACHE_PREFETCH_ADDRESS_PREFIX = 'BOLT_PREFETCH_ADDRESS_';
    const CACHE_ESTIMATE_PREFIX = 'BOLT_PREFETCH_ESTIMATE_';

    /**
     * @var Mage_Core_Model_Cache
     */
    protected $_cache;

    /**
     * Receives json formated request from Bolt,
     * containing cart identifier and the address entered in the checkout popup.
     * Responds with available shipping options and calculated taxes
     * for the cart and address specified.
     */
    public function indexAction() 
    {
        try {
            $hmacHeader = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $requestJson = file_get_contents('php://input');
            $requestData = json_decode($requestJson);

            /* @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');
            if (!$boltHelper->verify_hook($requestJson, $hmacHeader)) {
                throw new Exception("Failed HMAC Authentication");
            }

            $shippingAddress = $requestData->shipping_address;

            $region = Mage::getModel('directory/region')->loadByName($shippingAddress->region, $shippingAddress->country_code)->getCode();

            $addressData = array(
                'email' => $shippingAddress->email,
                'firstname' => $shippingAddress->first_name,
                'lastname' => $shippingAddress->last_name,
                'street' => $shippingAddress->street_address1 . ($shippingAddress->street_address2 ? "\n" . $shippingAddress->street_address2 : ''),
                'company' => $shippingAddress->company,
                'city' => $shippingAddress->locality,
                'region' => $region,
                'postcode' => $shippingAddress->postal_code,
                'country_id' => $shippingAddress->country_code,
                'telephone' => $shippingAddress->phone
            );

            $quoteId = $requestData->cart->order_reference;
            /* @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);


            /***********************/
            // Set session quote to real customer quote
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($quoteId);
            /**************/

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
                'email' => $billingAddress->getEmail() ?: $shippingAddress->email,
                'firstname' => $billingAddress->getFirstname() ?: $shippingAddress->first_name,
                'lastname' => $billingAddress->getLastname() ?: $shippingAddress->last_name,
                'street' => implode("\n", $billingAddress->getStreet()) ?: $shippingAddress->street_address1 . ($shippingAddress->street_address2 ? "\n" . $shippingAddress->street_address2 : ''),
                'company' => $billingAddress->getCompany() ?: $shippingAddress->company,
                'city' => $billingAddress->getCity() ?: $shippingAddress->locality,
                'region' => $billingAddress->getRegion() ?: $region,
                'postcode' => $billingAddress->getPostcode() ?: $shippingAddress->postal_code,
                'country_id' => $billingAddress->getCountryId() ?: $shippingAddress->country_code,
                'telephone' => $billingAddress->getTelephone() ?: $shippingAddress->phone
                )
            )->save();

            ////////////////////////////////////////////////////////////////////////////////////////
            // Check session cache for estimate.  If the shipping city or postcode, and the country code match,
            // then use the cached version.  Otherwise, we have to do another calculation
            ////////////////////////////////////////////////////////////////////////////////////////
            $this->_cache = Mage::app()->getCache();
            $cachedIdentifier = $this->getPrefetchCacheIdentifier($quote, $addressData);
            $addressCacheKey  = $this->getAddressCacheKey($cachedIdentifier);
            $prefetchCacheKey = $this->getEstimateCacheKey($cachedIdentifier);

            $cachedAddress = unserialize($this->_cache->load($addressCacheKey));

            if ($cachedAddress &&
                ($cachedAddress['postcode'] == $addressData['postcode']) &&
                ($cachedAddress['country_id'] == $addressData['country_id'])
            ) {
                //Mage::log('Using cached address: '.var_export($cached_address, true), null, 'shipping_and_tax.log');
                $response = unserialize($this->_cache->load($prefetchCacheKey));
                $cacheBoltHeader = 'HIT';
                if (!$response) {
                    $response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
                    $cacheBoltHeader = 'MISS';
                }
            } else {
                //Mage::log('Generating address from quote', null, 'shipping_and_tax.log');
                //Mage::log('Live address: '.var_export($address_data, true), null, 'shipping_and_tax.log');
                $response = Mage::helper('boltpay/api')->getShippingAndTaxEstimate($quote);
                $cacheBoltHeader = 'MISS';
            }

            ////////////////////////////////////////////////////////////////////////////////////////

            $response = json_encode($response, JSON_PRETTY_PRINT);

            //Mage::log('SHIPPING AND TAX RESPONSE: ' . $response, null, 'shipping_and_tax.log');

            $this->getResponse()->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            $this->getResponse()->setHeader('X-Bolt-Cache-Hit', $cacheBoltHeader);

            Mage::helper('boltpay/api')->setResponseContextHeaders();

            $this->getResponse()->setBody($response);
        } catch (Exception $e) {
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            throw $e;
        }
    }

    /**
     * @return mixed
     * @throws Varien_Exception
     */
    public function prefetchEstimateAction()
    {
        $this->_cache = Mage::app()->getCache();

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $shippingAddressOriginal = $quote->getShippingAddress()->getData();

        $cacheIdentifier = $this->getPrefetchCacheIdentifier($quote, $shippingAddressOriginal);
        $addressCacheKey = $this->getAddressCacheKey($cacheIdentifier);

        if ($serialized = $this->_cache->load($addressCacheKey)) {
            $addressData = unserialize($serialized);
        } else {
            $geoLocationAddress = $this->getGeoIpAddress();
            $geoLocationAddress = $this->cleanEmptyAddressField($geoLocationAddress);

            // ----------^_^----------- //
            $shippingAddress = [
                'city'       => @$shippingAddressOriginal['city'],
                'region'     => @$shippingAddressOriginal['region'],
                'region_id'  => @$shippingAddressOriginal['region_id'] ? $shippingAddressOriginal['region_id'] : null,
                'postcode'   => @$shippingAddressOriginal['postcode'],
                'country_id' => @$shippingAddressOriginal['country_id'],
            ];
            unset($shippingAddressOriginal);

            $addressData = $this->mergeAddressData($geoLocationAddress, $shippingAddress);

            if($quote->getId() && $quote->getItemsCount() && @addressData['postcode']) {
                $cacheIdentifier = $this->getPrefetchCacheIdentifier($quote, $addressData);
                $this->saveAddressCache($addressData, $cacheIdentifier);

                $quote->getShippingAddress()->addData($addressData);
                $quote->getBillingAddress()->addData($addressData);

                try {
                    /** @var Bolt_Boltpay_Helper_Api $helper */
                    $helper = Mage::helper('boltpay/api');
                    $estimateResponse = $helper->getShippingAndTaxEstimate($quote);

                    $this->cacheShippingAndTaxEstimate($estimateResponse, $cacheIdentifier);
                } catch (Exception $e) {
                    $estimateResponse = null;
                }
            }
        }

        $response = Mage::helper('core')->jsonEncode(array('address_data' => $addressData));
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($response);
    }

    /**
     * @param array $geoAddress
     * @param array $shippingAddress
     * @return array
     */
    public function mergeAddressData($geoAddress = array(), $shippingAddress = array())
    {
        if (!count($geoAddress)) {
            return $shippingAddress;
        }

        if (!count($shippingAddress)) {
            return $geoAddress;
        }

        foreach ($shippingAddress as $key => $value) {
            if (isset($geoAddress[$key]) && empty($value)) {
                $shippingAddress[$key] = $geoAddress[$key];
            }
        }

        return $shippingAddress;
    }

    /**
     * @param     $estimate
     * @param     $cacheKey
     * @param int $lifeTime
     */
    public function cacheShippingAndTaxEstimate($estimate, $cacheKey, $lifeTime = 600)
    {
        $this->_cache->save(
            serialize($estimate),
            $this->getEstimateCacheKey($cacheKey),
            array('BOLT_QUOTE_PREFETCH'),
            $lifeTime
        );
    }

    /**
     * @param     $addressData
     * @param     $cacheKey
     * @param int $lifeTime
     */
    public function saveAddressCache($addressData, $cacheKey, $lifeTime = 3600)
    {
        $this->_cache->save(
            serialize($addressData),
            $this->getAddressCacheKey($cacheKey),
            array('BOLT_QUOTE_PREFETCH'),
            $lifeTime
        );
    }

    /**
     * @param $addressData
     * @return mixed
     */
    public function cleanEmptyAddressField($addressData)
    {
        foreach ($addressData as $key => $value) {
            if (empty($value)) {
                unset($addressData[$key]);
            }
        }

        return $addressData;
    }

    /**
     * @return array
     */
    public function getGeoIpAddress()
    {
        $requestJson = file_get_contents('php://input');
        $requestData = json_decode($requestJson);

        $addressData = array(
            'city'          => $requestData->city,
            'region'        => $requestData->region_code,
            'region_name'   => $requestData->region_name,
            'postcode'      => $requestData->zip_code,
            'country_id'    => $requestData->country_code
        );

        /** @var Mage_Directory_Model_Country $countryObj */
        $countryObj = Mage::getModel('directory/country')->load($addressData['country_id']);
        $isRegionAvailable = ($countryObj->getRegionCollection()->getSize() > 0);

        if (!$isRegionAvailable) {
            // If country does not have region options for dropdown.
            $addressData['region'] = $addressData['region_name'];
        }

        return $addressData;
    }

    /**
     * @param $cacheIdentifier
     * @return string
     */
    public function getAddressCacheKey($cacheIdentifier)
    {
        return self::CACHE_PREFETCH_ADDRESS_PREFIX . $cacheIdentifier;
    }

    /**
     * @param $cacheIdentifier
     * @return string
     */
    public function getEstimateCacheKey($cacheIdentifier)
    {
        return self::CACHE_ESTIMATE_PREFIX . $cacheIdentifier;
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @param $addressData array
     * @return string
     */
    public function getPrefetchCacheIdentifier($quote, $addressData)
    {
        $cacheIdentifier = $quote->getId() . '_' . round($quote->getGrandTotal()*100);

        $cacheIdentifier .= '_' . ($quote->getCustomerId() ?: 0);

        $cacheIdentifier .= '_' . ($quote->getCustomerTaxClassId() ?: 0);

        if (isset($addressData['country_id'])) {
            $cacheIdentifier .= '_' . $addressData['country_id'];
        }

        if (isset($addressData['postcode'])) {
            $cacheIdentifier .= '_' . $addressData['postcode'];
        }

        // include products in cache key
        foreach($quote->getAllVisibleItems() as $item) {
            $cacheIdentifier .= '_'.$item->getProductId().'_'.$item->getQty();
        }

        return md5($cacheIdentifier);
    }
}
