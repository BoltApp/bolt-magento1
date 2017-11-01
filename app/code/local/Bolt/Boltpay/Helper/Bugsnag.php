<?php

require_once(Mage::getBaseDir('lib') . DS .  'Boltpay/Guzzle/autoloader.php');
require_once(Mage::getBaseDir('lib') . DS .  'Boltpay/Bugsnag/autoloader.php');

class Bolt_Boltpay_Helper_Bugsnag extends Mage_Core_Helper_Abstract {

    private $apiKey = "811cd1efe8b48df719c5ad0379e3ae75";
    private $mode = "development";

    private $bugsnag;

    public function getBugsnag() {

        if (empty($this->bugsnag)) {

            $bugsnag = \Bugsnag\Client::make($this->apiKey);
            $bugsnag->setErrorReportingLevel(E_ERROR);
            $bugsnag->setReleaseStage($this->mode);
            $bugsnag->setBatchSending(true);
            Bugsnag\Handler::register($bugsnag);

            $this->bugsnag = $bugsnag;
        }
        return $this->bugsnag;
    }
}
