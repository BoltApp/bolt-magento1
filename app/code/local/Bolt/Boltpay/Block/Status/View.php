<?php
class Bolt_Boltpay_Block_Status_View extends Mage_Adminhtml_Block_Template {
    public function getBoltUserIdStatus() {
        $customer = Mage::getModel('customer/customer');
        $eavConfig = Mage::getModel('eav/config');

        $attributes = $eavConfig->getEntityAttributeCodes('customer', $customer);

        if (in_array('bolt_user_id', $attributes)) {
            return "OK";
        } else {
            return "ERROR (Bolt User Id not found in Customer object)";
        }
    }

    public function getBoltInstallStatus() {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql        = "SELECT * FROM core_resource WHERE code = 'bolt_boltpay_setup'";
        $rows       = $connection->fetchAll($sql);

        if (empty($rows)) {
            return "No bolt_boltpay_setup entry found";
        } else {
            return implode(",", $rows[0]);
        }
    }

    public function getConnectionStatusToBolt() {
        $boltUrl = str_replace("https://", "", Mage::helper('boltpay/api')->getApiUrl());
        $boltUrl = str_replace("/", "", $boltUrl);
        try {
          if ($sock = fsockopen($boltUrl, 443, $errNo, $errStr, 10)) {
            fclose($sock);
            return 'Connection to ' . $boltUrl . ": OK";
          } else {
            return 'Connection to ' . $boltUrl . ": FAIL. Error: " . $errstr;
          }
        } catch (Exception $e) {
          $error = array('error' => $e->getMessage());
          Mage::log($error, null, 'bolt.log');
          return 'Connection to ' . $boltUrl . ": ERROR. Error: " . $e;
        }
    }

    public function getMerchantCall() {
        $boltApi = Mage::helper('boltpay/api');
        $result = $boltApi->transmit('', null, 'merchant', '');
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

    public function getTransactionsEndpoint() {
        $boltApi = Mage::helper('boltpay/api');
        $result = $boltApi->transmit('ABCD-1234-EFGH', null, 'merchant', 'transactions');
        $resp = array();
        if ($result != null) {
            return json_encode($result, JSON_PRETTY_PRINT);
        }
        return "No response from Bolt Backend";
    }

    public function isCurlEnabled() {
        return function_exists('curl_version')
    };
}
}