<?php

require_once(Mage::getBaseDir('lib') . DS .  'Boltpay/Bugsnag/Autoload.php');

class Bolt_Boltpay_Helper_Bugsnag extends Mage_Core_Helper_Abstract {

    private $apiKey = "811cd1efe8b48df719c5ad0379e3ae75";

    private $bugsnag;

    private $metaData = array("breadcrumbs_" => array ());

    private $boltTraceId;

    public function addMetaData($metaData) {
        $this->metaData['breadcrumbs_'] = array_merge($metaData, $this->metaData['breadcrumbs_']);
    }

    public function test() {
        $this->notifyError('ErrorType', 'Test Error');
        $this->notifyException(new Exception("Test Exception"));
    }

    private function getBugsnag() {

        if (!$this->bugsnag) {

            $bugsnag = new Bugsnag_Client($this->apiKey);

            $bugsnag->setErrorReportingLevel(E_ERROR);
            $bugsnag->setReleaseStage(Mage::getStoreConfig('payment/boltpay/test') ? 'development' : 'production');
            $bugsnag->setBatchSending(true);
            $bugsnag->setBeforeNotifyFunction(array($this, 'beforeNotifyFunction'));

            $this->bugsnag = $bugsnag;
        }
        return $this->bugsnag;
    }

    public function beforeNotifyFunction($error) {
        $this->addDefaultMetaData();

        if (count($this->metaData['breadcrumbs_'])) {
            $error->setMetaData($this->metaData);
        }
    }

    protected function addDefaultMetaData() {
        $this->addTraceIdMetaData();
    }

    protected function addTraceIdMetaData() {
        $traceId = $this->getBoltTraceId();

        if(!empty($traceId) && !array_key_exists('bolt_trace_id', $this->metaData)) {
            $this->addMetaData(array(
                "bolt_trace_id" => $traceId
            ));
        }
    }

    public function notifyException($throwable, array $metaData = array(), $severity = null) {
        $exception = new Exception($throwable->getMessage()."\n".json_encode(static::getContextInfo()), 0, $throwable );
        $this->getBugsnag()->notifyException($exception, $metaData, $severity);
    }

    public function notifyError($name, $message, array $metaData = array(), $severity = null) {
        $this->getBugsnag()->notifyError($name, $message."\n".json_encode(static::getContextInfo()), $this->$metaData, $severity );
    }

    public static function getContextInfo() {

        $version_element =  Mage::getConfig()->getModuleConfig("Bolt_Boltpay")->xpath("version");
        $version = (string)$version_element[0];
        $request_body = file_get_contents('php://input');

        return array(
                "Requested-URL" => $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'],
                "Magento-Version" => Mage::getVersion(),
                "Bolt-Plugin-Version" => $version,
                "Request-Method" => $_SERVER['REQUEST_METHOD'],
                "Request-Body" => $request_body,
                "Time" => date("D M j, Y - G:i:s T")
        ) + static::getRequestHeaders();
    }

    private static function getRequestHeaders() {
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

    public function setBoltTraceId($traceId) {
        $this->boltTraceId = $traceId;
    }

    protected function getBoltTraceId() {
        if(isset($this->boltTraceId)) {
            return $this->boltTraceId;
        }

        $traceId = $_SERVER['HTTP_X_BOLT_TRACE_ID'];

        if(!empty($traceId)) {
            return $traceId;
        }

        return null;
    }
}
