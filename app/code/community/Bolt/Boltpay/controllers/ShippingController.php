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
                throw new Exception(Mage::helper('boltpay')->__("Failed HMAC Authentication"));
            }

            $shippingAddress = $requestData->shipping_address;

            if (!$this->isPOBoxAllowed() && $this->doesAddressContainPOBox($shippingAddress->street_address1, $shippingAddress->street_address2)) {
                $errorDetails = array('code' => 6101, 'message' => Mage::helper('boltpay')->__('Address with P.O. Box is not allowed.'));
                return $this->getResponse()->setHttpResponseCode(403)
                    ->setBody(json_encode(array('status' => 'failure','error' => $errorDetails)));
            }

            $mockTransaction = (object) array("order" => $requestData );

            /** @var Bolt_Boltpay_Helper_Transaction $transactionHelper */
            $transactionHelper = Mage::helper('boltpay/transaction');
            $quoteId = $transactionHelper->getImmutableQuoteIdFromTransaction($mockTransaction);

            /* @var Mage_Sales_Model_Quote $quote */
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);

            /***********************/
            // Set session quote to real customer quote
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($quoteId);
            /**************/

            /* @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
            $shippingAndTaxModel = Mage::getModel('boltpay/shippingAndTax');
            $addressData = $shippingAndTaxModel->applyShippingAddressToQuote($quote, $shippingAddress);

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
                    $response = $shippingAndTaxModel->getShippingAndTaxEstimate($quote);
                    $cacheBoltHeader = 'MISS';
                }
            } else {
                Mage::helper('boltpay')->setCustomerSessionById($quote->getCustomerId());

                //Mage::log('Generating address from quote', null, 'shipping_and_tax.log');
                //Mage::log('Live address: '.var_export($address_data, true), null, 'shipping_and_tax.log');
                $response = $shippingAndTaxModel->getShippingAndTaxEstimate($quote);
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
            $metaData = array();
            if (isset($quote)){
                $metaData['quote'] = var_export($quote->debug(), true);
            }

            Mage::helper('boltpay/bugsnag')->notifyException($e, $metaData);
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

        if(!$quote->getId() || !$quote->getItemsCount()){
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody("{}");
            return;
        }

        $shippingAddressOriginal = $quote->getShippingAddress()->getData();

        $cacheIdentifier = $this->getPrefetchCacheIdentifier($quote, $shippingAddressOriginal);
        $addressCacheKey = $this->getAddressCacheKey($cacheIdentifier);

        if ($serialized = $this->_cache->load($addressCacheKey)) {
            $addressData = unserialize($serialized);
        } else {
            $geoLocationAddress = $this->getGeoIpAddress();
            $geoLocationAddress = $this->cleanEmptyAddressField($geoLocationAddress);

            // ----------^_^----------- //
            $shippingAddress = array(
                'city'       => @($shippingAddressOriginal['city']),
                'region'     => @($shippingAddressOriginal['region']),
                'region_id'  => @($shippingAddressOriginal['region_id']),
                'postcode'   => @($shippingAddressOriginal['postcode']),
                'country_id' => @($shippingAddressOriginal['country_id']),
            );
            unset($shippingAddressOriginal);

            $addressData = $this->mergeAddressData($geoLocationAddress, $shippingAddress);

            if(isset($addressData['postcode'])) {
                $cacheIdentifier = $this->getPrefetchCacheIdentifier($quote, $addressData);
                $this->saveAddressCache($addressData, $cacheIdentifier);

                $quote->getShippingAddress()->addData($addressData);
                $quote->getBillingAddress()->addData($addressData);

                try {
                    /** @var Bolt_Boltpay_Model_ShippingAndTax $shippingAndTaxModel */
                    $shippingAndTaxModel = Mage::getModel('boltpay/shippingAndTax');
                    $estimateResponse = $shippingAndTaxModel->getShippingAndTaxEstimate($quote);

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
            'city'          => isset($requestData->city) ?$requestData->city: '',
            'region'        => isset($requestData->region_code) ?$requestData->region_code: '',
            'region_name'   => isset($requestData->region_name) ?$requestData->region_name: '',
            'postcode'      => isset($requestData->zip_code) ?$requestData->zip_code: '',
            'country_id'    => isset($requestData->country_code) ?$requestData->country_code: ''
        );

        if(!empty($addressData['country_id'])){
            /** @var Mage_Directory_Model_Country $countryObj */
            $countryObj = Mage::getModel('directory/country')->loadByCode($addressData['country_id']);
            $isRegionAvailable = ($countryObj->getRegionCollection()->getSize() > 0);
    
            if (!$isRegionAvailable) {
                // If country does not have region options for dropdown.
                $addressData['region'] = $addressData['region_name'];
            }
            elseif(!empty($addressData['region'])){
                $regionModel = Mage::getModel('directory/region')->loadByCode($addressData['region'], $addressData['country_id']);
                if($regionModel){
                    $addressData['region'] = $regionModel->getName();
                    $addressData['region_id'] = $regionModel->getId();
                }
            }    
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

    /**
     * Returns a whether P.O. box addresses are allowed for this store
     *
     * @return bool     true if P.O. boxes are allowed.  Otherwise, false.
     */
    protected function isPOBoxAllowed()
    {
        return Mage::getStoreConfigFlag('payment/boltpay/allow_po_box');
    }

    /**
     * Checks wheather a P.O. Box exist in the addresses given
     *
     * @param $address1      The address to be checked for a P.O. Box matching string
     * @param $address2      If set, second address to be checked.  Useful for checking both shipping and billing in on call.
     *
     * @return bool     returns true only if any of the provided addresses contain a P.O. Box.  Otherwise, false
     */
    protected function doesAddressContainPOBox($address1, $address2 = null)
    {
        $poBoxRegex = '/^\s*((P(OST)?.?\s*(O(FF(ICE)?)?|B(IN|OX))+.?\s+(B(IN|OX))?)|B(IN|OX))/i';
        return (preg_match($poBoxRegex, $address1) || preg_match($poBoxRegex, $address2));
    }
}
