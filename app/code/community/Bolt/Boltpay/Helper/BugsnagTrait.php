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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Trait Bolt_Boltpay_Helper_BugsnagTrait
 *
 * Adds Bugsnag functionality to Bolt
 */
trait Bolt_Boltpay_Helper_BugsnagTrait {

    private $apiKey = "811cd1efe8b48df719c5ad0379e3ae75";

    private $bugsnag;

    private $metaData = array("breadcrumbs_" => array ());

    private $boltTraceId;

    public function addBreadcrumb($metaData)
    {
        $this->metaData['breadcrumbs_'] = array_merge($metaData, $this->metaData['breadcrumbs_']);
    }

    public function test()
    {
        $this->notifyError('ErrorType', 'Test Error');
        $this->notifyException(new Exception("Test Exception"));
    }

    private function getBugsnag()
    {

        if (!$this->bugsnag) {
            $bugsnag = new Boltpay_Bugsnag_Client($this->apiKey);

            $bugsnag->setErrorReportingLevel(E_ERROR);

            if (isset($_SERVER['PHPUNIT_ENVIRONMENT']) && $_SERVER['PHPUNIT_ENVIRONMENT']) {
                $bugsnag->setReleaseStage('test');
            } else {
                $bugsnag->setReleaseStage(Mage::getStoreConfig('payment/boltpay/test') ? 'development' : 'production');
            }
            $bugsnag->setNotifyReleaseStages( array( 'development', 'production' ) );
            $bugsnag->setAppVersion(static::getBoltPluginVersion());
            $bugsnag->setBatchSending(true);
            $bugsnag->setBeforeNotifyFunction(array($this, 'beforeNotifyFunction'));

            $this->bugsnag = $bugsnag;
        }

        return $this->bugsnag;
    }

    public function beforeNotifyFunction($error)
    {
        $this->addDefaultMetaData();

        if (count($this->metaData['breadcrumbs_'])) {
            $error->setMetaData($this->metaData);
        }
    }

    protected function addDefaultMetaData()
    {
        $this->addTraceIdMetaData();
        $this->addStoreUrlMetaData();
    }

    protected function addStoreUrlMetaData()
    {
        $storeUrl = Mage::getBaseUrl();

        if(!empty($storeUrl) && !array_key_exists('store_url', $this->metaData)) {
            $this->addBreadcrumb(array('store_url' => $storeUrl));
        }
    }

    protected function addTraceIdMetaData()
    {
        $traceId = $this->getBoltTraceId();

        if(!empty($traceId) && !array_key_exists('bolt_trace_id', $this->metaData)) {
            $this->addBreadcrumb(
                array(
                    "bolt_trace_id" => $traceId
                )
            );
        }
    }

    public function notifyException($throwable, array $metaData = array(), $severity = null)
    {
        $exception = new Exception($throwable->getMessage()."\n".json_encode(static::getContextInfo()), 0, $throwable);
        $this->getBugsnag()->notifyException($exception, $metaData, $severity);
    }

    public function notifyError($name, $message, array $metaData = array(), $severity = null)
    {
        $this->getBugsnag()->notifyError($name, $message."\n".json_encode(static::getContextInfo()), $metaData, $severity);
    }

    public static function getContextInfo()
    {
        $version = static::getBoltPluginVersion();
        $requestBody = file_get_contents('php://input');

        return array(
                "Requested-URL" => $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'],
                "Magento-Version" => Mage::getVersion(),
                "Bolt-Plugin-Version" => $version,
                "Request-Method" => $_SERVER['REQUEST_METHOD'],
                "Request-Body" => $requestBody,
                "Time" => date("D M j, Y - G:i:s T")
            ) + static::getRequestHeaders();
    }

    protected static function getBoltPluginVersion() {
        $versionElm =  Mage::getConfig()->getModuleConfig("Bolt_Boltpay")->xpath("version");

        if(isset($versionElm[0])) {
            return (string)$versionElm[0];
        }

        return null;
    }

    private static function getRequestHeaders()
    {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }

            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }

        return $headers;
    }

    public function setBoltTraceId($traceId)
    {
        $this->boltTraceId = $traceId;
    }

    /**
     * The Bolt TraceID set in the header file
     *
     * @return string|null  it will be a string if it is set, otherwise null
     */
    public function getBoltTraceId()
    {
        return $this->boltTraceId ?: @$_SERVER['HTTP_X_BOLT_TRACE_ID'];
    }

    /* add metaData for to create new tab
     *
     * @param array $metaData
     * @param Boolean $merge
     *
     */
    public function addMetaData(array $metaData, $merge = false)
    {
        $this->getBugsnag()->setMetaData($metaData, $merge);
    }
}