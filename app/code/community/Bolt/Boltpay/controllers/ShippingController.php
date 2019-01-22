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
     * @var Mage_Core_Model_Cache  The Magento cache where the shipping and tax estimate is stored
     */
    protected $_cache;

    /**
     * @var Bolt_Boltpay_Model_ShippingAndTax  Object that performs shipping and tax business logic
     */
    protected $_shippingAndTaxModel;

    /**
     * Initializes Controller member variables
     */
    protected function _construct()
    {
        $this->_cache = Mage::app()->getCache();
        $this->_shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
    }

    /**
     * Receives json formated request from Bolt,
     * containing cart identifier and the address entered in the checkout popup.
     * Responds with available shipping options and calculated taxes
     * for the cart and address specified.
     */
    public function indexAction()
    {
        try {
            set_time_limit(30);
            ignore_user_abort(true);

            $hmacHeader = $_SERVER['HTTP_X_BOLT_HMAC_SHA256'];

            $requestJson = file_get_contents('php://input');
            $requestData = json_decode($requestJson);

            /* @var Bolt_Boltpay_Helper_Api $boltHelper */
            $boltHelper = Mage::helper('boltpay/api');

            if (!$boltHelper->verify_hook($requestJson, $hmacHeader)) {
                throw new Exception(Mage::helper('boltpay')->__("Failed HMAC Authentication"));
            }

            $shippingAddress = $requestData->shipping_address;

            if (
                !$this->_shippingAndTaxModel->isPOBoxAllowed()
                && $this->_shippingAndTaxModel->doesAddressContainPOBox($shippingAddress->street_address1, $shippingAddress->street_address2)
            ) {
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

            Mage::helper('boltpay')->setCustomerSessionById($quote->getCustomerId());

            /***********************/
            // Set session quote to real customer quote
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($quoteId);
            /**************/

            $addressData = $this->_shippingAndTaxModel->applyShippingAddressToQuote($quote, $shippingAddress);

            ////////////////////////////////////////////////////////////////////////////////////////
            // Check session cache for estimate.  If the shipping city or postcode, and the country code match,
            // then use the cached version.  Otherwise, we have to do another calculation
            ////////////////////////////////////////////////////////////////////////////////////////
            $cachedIdentifier = $this->getEstimateCacheIdentifier($quote, $addressData);

            $estimate = unserialize($this->_cache->load($cachedIdentifier));

            if ($estimate) {
                $cacheBoltHeader = 'HIT';
            } else {
                //Mage::log('Generating address from quote', null, 'shipping_and_tax.log');
                //Mage::log('Live address: '.var_export($address_data, true), null, 'shipping_and_tax.log');
                $estimate = $this->_shippingAndTaxModel->getShippingAndTaxEstimate($quote);
                $this->cacheShippingAndTaxEstimate($estimate, $cachedIdentifier);
                $cacheBoltHeader = 'MISS';
            }
            ////////////////////////////////////////////////////////////////////////////////////////

            $responseJSON = json_encode($estimate, JSON_PRETTY_PRINT);

            //Mage::log('SHIPPING AND TAX RESPONSE: ' . $response, null, 'shipping_and_tax.log');

            $this->getResponse()->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            $this->getResponse()->setHeader('X-Bolt-Cache-Hit', $cacheBoltHeader);

            Mage::helper('boltpay/api')->setResponseContextHeaders();

            $this->getResponse()->setBody($responseJSON);
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
     * Initiates caching of the shipping and tax estimate if none exist base on the
     * quote address data and then as backup, GeoIP predicted address data.  Upon conclusion,
     * responds to the HTTP client with the address used to derive the estimate.  This may
     * be used to fill client forms and trigger frontend events upon address changes.
     */
    public function prefetchEstimateAction()
    {
        set_time_limit(30);
        ignore_user_abort(true);

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        if(!$quote->getId() || !$quote->getItemsCount()){
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody("{}");
            return;
        }

        $shippingAddress = array_intersect_key(
            $quote->getShippingAddress()->getData(),
            array('city'=>'','region'=>'','region_id'=>'','postcode'=>'','country_id'=>'')
        );

        $cachedIdentifier = $this->getEstimateCacheIdentifier($quote, $shippingAddress);
        $estimate = $this->_cache->load($cachedIdentifier);

        if ($estimate) {
            $addressData = $shippingAddress;
        } else {

            $addressData = array_merge(array_filter($this->getGeoIpAddress()), array_filter($shippingAddress));

            $cacheIdentifier = $this->getEstimateCacheIdentifier($quote, $addressData);

            $quote->getShippingAddress()->addData($addressData);
            $quote->getBillingAddress()->addData($addressData);

            try {
                $estimateResponse = $this->_shippingAndTaxModel->getShippingAndTaxEstimate($quote);
                $this->cacheShippingAndTaxEstimate($estimateResponse, $cacheIdentifier);
            } catch (Exception $e) {
                $metaData = array();
                $metaData['quote'] = var_export($quote->debug(), true);
                $metaData['address_data'] = var_export($addressData, true);
                $metaData['cache_key'] = $cachedIdentifier;
                $metaData['estimate'] = isset($estimateResponse) ? var_export($estimateResponse, true) : '';

                Mage::helper('boltpay/bugsnag')->notifyException(
                    $e,
                    $metaData,
                    "info"
                );
            }
        }

        $response = Mage::helper('core')->jsonEncode(array('address_data' => $addressData));
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($response);
    }


    /**
     * Caches the shipping and tax estimate
     *
     * @param array    $estimate        shipping and tax estimates to be returned to Bolt
     * @param string   $quoteCacheKey   unique key identifying the quote whose estimate is cached
     * @param int      $lifeTime        duration the the cached value should remain in seconds
     */
    public function cacheShippingAndTaxEstimate($estimate, $quoteCacheKey, $lifeTime = 600)
    {
        $this->_cache->save(
            serialize($estimate),
            $quoteCacheKey,
            array('BOLT_QUOTE_PREFETCH'),
            $lifeTime
        );
    }


    /**
     * Converts address data read from the request from ipstack format to Magento address format
     *
     * @return array    The address prediction identified ipstack in Magento format
     */
    private function getGeoIpAddress()
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
    
            if (!$countryObj->getRegionCollection()->getSize()) {
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
     * Caches the shipping and tax estimate based on cart price, cart content, customer,
     * tax class, and when provided, country_id, postcode, region, region_id, and city
     *
     * @param Mage_Sales_Model_Quote    $quote          Quote containing cart content and price info
     * @param array                     $addressData    optionally provided address data
     *
     * @return string   The uniquely identifying key calculated from the provided data
     */
    public function getEstimateCacheIdentifier($quote, $addressData)
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

        if (isset($addressData['city'])) {
            $cacheIdentifier .= '_' . $addressData['city'];
        }

        if (isset($addressData['region'])) {
            $cacheIdentifier .= '_' . $addressData['region'];
        }

        if (isset($addressData['region_id'])) {
            $cacheIdentifier .= '_' . $addressData['region_id'];
        }

        // include products in cache key
        foreach($quote->getAllVisibleItems() as $item) {
            $cacheIdentifier .= '_'.$item->getProductId().'_'.$item->getQty();
        }

        return md5($cacheIdentifier);
    }
}
