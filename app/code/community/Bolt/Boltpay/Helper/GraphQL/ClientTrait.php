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
 * @copyright  Copyright (c) 2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
use Bolt_Boltpay_Helper_GraphQL_Constants as GraphQLConstants;


/**
 * Trait Bolt_Boltpay_Helper_GraphQL_ClientTrait
 *
 * Provides utility methods related to the Bolt GraphQL API:
 *
 */
trait Bolt_Boltpay_Helper_GraphQL_ClientTrait
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
    private function makeGQLCall($query, $operation, $variables)
    {
        try {

            $gqlRequest = array(
                "operationName" => $operation,
                "variables" => $variables,
                "query" => $query
            );

            $requestData = json_encode($gqlRequest, JSON_UNESCAPED_SLASHES);

            $apiURL = $url = $this->getApiUrl() . GraphQLConstants::MERCHANT_API_GQL_ENDPOINT;

            $apiKey = Mage::getStoreConfig('payment/boltpay/api_key');

            $headerInfo = $this->constructRequestHeaders($requestData, $apiKey);

            $this->addMetaData(array('BOLT API REQUEST' => array('header' => $headerInfo)));
            $this->addMetaData(array('BOLT API REQUEST' => array('data' => $requestData)), true);

            $response = (string)$this->getApiClient()->post($apiURL, $requestData, $headerInfo)->getBody();
            $resultJSON = json_decode($response);

            $this->addMetaData(array('BOLT API RESPONSE' => array('api-response' => $resultJSON)), true);
            Mage::getModel('boltpay/payment')->debugData($resultJSON);

            return $resultJSON;
        } catch (\Exception $e) {
            $this->notifyException($e);
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * This Method makes a call to Bolt and returns the feature switches and their values for this server with
     * its current version and the current merchant in question.
     *
     * @return mixed
     */
    public function getFeatureSwitches()
    {
        $res = $this->makeGQLCall(GraphQLConstants::GET_FEATURE_SWITCHES_QUERY, GraphQLConstants::GET_FEATURE_SWITCHES_OPERATION, array(
            "type" => GraphQLConstants::PLUGIN_TYPE,
            "version" => static::getBoltPluginVersion(),
        ));

        return $res;
    }
}
