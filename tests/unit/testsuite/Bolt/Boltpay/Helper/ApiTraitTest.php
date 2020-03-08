<?php

require_once('TestHelper.php');

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
     * that verify_hook calls verify_hook_secret and returns the correct result
     *
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook
     * @covers Bolt_Boltpay_Helper_ApiTrait::verify_hook_secret
     */
    public function verifyHook_callsVerifyHookSecret()
    {
        $currentMock = $this->getCurrentMock();
        $encryptedSigningSecret = Mage::helper('core')->encrypt('signing secret');
        TestHelper::stubConfigValue('payment/boltpay/signing_key', $encryptedSigningSecret);
        $this->assertTrue($currentMock->verify_hook('payload', 'dt0bpl0AryqEkb/UrJLFdvL+4Cby8vGvmiHcXbVMZwI='));
        TestHelper::restoreOriginals();
    }

    /**
     * @test
     * @group        Helper
     * @group        Trait
     * @group        HelperApiTrait
     * @dataProvider transmitCases
     * @covers       Bolt_Boltpay_Helper_ApiTrait::transmit
     * @param array $case
     * @throws Mage_Core_Model_Store_Exception
     */
    public function transmit_successScenario(array $case)
    {
        $this->markTestSkipped();
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
        $this->markTestSkipped();
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
        $result = TestHelper::callNonPublicFunction($currentMock, 'constructRequestHeaders', ['this is a test', $encryptedKey]);
        $this->assertArraySubset($expected, $result);
        $this->assertTrue($result['X-Nonce'] >= 100000000);
        $this->assertTrue($result['X-Nonce'] < 1000000000);
    }
}
