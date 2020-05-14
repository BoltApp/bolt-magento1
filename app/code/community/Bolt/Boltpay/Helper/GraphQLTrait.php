<?php

/**
 * Bolt magento plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Bolt
 * @package    Bolt_Boltpay
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Trait Bolt_Boltpay_Helper_GraphQLTrait
 *
 * Provides utility methods related to the Bolt GraphQL API:
 *
 */
trait Bolt_Boltpay_Helper_GraphQLTrait
{

    use Bolt_Boltpay_Helper_ApiTrait;

    /**
     * Make GraphQL call to Bolt server
     *
     * @param $query
     * @param $operation
     * @param $variables
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function makeGqlCall($query, $operation, $variables)
    {
        $gqlRequest = array(
            "operationName" => $operation,
            "variables" => $variables,
            "query" => $query
        );

        $requestData = json_encode($gqlRequest, JSON_UNESCAPED_SLASHES);

        $apiURL = $url = $this->getApiUrl() . Boltpay_GraphQL_Constants::MERCHANT_API_GQL_ENDPOINT;

        $apiKey = Mage::getStoreConfig('payment/boltpay/api_key');

        $headerInfo = $this->constructRequestHeaders($requestData, $apiKey);

        $this->addMetaData(array('BOLT API REQUEST' => array('header' => $headerInfo)));
        $this->addMetaData(array('BOLT API REQUEST' => array('data' => $requestData)), true);

        $response = (string)$this->getApiClient()->post($apiURL, $requestData, $headerInfo)->getBody();

        return json_decode($response);
    }

    /**
     * This Method makes a call to Bolt and returns the feature switches and their values for this server with
     * its current version and the current merchant in question.
     *
     * @return object
     * $result->plugin->features is array of features
     * Each feature is object with 4 fields:
     * string name
     * boolean value
     * boolean defaultValue
     * 0..100 rolloutPercentage
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getFeatureSwitches()
    {
        $res = $this->makeGqlCall(
            Boltpay_GraphQL_Constants::GET_FEATURE_SWITCHES_QUERY,
            Boltpay_GraphQL_Constants::GET_FEATURE_SWITCHES_OPERATION,
            array(
                "type" => Boltpay_GraphQL_Constants::PLUGIN_TYPE,
                "version" => $this->getBoltPluginVersion(),
                )
        );

        return $res;
    }
}
