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
 * Trait Bolt_Boltpay_Helper_UrlTrait
 *
 * Defines URL related functions used by Bolt
 */
trait Bolt_Boltpay_Helper_UrlTrait {

    /**
     * @var string The Bolt sandbox url for the api
     */
    protected $apiUrlTest = 'https://api-sandbox.bolt.com/';

    /**
     * @var string The Bolt production url for the api
     */
    protected $apiUrlProd = 'https://api.bolt.com/';

    /**
     * @var string The Bolt sandbox url for the javascript
     */
    protected $jsUrlTest = 'https://connect-sandbox.bolt.com';

    /**
     * @var string The Bolt production url for the javascript
     */
    protected $jsUrlProd = 'https://connect.bolt.com';

    /**
     * @var string The Bolt sandbox url for the merchant
     */
    protected $merchantUrlSandbox = 'https://merchant-sandbox.bolt.com';

    /**
     * @var string The Bolt production url for the merchant
     */
    protected $merchantUrlProd = 'https://merchant.bolt.com';


    /**
     * Returns the Bolt merchant url, sandbox or production, depending on the store configuration.
     * @param null $storeId
     * @return string  the api url, sandbox or production
     */
    public function getBoltMerchantUrl($storeId = null)
    {
        return  Mage::getStoreConfigFlag('payment/boltpay/test', $storeId) ?
            $this->merchantUrlSandbox :
            $this->merchantUrlProd ;
    }

    /**
     * Returns the Bolt API url, sandbox or production, depending on the store configuration.
     * @param null $storeId
     * @return string  the api url, sandbox or production
     */
    public function getApiUrl($storeId = null)
    {
        return  Mage::getStoreConfigFlag('payment/boltpay/test', $storeId) ?
            $this->apiUrlTest :
            $this->apiUrlProd ;
    }

    /**
     * Returns the Bolt javascript url, sandbox or production, depending on the store configuration.
     * @param null $storeId
     * @return string  the api url, sandbox or production
     */
    public function getJsUrl($storeId = null)
    {
        return  Mage::getStoreConfigFlag('payment/boltpay/test', $storeId) ?
            $this->jsUrlTest :
            $this->jsUrlProd ;
    }

    /**
     * Gets the connect.js url depending on the sandbox state of the application
     *
     * @return string  Sandbox connect.js URL for Sanbox mode, otherwise production
     */
    public function getConnectJsUrl()
    {
        return $this->getJsUrl() . "/connect.js";
    }

    /**
     * Generates a magento URL by route and parameters,
     * auto-detecting if it should secure or insecure
     *
     * @param string $route     magento path specified in XML configs
     * @param array $params     directives for constructing the url
     * @param bool $isAdmin     if true, constructs a backend URL, otherwise frontend
     *
     * @return string   constructed url with appropriate protocol
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getMagentoUrl($route = '', $params = array(), $isAdmin = false ){
        if ($isAdmin) {
            if ((Mage::app()->getStore()->isAdminUrlSecure()) &&
                (Mage::app()->getRequest()->isSecure())) {
                $params["_secure"] = true;
            }
            return Mage::helper("adminhtml")->getUrl($route, $params);
        }

        if ((Mage::app()->getStore()->isFrontUrlSecure()) &&
            (Mage::app()->getRequest()->isSecure())) {
            $params["_secure"] = true;
        }
        return Mage::getUrl($route, $params);
    }
}