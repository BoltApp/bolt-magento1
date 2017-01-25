<?php

class Bolt_Boltpay_Helper_Api extends Bolt_Boltpay_Helper_Data {
    const API_URL_TEST = 'https://api-staging.boltapp.com/';
    const API_URL_PROD = 'https://api.boltapp.com/';

    public function transmit($command, $data, $object='merchant', $type='transactions') {
        $url = $this->getApiUrl() . 'v1/';

        Mage::log(sprintf("Making an API call to %s", $command), null, 'bolt.log');
        if($command == 'sign') {
            $url .= $object . '/' . $command;
        } elseif ($command == 'orders') {
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

  public function buildOrder($quote, $items) {
      $cart = $this->buildCart($quote, $items);
      return array(
          'cart' => $cart
      );
  }

  public function buildCart($quote, $items) {
      $billing = $quote->getBillingAddress();
      $shipping = $quote->getShippingAddress();
      $totals = $quote->getTotals();
      $shippingAddress = $quote->getShippingAddress();
      $productMediaConfig = Mage::getModel('catalog/product_media_config');

      $cart_submission_data = array(
          'order_reference' => $quote->getId(),
          'display_id' => $quote->getReservedOrderId(),
          'items' => array_map(function ($item) use ($quote, $productMediaConfig) {
              $image_url = $productMediaConfig->getMediaUrl($item->getProduct()->getThumbnail());
              $product = Mage::getModel('catalog/product')->load($item->getProductId());
              return array(
                  'reference' => $quote->getId(),
                  'image_url' => $image_url,
                  'name' => $item->getName(),
                  'description' => $product->getDescription(),
                  'total_amount' => floor($item->getPrice() * 100 * $item->getQty()),
                  'unit_price' => floor($item->getPrice() * 100),
                  'quantity' => $item->getQty()
              );
          }, $items),
      );

      if ($shippingAddress != null) {
          $tax = null;
          // WeltPixel has custom tax calculator which writes into a field field called taxjar_fee in shipping address
          if ($shippingAddress->getTaxjarFee() != 0) {
              $tax = $shippingAddress->getTaxjarFee();
          } elseif ($shippingAddress->getTaxAmount() != 0) {
              $tax = $shippingAddress->getTaxAmount();
          }

          if ($tax != null) {
              $cart_submission_data['tax_amount'] = floor($tax * 100);
          }
      }

      if (array_key_exists('discount', $totals)) {
          $cart_submission_data['discounts'] = array(array(
              'amount' => -1 * floor($totals['discount']->getValue() * 100),
              'description' => $totals['discount']->getTitle(),
          ));
      }

      if (array_key_exists('grand_total', $totals)) {
          $cart_submission_data['total_amount'] = floor($totals['grand_total']->getValue() * 100);
      }

      $cart_submission_data['currency'] = $quote->getQuoteCurrencyCode();

      $billingAddress = $billing->getStreet();
      $shippingAddress = $shipping->getStreet();

      $cart_submission_data['billing_address'] = array(
          'street_address1' => array_key_exists(0, $billingAddress) ? $billingAddress[0] : '',
          'street_address2' => array_key_exists(1, $billingAddress) ? $billingAddress[1] : '',
          'street_address3' => array_key_exists(2, $billingAddress) ? $billingAddress[2] : '',
          'street_address4' => array_key_exists(3, $billingAddress) ? $billingAddress[2] : '',
          'first_name' => $billing->getFirstname(),
          'last_name' => $billing->getLastname(),
          'locality' => $billing->getCity(),
          'region' => $billing->getRegion(),
          'postal_code' => $billing->getPostcode(),
          'country_code' => $billing->getCountry(),
          'phone' => $billing->getTelephone(),
          'email' => $billing->getEmail(),
      );

      $shipping_address = array(
          'street_address1' => array_key_exists(0, $shippingAddress) ? $shippingAddress[0] : '',
          'street_address2' => array_key_exists(1, $shippingAddress) ? $shippingAddress[1] : '',
          'street_address3' => array_key_exists(2, $shippingAddress) ? $shippingAddress[2] : '',
          'street_address4' => array_key_exists(3, $shippingAddress) ? $shippingAddress[2] : '',
          'first_name' => $shipping->getFirstname(),
          'last_name' => $shipping->getLastname(),
          'locality' => $shipping->getCity(),
          'region' => $shipping->getRegion(),
          'postal_code' => $shipping->getPostcode(),
          'country_code' => $shipping->getCountry(),
          'phone' => $shipping->getTelephone(),
          'email' => $shipping->getEmail(),
      );

      if (array_key_exists('shipping', $totals)) {
          $cart_submission_data['shipments'] = array(array(
              'shipping_address' => $shipping_address,
              'cost' => floor($totals['shipping']->getValue() * 100),
          ));
      }

      return $cart_submission_data;
  }

  function isResponseError($response) {
      return array_key_exists('errors', $response) || array_key_exists('error_code', $response);
  }
}
