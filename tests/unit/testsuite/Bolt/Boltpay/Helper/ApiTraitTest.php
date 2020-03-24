<?php

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Bolt_Boltpay_TestHelper as TestHelper;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class Bolt_Boltpay_Helper_ApiTraitTest
 *
 * @coversDefaultClass Bolt_Boltpay_Helper_ApiTrait
 */
class Bolt_Boltpay_Helper_ApiTraitTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Mage_Core_Model_App
     */
    private $app;

    public function setUp()
    {
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }

    /**
     * Creates mock of api trait with specific methods mocked
     *
     * @param array|null $methods to be mocked
     * @return MockObject|Bolt_Boltpay_Helper_Data mocked instance of api trait
     */
    private function getCurrentMock($methods = null)
    {
        return $this->getMockBuilder('Bolt_Boltpay_Helper_Data')
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::getApiClient
     */
    public function getApiClient_whenApiClientIsEmpty_returnsNewApiClient()
    {
        $currentMock = $this->getCurrentMock();
        $this->assertEmpty(TestHelper::getNonPublicProperty($currentMock, 'apiClient'));
        $this->assertInstanceOf(Boltpay_Guzzle_ApiClient::class, $currentMock->getApiClient());
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::getApiClient
     */
    public function getApiClient_whenApiClientIsNotEmpty_returnsApiClient()
    {
        $currentMock = $this->getCurrentMock();
        $apiClient = new Boltpay_Guzzle_ApiClient();
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClient);
        $this->assertEquals($apiClient, $currentMock->getApiClient());
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::fetchTransaction
     */
    public function fetchTransaction_callsTransmit()
    {
        $currentMock = $this->getCurrentMock(array('transmit'));
        $currentMock->expects($this->once())->method('transmit');
        $currentMock->fetchTransaction('test reference');
    }

    /**
     * @test
     * that verify_hook returns true if verify_hook_secret returns true
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook_secret
     */
    public function verifyHook_ifVerifyHookSecretReturnsTrue_returnsTrue()
    {
        $currentMock = $this->getCurrentMock();
        $encryptedSigningSecret = Mage::helper('core')->encrypt('signing secret');
        TestHelper::stubConfigValue('payment/boltpay/signing_key', $encryptedSigningSecret);
        $this->assertTrue($currentMock->verify_hook('payload', 'dt0bpl0AryqEkb/UrJLFdvL+4Cby8vGvmiHcXbVMZwI='));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * that verify_hook returns true if verify_hook_secret returns false and getStatusCode returns 200
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook_api
     */
    public function verifyHook_ifVerifyHookSecretReturnsFalse_callsVerifyHookApiAndReturnsTrue()
    {
        $currentMock = $this->getCurrentMock();
        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('post'))
            ->getMock();
        $responseMock = $this->getMockBuilder('Response')
            ->setMethods(array('getBody', 'getStatusCode'))
            ->getMock();
        $responseMock->method('getBody')->willReturn('');
        $responseMock->method('getStatusCode')->willReturn(200);
        $apiClientMock->method('post')->willReturn($responseMock);
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClientMock);
        $this->assertTrue($currentMock->verify_hook('payload', 'header'));
    }

    /**
     * @test
     * that verify_hook returns false if verify_hook_secret returns false and getStatusCode returns 500
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook_api
     */
    public function verifyHook_ifVerifyHookSecretReturnsFalse_callsVerifyHookApiAndReturnsFalse()
    {
        $currentMock = $this->getCurrentMock();
        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('post'))
            ->getMock();
        $responseMock = $this->getMockBuilder('Response')
            ->setMethods(array('getBody', 'getStatusCode'))
            ->getMock();
        $responseMock->method('getBody')->willReturn('');
        $responseMock->method('getStatusCode')->willReturn(500);
        $apiClientMock->method('post')->willReturn($responseMock);
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClientMock);
        $this->assertFalse($currentMock->verify_hook('payload', 'header'));
    }

    /**
     * @test
     * that verify_hook returns false and logs exception if verify_hook_secret returns false and exception is thrown
     * in verify_hook_api
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook_api
     */
    public function verifyHook_ifVerifyHookSecretReturnsFalse_callsVerifyHookApiAndLogsException()
    {
        $currentMock = $this->getCurrentMock(array('notifyException', 'logException'));
        $currentMock->expects($this->once())->method('notifyException');
        $currentMock->expects($this->once())->method('logException');
        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('post'))
            ->getMock();
        $apiClientMock->method('post')->willThrowException(new Exception('expected exception'));
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClientMock);
        $this->assertFalse($currentMock->verify_hook('payload', 'header'));
    }

    /**
     * @test
     *
     * @dataProvider transmit_withDifferentParams_callsRightMethodWithRightParamsProvider
     *
     * @covers       Bolt_Boltpay_Helper_ApiTrait::transmit
     *
     * @param $command
     * @param $data
     * @param $object
     * @param $type
     * @param $url
     * @param $key
     * @param $method
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     * @throws ReflectionException
     * @throws GuzzleException
     */
    public function transmit_withDifferentParams_callsRightMethodWithRightParams($command, $data, $object, $type, $url, $params, $key)
    {
        TestHelper::stubConfigValue('payment/boltpay/publishable_key_multipage', 'publishable_key_multipage');
        TestHelper::stubConfigValue('payment/boltpay/api_key', 'api_key');
        $currentMock = $this->getCurrentMock();
        srand(1);
        $headerInfo = TestHelper::callNonPublicFunction($currentMock, 'constructRequestHeaders', array($params, $key));
        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('post', 'get'))
            ->getMock();
        $responseMock = $this->getMockBuilder('Response')
            ->setMethods(array('getBody'))
            ->getMock();
        $responseMock->method('getBody')->willReturn('');
        if ($params) {
            $apiClientMock->expects($this->once())->method('post')->with($url, $params, $headerInfo)->willReturn($responseMock);
            $apiClientMock->expects($this->never())->method('get');
        } else {
            $apiClientMock->expects($this->never())->method('post');
            $apiClientMock->expects($this->once())->method('get')->with($url, $headerInfo)->willReturn($responseMock);
        }
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClientMock);

        srand(1);
        $currentMock->transmit($command, $data, $object, $type);

        TestHelper::restoreOriginals();
    }

    /**
     * Data provider for {@see transmit_withDifferentParams_callsRightMethodWithRightParams}
     *
     * @return array
     */
    public function transmit_withDifferentParams_callsRightMethodWithRightParamsProvider()
    {
        return array(
            array(
                'command' => 'sign',
                'data' => null,
                'object' => 'merchant',
                'type' => 'transactions',
                'url' => 'https://api.bolt.com/v1/merchant/sign',
                'params' => '',
                'key' => 'api_key'
            ),
            array(
                'command' => 'orders',
                'data' => null,
                'object' => 'merchant',
                'type' => 'transactions',
                'url' => 'https://api.bolt.com/v1/merchant/orders',
                'params' => '',
                'key' => 'api_key'
            ),
            array(
                'command' => null,
                'data' => null,
                'object' => 'merchant',
                'type' => 'transactions',
                'url' => 'https://api.bolt.com/v1/merchant',
                'params' => '',
                'key' => 'api_key'
            ),
            array(
                'command' => '',
                'data' => null,
                'object' => 'merchant',
                'type' => '',
                'url' => 'https://api.bolt.com/v1/merchant',
                'params' => '',
                'key' => 'publishable_key_multipage'
            ),
            array(
                'command' => 'testcmd',
                'data' => array('data'),
                'object' => 'testobj',
                'type' => 'transactions',
                'url' => 'https://api.bolt.com/v1/testobj/transactions/testcmd',
                'params' => json_encode(array('data')),
                'key' => 'api_key'
            )
        );
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::transmit
     *
     * @expectedException Exception
     * @expectedExceptionMessage expected exception
     */
    public function transmit_whenGetThrowsException_logsExceptionAndRethrows()
    {
        $currentMock = $this->getCurrentMock(array('notifyException', 'logException'));
        $currentMock->expects($this->once())->method('notifyException');
        $currentMock->expects($this->once())->method('logException');
        $apiClientMock = $this->getMockBuilder('Boltpay_Guzzle_ApiClient')
            ->setMethods(array('get'))
            ->getMock();
        $apiClientMock->expects($this->once())->method('get')->willThrowException(new Exception('expected exception'));
        TestHelper::setNonPublicProperty($currentMock, 'apiClient', $apiClientMock);
        $currentMock->transmit('command', null);
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::setResponseContextHeaders
     */
    public function setResponseContextHeaders_callsSetHeaderWithRightParams()
    {
        $appMock = $this->getMockBuilder('Mage_Core_Model_App')
            ->setMethods(array('getResponse'))
            ->getMock();

        $responseMock = $this->getMockBuilder('Mage_Core_Controller_Response_Http')
            ->setMethods(array('setHeader'))
            ->getMock();

        $contextInfo = Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo();
        $responseMock->expects($this->exactly(2))
            ->method('setHeader')
            ->withConsecutive(
                ['User-Agent', 'BoltPay/Magento-' . $contextInfo["Magento-Version"] . '/' . $contextInfo["Bolt-Plugin-Version"], true],
                ['X-Bolt-Plugin-Version', $contextInfo["Bolt-Plugin-Version"], true]
            )
            ->willReturnSelf();

        $appMock->expects($this->once())->method('getResponse')->willReturn($responseMock);

        $previousApp = Mage::app('default');

        TestHelper::setNonPublicProperty('Mage', '_app', $appMock);

        $currentMock = $this->getCurrentMock();
        $currentMock->setResponseContextHeaders();

        TestHelper::setNonPublicProperty('Mage', '_app', $previousApp);
    }

    /**
     * @test
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::constructRequestHeaders
     */
    public function constructRequestHeaders_returnsCorrectHeaders()
    {
        $contextInfo = Bolt_Boltpay_Helper_BugsnagTrait::getContextInfo();
        $encryptedKey = Mage::helper('core')->encrypt('test key');
        $expected = array(
            'User-Agent' => 'BoltPay/Magento-' . $contextInfo["Magento-Version"] . '/' . $contextInfo["Bolt-Plugin-Version"],
            'Content-Length' => 14,
            'X-Bolt-Plugin-Version' => $contextInfo["Bolt-Plugin-Version"],
            'X-Api-Key' => 'test key'
        );
        $currentMock = $this->getCurrentMock();
        $result = TestHelper::callNonPublicFunction($currentMock, 'constructRequestHeaders', array('this is a test', $encryptedKey));
        $this->assertArraySubset($expected, $result);
        $this->assertTrue($result['X-Nonce'] >= 100000000);
        $this->assertTrue($result['X-Nonce'] < 1000000000);
    }
}
