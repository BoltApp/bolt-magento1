<?php

use GuzzleHttp\Psr7\Response;

class Bolt_Boltpay_Helper_ApiTraitTest extends PHPUnit_Framework_TestCase
{
    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }

    /**
     * @test
     * @group  Helper
     * @group  Trait
     * @group  HelperApiTrait
     * @covers Bolt_Boltpay_Helper_ApiTrait::getApiClient
     */
    public function getApiClient_successScenario()
    {
        $mock = $this->getMockForTrait(Bolt_Boltpay_Helper_ApiTrait::class);
        $this->assertEmpty(Bolt_Boltpay_TestHelper::getNonPublicProperty($mock, 'apiClient'));
        $result = $mock->getApiClient();
        $this->assertInstanceOf(Boltpay_Guzzle_ApiClient::class, $result);
        $property = Bolt_Boltpay_TestHelper::getNonPublicProperty($mock, 'apiClient');
        $this->assertEquals($property, $result);
    }

    /**
     * @test
     * @group        Helper
     * @group        Trait
     * @group        HelperApiTrait
     * @dataProvider transmitCases
     * @covers       Bolt_Boltpay_Helper_ApiTrait::transmit
     * @param array $case
     */
    public function transmit_successScenario(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_multipage', $case['publishable_key_multipage']);
        $this->app->getStore()->setConfig('payment/boltpay/api_key', $case['api_key']);
        $mock = $this->getMockForTrait(Bolt_Boltpay_Helper_ApiTrait::class, array(), '', true, true, true, array('getContextInfo', 'addMetaData', 'getApiClient'));
        $guzzle = $this->getMockBuilder(Boltpay_Guzzle_ApiClient::class)->setMethods(array('post', 'get'))->getMock();
        $response = $this->getMockBuilder(Response::class)->setMethods(array('getBody'))->getMock();
        $response->method('getBody')->will($this->returnValue(json_encode($case['response'])));
        $guzzle->method('post')->will($this->returnValue($response));
        $guzzle->method('get')->will($this->returnValue($response));
        $mock->method('getApiClient')->will($this->returnValue($guzzle));
        $mock->method('getContextInfo')->will($this->returnValue($case['context']));
        $mock->method('addMetaData');
        // Start test
        $result = $mock->transmit($case['command'], $case['data'], $case['object'], $case['type'], $case['storeId']);
        $this->assertInternalType('array', $result);
        $this->assertEquals($case['expect'], $result);
    }

    /**
     * Test cases
     *
     * @return array
     */
    public function transmitCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => array(),
                    'command' => '',
                    'data' => '',
                    'object' => '',
                    'type' => '',
                    'storeId' => '',
                    'publishable_key_multipage' => '',
                    'api_key' => '',
                    'context' => array(),
                    'response' => array()
                )
            ),
        );
    }

    /**
     * @test
     * @group        Helper
     * @group        Trait
     * @group        HelperApiTrait
     * @group        inProgress
     * @dataProvider transmitExceptionCases
     * @covers       Bolt_Boltpay_Helper_ApiTrait::transmit
     * @expectedException Exception
     * @param array $case
     */
    public function transmit_Exceptions(array $case)
    {
        $this->app->getStore()->setConfig('payment/boltpay/publishable_key_multipage', $case['publishable_key_multipage']);
        $this->app->getStore()->setConfig('payment/boltpay/api_key', $case['api_key']);
        $mock = $this->getMockForTrait(Bolt_Boltpay_Helper_ApiTrait::class, array(), '', true, true, true, array('getContextInfo', 'addMetaData', 'getApiClient', 'notifyException', 'logException'));
        $guzzle = $this->getMockBuilder(Boltpay_Guzzle_ApiClient::class)->setMethods(array('post', 'get'))->getMock();
        $response = $this->getMockBuilder(Response::class)->setMethods(array('getBody'))->getMock();
        $response->method('getBody')->willThrowException(new Exception('Test Rejected request'));
        $guzzle->method('post')->will($this->returnValue($response));
        $guzzle->method('get')->will($this->returnValue($response));
        $mock->method('getApiClient')->will($this->returnValue($guzzle));
        $mock->method('getContextInfo')->will($this->returnValue($case['context']));
        $mock->method('addMetaData');
        $mock->method('notifyException')->willReturnSelf();
        $mock->method('logException')->willReturnSelf();

        if (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException('Exception', $case['exception']);
        } else {
            $this->expectExceptionMessage($case['exception']);
        }
        // Start test
        $mock->transmit($case['command'], $case['data'], $case['object'], $case['type'], $case['storeId']);
    }

    /**
     * Test cases
     *
     * @return array
     */
    public function transmitExceptionCases()
    {
        return array(
            array(
                'case' => array(
                    'expect' => array(),
                    'command' => '',
                    'data' => '',
                    'object' => '',
                    'type' => '',
                    'storeId' => '',
                    'publishable_key_multipage' => '',
                    'api_key' => '',
                    'context' => array(),
                    'response' => null,
                    'exception' => 'Test Rejected request'
                )
            ),
        );
    }
}
