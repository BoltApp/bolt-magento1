<?php
/**
 * Bolt PHP library
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License (MIT)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category   Bolt
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    https://opensource.org/licenses/MIT  MIT License (MIT)
 */

require_once(Mage::getBaseDir('lib') . DS.'Boltpay/Psr/autoload.php');
require_once(Mage::getBaseDir('lib') . DS.'Boltpay/GuzzleHttp/autoload.php');

/**
 * Class Boltpay_Guzzle_ApiClient
 */
class Boltpay_Guzzle_ApiClient
{
    const REQUEST_TYPE_GET = 'GET';
    const REQUEST_TYPE_POST = 'POST';

    /**
     * @param $additional
     * @return \GuzzleHttp\Client
     */
    private function getApiClient($additional)
    {
        $headers = array(
            'Content-Type' => 'application/json',
        );
        $headers = array_merge($headers, $additional);

        return new \GuzzleHttp\Client(['headers' => $headers]);
    }

    /**
     * @param $url
     * @param $jsonData
     * @param array $additional
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function post($url, $jsonData, $additional = array())
    {
        $client = $this->getApiClient($additional);
        return $client->request(self::REQUEST_TYPE_POST, $url, ['body' => $jsonData]);
    }

    /**
     * @param $url
     * @param array $additional
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function get($url, $additional = array())
    {
        $client = $this->getApiClient($additional);
        return $client->request(self::REQUEST_TYPE_GET, $url);
    }
}





