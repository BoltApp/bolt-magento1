<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Helper_GraphQLTrait
 */
class Bolt_Boltpay_Helper_GraphQLTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_GraphQLTrait Mocked instance of trait tested
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Boltpay_Guzzle_ApiClient
     */
    private $guzzleClientMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|\Psr\Http\Message\ResponseInterface
     */
    private $responseMock;

    /**
     * Configure test dependencies, called before each test
     */
    public function setUp()
    {
        Mage::app('default');
        $this->currentMock = $this->getMockBuilder('Bolt_Boltpay_Helper_GraphQLTrait')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(array('getApiClient', 'getContextInfo', 'addMetaData', 'getBoltPluginVersion', 'getApiUrl', 'notifyException', 'logException'))
            ->getMockForTrait();
        $this->currentMock->method('getBoltPluginVersion')->willReturn('2.3.0');
        $this->currentMock->method('getApiUrl')->willReturn('https://api.bolt.com/');


        $this->guzzleClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->disableOriginalConstructor()
            ->setMethods(array('post', 'getBody'))
            ->getMock();

        $this->responseMock = $this->getMockForAbstractClass(
            '\Psr\Http\Message\ResponseInterface',
            [],
            '',
            false,
            true,
            true,
            ['getBody']
        );

        $this->currentMock->method('getApiClient')->willReturn($this->guzzleClientMock);
    }

    /**
     * @test
     * that makeGqlCall returns api client response body
     *
     * @covers Bolt_Boltpay_Helper_GraphQLTrait::makeGqlCall
     */
    public function makeGqlCall_always_returnsApiClientResponse()
    {
        $operation = Boltpay_GraphQL_Constants::GET_FEATURE_SWITCHES_QUERY;
        $variables = Boltpay_GraphQL_Constants::GET_FEATURE_SWITCHES_OPERATION;
        $boltPluginVersion = \Bolt_Boltpay_Helper_ConfigTrait::getBoltPluginVersion();
        $query = array(
            "type" => Boltpay_GraphQL_Constants::PLUGIN_TYPE,
            "version" => $boltPluginVersion
        );

        $gqlRequest = array(
            "operationName" => $operation,
            "variables" => $variables,
            "query" => $query
        );
        $apiKey = 'stubbedApiKey_dasdasfasdasdasds';
        Bolt_Boltpay_TestHelper::stubConfigValue('payment/boltpay/api_key', $apiKey);
        $requestData = json_encode($gqlRequest, JSON_UNESCAPED_SLASHES);

        $headerInfo = Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            'constructRequestHeaders',
            array(
                $requestData,
                $apiKey
            )
        );
        
        unset($headerInfo['X-Nonce']);
        $apiURL = 'https://api.bolt.com/v2/merchant/api';
        
        $this->guzzleClientMock->expects($this->once())
            ->method('post')
            ->with($apiURL, $requestData, new PHPUnit_Framework_Constraint_ArraySubset($headerInfo))
            ->willReturnSelf();
        $stubedResponse = json_encode(
            array('status' => 200)
        );
        
        $this->guzzleClientMock->expects($this->once())->method('getBody')->willReturn($stubedResponse);

        $this->assertEquals(
            json_decode($stubedResponse),
            Bolt_Boltpay_TestHelper::callNonPublicFunction(
                $this->currentMock,
                'makeGqlCall',
                array($query, $operation, $variables)
            )
        );
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_GraphQLTrait::getFeatureSwitches
     */
    public function getFeatureSwitches_withoutException_returnsResponseWithValues()
    {
        $this->guzzleClientMock->expects($this->once())->method('post')->with(
            'https://api.bolt.com/v2/merchant/api',
            $this->callback(function ($jsonData) {
                $testJsonData = '{"operationName":"GetFeatureSwitches","variables":{"type":"MAGENTO_1","version":"2.3.0"},"query":"query GetFeatureSwitches($type: PluginType!, $version: String!) {\n  plugin(type: $type, version: $version) {\n    features {\n      name\n      value\n      defaultValue\n      rolloutPercentage\n    }\n  }\n}"}';
                return str_replace('\r\n', '\n', $jsonData) == $testJsonData;
            }),
            $this->anything()
        )->willReturn($this->responseMock);

        $this->currentMock->method('getContextInfo')->willReturn(array());

        $this->responseMock->expects($this->once())->method('getBody')
            ->willReturn('{"plugin": {"features": [{"name": "M1_BOLT_ENABLED", "value": true, "default_value": false, "rollout_percentage": 100}]}}');

        $response = $this->currentMock->getFeatureSwitches();
        $this->assertEquals("M1_BOLT_ENABLED", $response->plugin->features[0]->name);
    }

    /**
     * @test
     * that getFeatureSwitches throws exceptions if there is problem with a GraphQL call
     *
     * @covers Bolt_Boltpay_Helper_GraphQLTrait::getFeatureSwitches
     *
     * @expectedException GuzzleHttp\Exception\GuzzleException
     * @expectedExceptionMessage Test exception
     */
    public function getFeatureSwitches_uponGraphQLCallError_willThrowException()
    {
        $exception = new GuzzleHttp\Exception\RequestException('Test exception', null);
        $this->guzzleClientMock->expects($this->once())->method('post')->will($this->throwException($exception));

        $this->currentMock->getFeatureSwitches();
    }
}

