<?php

ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/../../app' . PATH_SEPARATOR . dirname(__FILE__));
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/../../app/code/local/Bolt/Boltpay' . PATH_SEPARATOR . dirname(__FILE__));
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/testsuite/Bolt/Boltpay');

error_reporting(E_ALL | E_STRICT);

//Set custom memory limit
ini_set('memory_limit', '512M');
//Include Magento libraries
require_once 'Mage.php';
////Start the Magento application
//Mage::app('default');
//Avoid issues "Headers already send"
session_start();