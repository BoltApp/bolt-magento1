<?php
require_once('TestHelper.php');

class Bolt_Boltpay_Helper_GraphQLTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Helper_GraphQLTest Mocked instance of trait tested
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
            ->setMethods(array('getContextInfo', 'addMetaData', 'getBoltPluginVersion', 'getApiUrl', 'notifyException', 'logException'))
            ->getMockForTrait();
        $this->currentMock->method('getBoltPluginVersion')->willReturn('2.3.0');
        $this->currentMock->method('getApiUrl')->willReturn('https://api.bolt.com/');


        $this->guzzleClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->disableOriginalConstructor()
            ->setMethods(array('post'))
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

        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            $this->currentMock,
            'apiClient',
            $this->guzzleClientMock
        );
    }

    /**
     * @test
     */
    public function getFeatureSwitches_success()
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
            ->willReturn('{"data": {"features": {"name": "OK", "value": true, "default_value": false, "rollout_percentage": 100}}}');

        $response = $this->currentMock->getFeatureSwitches();
        $this->assertEquals("OK", $response->data->features->name);
    }

    /**
     * @test
     *
     * @expectedException Exception
     * @expectedExceptionMessage Test exception
     */
    public function getFeatureSwitches_fail()
    {
        $exception = new Exception('Test exception');
        $this->guzzleClientMock->expects($this->once())->method('post')->will($this->throwException($exception));

        $this->currentMock->expects($this->once())->method('notifyException')->with($exception);
        $this->currentMock->expects($this->once())->method('logException')->with($exception);

        $response = $this->currentMock->getFeatureSwitches();
        $this->assertEquals("OK", $response->data->features->name);
    }
}

