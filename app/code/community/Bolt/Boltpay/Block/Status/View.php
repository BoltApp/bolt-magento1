<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the Bolt extension
 * to a newer versions in the future. If you wish to customize this extension
 * for your needs please refer to http://www.magento.com for more information.
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2018 Bolt Financial, Inc (http://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Block_Status_View extends Mage_Adminhtml_Block_Template
{
    public function getBoltUserIdStatus() 
    {
        $customer = Mage::getModel('customer/customer');
        $eavConfig = Mage::getModel('eav/config');

        $attributes = $eavConfig->getEntityAttributeCodes('customer', $customer);

        if (in_array('bolt_user_id', $attributes)) {
            return "OK";
        } else {
            return "ERROR (Bolt User Id not found in Customer object)";
        }
    }

    public function getBoltInstallStatus() 
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
		$prefix = Mage::getConfig()->getTablePrefix();
        $sql        = "SELECT * FROM ".$prefix."core_resource WHERE code = 'bolt_boltpay_setup'";
        $rows       = $connection->fetchAll($sql);

        if (empty($rows)) {
            return "No bolt_boltpay_setup entry found";
        } else {
            return implode(",", $rows[0]);
        }
    }

    public function getConnectionStatusToBolt() 
    {
        $boltUrl = str_replace("https://", "", Mage::helper('boltpay/api')->getApiUrl());
        $boltUrl = str_replace("/", "", $boltUrl);
        try {
          if ($sock = fsockopen($boltUrl, 443, $errNo, $errStr, 10)) {
            fclose($sock);
            return 'Connection to ' . $boltUrl . ": OK";
          } else {
            return 'Connection to ' . $boltUrl . ": FAIL. Error: " . $errStr;
          }
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
          return 'Connection to ' . $boltUrl . ": ERROR. Error: " . $e;
        }
    }

    public function getSSLData() 
    {
        $ch = curl_init('https://www.howsmyssl.com/a/check');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($data);
        return var_dump($json);
    }

    public function getMerchantCall() 
    {
        $boltApi = Mage::helper('boltpay/api');
        try {
            $result = $boltApi->transmit('', null, 'merchant', '');
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            return 'Bolt call failed. Error has been logged to bolt.log';
        }
        
        $resp = array();
        if ($result != null) {
            if (strlen($result->description) != 0) {
                $resp['name'] = $result->description;
            }

            if (strlen($result->public_id) != 0) {
                $resp['public_id'] = $result->public_id;
            }

            if (strlen($result->support_phone) != 0) {
                $resp['support_phone'] = $result->support_phone;
            }

            if (strlen($result->support_email) != 0) {
                $resp['support_email'] = $result->support_email;
            }

            return json_encode($resp, JSON_PRETTY_PRINT);
        }

        return "No response from Bolt Backend";
    }

    public function getTransactionsEndpoint() 
    {
        $boltApi = Mage::helper('boltpay/api');
        try {
            $result = $boltApi->transmit('ABCD-1234-EFGH', null, 'merchant', 'transactions');
        } catch (Exception $e) {
            $error = array('error' => $e->getMessage());
            //Mage::log($error, null, 'bolt.log');
            Mage::helper('boltpay/bugsnag')->notifyException($e);
            return 'Bolt call failed. Error has been logged to bolt.log';
        }

        if ($result != null) {
            return json_encode($result, JSON_PRETTY_PRINT);
        }

        return "No response from Bolt Backend";
    }

    public function isCurlEnabled() 
    {
        if (function_exists('curl_version') > 0) {
            return var_dump(curl_version());
        };
        return "Curl version not found";
    }

    public function testCurl() 
    {
        $boltUrl = Mage::helper('boltpay/api')->getApiUrl() . "v1/merchant";
        $ch = curl_init($boltUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $dumpStr = var_dump(curl_getinfo($ch));
        curl_close($ch);
        return $dumpStr;
    }

}
