<?php
// Activate Bolt and properly set its keys
include_once 'app/Mage.php';

Mage::init();

$api_key=Mage::getModel('core/encryption')->encrypt($argv[1]);
$signing_key=Mage::getModel('core/encryption')->encrypt($argv[2]);
$publishable_key_multipage=$argv[3];

Mage::getModel('core/config')->saveConfig('general/locale/code','en_US');
Mage::getModel('core/config')->saveConfig('currency/options/allow', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/base', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/default', "USD");
Mage::getModel('core/config')->saveConfig('payment/boltpay/active', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/test', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/api_key', $api_key);
Mage::getModel('core/config')->saveConfig('payment/boltpay/signing_key', $signing_key);
Mage::getModel('core/config')->saveConfig('payment/boltpay/publishable_key_multipage', $publishable_key_multipage);

Mage::app()->getCacheInstance()->flush(); 
