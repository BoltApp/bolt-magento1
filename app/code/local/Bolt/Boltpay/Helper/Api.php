<?php

class Bolt_Boltpay_Helper_Api extends Bolt_Boltpay_Helper_Data {
    const API_URL_TEST = 'https://api-staging.boltapp.com/';
    const API_URL_PROD = 'https://api.boltapp.com/';

    public function transmit($command, $data, $object='merchant', $type='transactions') {
        $url = $this->getApiUrl() . 'v1/';

        Mage::log(sprintf("Making an API call to %s", $command), null, 'bolt.log');
        if($command == 'order') {
            $url .= $command;
        } elseif($command == 'sign') {
            $url .= $object . '/' . $command;
        } else {
            $url .= $object . '/' . $type . '/' . $command;
        }

        $ch = curl_init($url);
        $params = "";
        if ($data != null) {
            $params = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        if ($command == 'oauth' && $type == 'division') {
            $key = Mage::getStoreConfig('payment/boltpay/merchant_key');
        } elseif ($command == 'sign') {
            $key = Mage::getStoreConfig('payment/boltpay/management_key');
        } else {
            $key = Mage::getStoreConfig('payment/boltpay/management_key');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'X-Merchant-Key: ' . Mage::helper('core')->decrypt($key),
            'X-Nonce: ' . rand(100000000, 99999999),
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($ch));
        curl_close($ch);
        Mage::getModel('boltpay/payment')->debugData($result);

        return $result;
    }

    public function handleErrorResponse($response) {
        if (is_null($response)) {
            Mage::throwException("BoltPay Gateway error: No response from Bolt. Please re-try again");
        } elseif (self::isResponseError($response)) {
            $message = sprintf("BoltPay Gateway error: %s", serialize($response));
            Mage::throwException($message);
        }

        return $response;
    }

  public function getApiUrl() {
      return Mage::getStoreConfig('payment/boltpay/test') ?
          self::API_URL_TEST :
          self::API_URL_PROD;
    }

  function isResponseError($response) {
      return array_key_exists('errors', $response) || array_key_exists('error_code', $response);
  }
}
