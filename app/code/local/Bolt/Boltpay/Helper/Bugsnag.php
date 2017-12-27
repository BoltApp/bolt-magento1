<?php

require_once(Mage::getBaseDir('lib') . DS .  'Boltpay/Bugsnag/Autoload.php');

class Bolt_Boltpay_Helper_Bugsnag extends Mage_Core_Helper_Abstract {

    private $apiKey = "811cd1efe8b48df719c5ad0379e3ae75";
    private $mode = "development";

    private $bugsnag;

    private $metaData = array("breadcrumbs_" => array ());

    public function addMetaData($metaData) {
        $this->metaData['breadcrumbs_'] = array_merge($metaData, $this->metaData['breadcrumbs_']);
    }

    public function test() {
        $this->getBugsnag()->notifyError('ErrorType', 'Test Error');
    }

    private function getBugsnag() {

        if (!$this->bugsnag) {

            $bugsnag = new Bugsnag_Client($this->apiKey);

            $bugsnag->setErrorReportingLevel(E_ERROR);
            $bugsnag->setReleaseStage($this->mode);
            $bugsnag->setBatchSending(true);
            $bugsnag->setBeforeNotifyFunction(array($this, 'beforeNotifyFunction'));

            $this->bugsnag = $bugsnag;
        }
        return $this->bugsnag;
    }

    public function beforeNotifyFunction($error) {
        if (count($this->metaData['breadcrumbs_'])) {
            $error->setMetaData($this->metaData);
        }
    }

    public function notifyException($throwable, array $metaData = null, $severity = null) {
        $this->getBugsnag()->notifyException($throwable, $metaData, $severity);
    }

    public function notifyError($name, $message, array $metaData = null, $severity = null) {
        $this->getBugsnag()->notifyError($name, $message, $metaData, $severity );
    }
}
