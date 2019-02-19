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
    /**
     * @var Mage_Core_Model_Cache  The Magento cache where the shipping and tax estimate is stored
     */
    protected $_cache;

    /**
     * @var Bolt_Boltpay_Model_ShippingAndTax  Object that performs shipping and tax business logic
     */
    protected $_shippingAndTaxModel;

    /**
     * @var string  The request body of the post made to this controller, expected to be in JSON
     */
    protected $_requestJSON;

    /**
     * @var Bolt_Boltpay_Helper_Api  Helper for Bolt API funcitionality
     */
    protected $_boltApiHelper;

    /**
     * Initializes Controller member variables
     */
    protected function _construct()
    {
        $this->_cache = Mage::app()->getCache();
        $this->_shippingAndTaxModel = Mage::getModel("boltpay/shippingAndTax");
        $this->_requestJSON = file_get_contents('php://input');
        $this->_boltApiHelper = Mage::helper('boltpay/api');
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

            $requestData = json_decode($this->_requestJSON);

            if (!$this->_boltApiHelper->verify_hook($this->_requestJSON, $hmacHeader)) {
                throw new Exception(Mage::helper('boltpay')->__("Failed HMAC Authentication"));
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

            ////////////////////////////////////////////////////////////////////////////////
            /// Apply shipping address with validation checks
            ////////////////////////////////////////////////////////////////////////////////
            $shippingAddress = $requestData->shipping_address;
            $addressErrorDetails = array();

            if (
                !$this->_shippingAndTaxModel->isPOBoxAllowed()
                && $this->_shippingAndTaxModel->doesAddressContainPOBox($shippingAddress->street_address1, $shippingAddress->street_address2)
            ) {
                $addressErrorDetails = array('code' => 6101, 'message' => Mage::helper('boltpay')->__('Address with P.O. Box is not allowed.'));
            } else {
                $addressData = $this->_shippingAndTaxModel->applyShippingAddressToQuote($quote, $shippingAddress);
                $magentoAddressErrors = $quote->getShippingAddress()->validate();

                if (is_array($magentoAddressErrors)) {
                    $addressErrorDetails = array('code' => 6103, 'message' => $magentoAddressErrors[0]);
                }
            }

            if ($addressErrorDetails) {
                return $this->getResponse()
                    ->clearAllHeaders()
                    ->setHttpResponseCode(403)
                    ->setBody(json_encode(array('status' => 'failure','error' => $addressErrorDetails)));
            }
            ////////////////////////////////////////////////////////////////////////////////


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

            $this->getResponse()->clearAllHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('X-Nonce', rand(100000000, 999999999), true);

            $this->getResponse()->setHeader('X-Bolt-Cache-Hit', $cacheBoltHeader);

            $this->_boltApiHelper->setResponseContextHeaders();

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
            $this->getResponse()->clearAllHeaders()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody("{}");
            return;
        }

        $requiredAddressFields = array('city'=>'','region'=>'','region_id'=>'','postcode'=>'','country_id'=>'');

        $filteredShippingAddress = array_intersect_key(
            $quote->getShippingAddress()->getData(),
            $requiredAddressFields
        );

        $cachedIdentifier = $this->getEstimateCacheIdentifier($quote, $filteredShippingAddress);
        $estimate = $this->_cache->load($cachedIdentifier);

        if ($estimate) {
            $addressData = $filteredShippingAddress;
        } else {
            $addressData = count($filteredShippingAddress) === count($requiredAddressFields)
                ? $filteredShippingAddress
                : array_merge(array_filter($this->getGeoIpAddress()), array_filter($filteredShippingAddress))
            ;

            $quote->getShippingAddress()->addData($addressData);
            $quote->getBillingAddress()->addData($addressData);

            try {
                $estimateResponse = $this->_shippingAndTaxModel->getShippingAndTaxEstimate($quote);
                $cacheIdentifier = $this->getEstimateCacheIdentifier($quote, $addressData);
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
        $this->getResponse()->clearAllHeaders()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($response);
    }


    /**
     * Caches the shipping and tax estimate
     *
     * @param array    $estimate        shipping and tax estimates to be returned to Bolt
     * @param string   $quoteCacheKey   unique key identifying the quote whose estimate is cached
     * @param int      $lifeTime        duration the the cached value should remain in seconds
     */
    protected function cacheShippingAndTaxEstimate($estimate, $quoteCacheKey, $lifeTime = 600)
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
    protected function getGeoIpAddress()
    {
        $requestData = json_decode($this->_requestJSON);

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
     * Generates caching key for  the shipping and tax estimate based on cart price,
     * cart content, customer, tax class, applied rules and discounts, and when provided,
     * country_id, postcode, region, region_id, and city
     *
     * @param Mage_Sales_Model_Quote    $quote          Quote containing cart content and price info
     * @param array                     $addressData    optionally provided address data
     *
     * @return string   The uniquely identifying key calculated from the provided data
     */
    public function getEstimateCacheIdentifier($quote, $addressData)
    {
        $cacheIdentifier = $quote->getId() . '_subtotal-' . round($quote->getBaseSubtotalWithDiscount()*100);

        $cacheIdentifier .= '_customer-' . ($quote->getCustomerId() ?: 0);

        $cacheIdentifier .= '_tax-class-' . ($quote->getCustomerTaxClassId() ?: 0);

        if (isset($addressData['country_id'])) {
            $cacheIdentifier .= '_country-id-' . $addressData['country_id'];
        }

        if (isset($addressData['postcode'])) {
            $cacheIdentifier .= '_postcode-' . $addressData['postcode'];
        }

        if (isset($addressData['city'])) {
            $cacheIdentifier .= '_city-' . $addressData['city'];
        }

        if (isset($addressData['region'])) {
            $cacheIdentifier .= '_region-' . $addressData['region'];
        }

        if (isset($addressData['region_id'])) {
            $cacheIdentifier .= '_region-id-' . $addressData['region_id'];
        }

        // include products in cache key
        foreach($quote->getAllVisibleItems() as $item) {
            $cacheIdentifier .= '_item-'.$item->getProductId().'-quantity-'.$item->getQty();
        }

        // include any discounts or gift card rules because they may affect shipping
        $cacheIdentifier .= '_applied-rules-'.json_encode($quote->getAppliedRuleIds());

        return md5($cacheIdentifier);
    }
}
