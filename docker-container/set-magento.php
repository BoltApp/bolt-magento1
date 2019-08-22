<?php
/* 
    Uses Magento's base library to activate Bolt and properly set its keys
*/

# Include Magento autoloader file
include_once 'app/Mage.php';

# Initialize Magento on our script
Mage::app();
Mage::app()->getCacheInstance()->flush(); 

# Encrypts the keys to store in the database
$api_key=Mage::getModel('core/encryption')->encrypt($argv[1]);
$signing_key=Mage::getModel('core/encryption')->encrypt($argv[2]);
$publishable_key_multipage=Mage::getModel('core/encryption')->encrypt($argv[3]);

# Sets all the configs needed for Bolt
Mage::getModel('core/config')->saveConfig('currency/options/allow', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/base', "USD");
Mage::getModel('core/config')->saveConfig('currency/options/default', "USD");
Mage::getModel('core/config')->saveConfig('payment/boltpay/active', 1);
Mage::getModel('core/config')->saveConfig('payment/boltpay/test', 1);
Mage::getModel('core/config')->saveConfig('payment/keys/api_key', $api_key);
Mage::getModel('core/config')->saveConfig('payment/keys/signing_key', $signing_key);
Mage::getModel('core/config')->saveConfig('payment/keys/publishable_key_multipage', $publishable_key_multipage);

# Flushes cache so that changes take place
Mage::app()->getCacheInstance()->flush(); 