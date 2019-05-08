<?php
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function constructor()
    {
        $object = new Bugsnag_Configuration();
        // check object properies
        $this->assertClassHasAttribute('apiKey', get_class($object));
        $this->assertClassHasAttribute('autoNotify', get_class($object));
        $this->assertClassHasAttribute('batchSending', get_class($object));
        $this->assertClassHasAttribute('useSSL', get_class($object));
        $this->assertClassHasAttribute('endpoint', get_class($object));
        $this->assertClassHasAttribute('notifyReleaseStages', get_class($object));
        $this->assertClassHasAttribute('filters', get_class($object));
        $this->assertClassHasAttribute('projectRoot', get_class($object));
        $this->assertClassHasAttribute('proxySettings', get_class($object));
        $this->assertClassHasAttribute('notifier', get_class($object));
        $this->assertClassHasAttribute('sendEnvironment', get_class($object));
        $this->assertClassHasAttribute('sendCookies', get_class($object));
        $this->assertClassHasAttribute('sendSession', get_class($object));
        $this->assertClassHasAttribute('sendCode', get_class($object));
        $this->assertClassHasAttribute('stripPath', get_class($object));
        $this->assertClassHasAttribute('stripPathRegex', get_class($object));
        $this->assertClassHasAttribute('context', get_class($object));
        $this->assertClassHasAttribute('type', get_class($object));
        $this->assertClassHasAttribute('user', get_class($object));
        $this->assertClassHasAttribute('releaseStage', get_class($object));
        $this->assertClassHasAttribute('appVersion', get_class($object));
        $this->assertClassHasAttribute('hostname', get_class($object));
        $this->assertClassHasAttribute('metaData', get_class($object));
        $this->assertClassHasAttribute('beforeNotifyFunction', get_class($object));
        $this->assertClassHasAttribute('errorReportingLevel', get_class($object));
        $this->assertClassHasAttribute('curlOptions', get_class($object));
        $this->assertClassHasAttribute('debug', get_class($object));
        // Check properies types
        $this->assertInternalType('null', $object->apiKey);
        $this->assertInternalType('boolean', $object->autoNotify);
        $this->assertInternalType('boolean', $object->batchSending);
        $this->assertInternalType('boolean', $object->useSSL);
        $this->assertInternalType('null', $object->endpoint);
        $this->assertInternalType('null', $object->notifyReleaseStages);
        $this->assertInternaltype('array', $object->filters);
        $this->assertInternalType('null', $object->projectRoot);
        $this->assertInternalType('array', $object->proxySettings);
        $this->assertInternalType('array', $object->notifier);
        $this->assertInternalType('boolean', $object->sendEnvironment);
        $this->assertInternalType('boolean', $object->sendCookies);
        $this->assertInternalType('boolean', $object->sendSession);
        $this->assertInternalType('boolean', $object->sendCode);
        $this->assertInternalType('null', $object->stripPath);
        $this->assertInternalType('null', $object->stripPathRegex);
        $this->assertInternalType('null', $object->context);
        $this->assertInternalType('null', $object->type);
        $this->assertInternalType('null', $object->user);
        $this->assertInternalType('string', $object->releaseStage);
        $this->assertInternalType('null', $object->appVersion);
        $this->assertInternalType('null', $object->hostname);
        $this->assertInternalType('null', $object->metaData);
        $this->assertInternalType('null', $object->beforeNotifyFunction);
        $this->assertInternalType('null', $object->errorReportingLevel);
        $this->assertInternalType('array', $object->curlOptions);
        $this->assertInternalType('boolean', $object->debug);
        // Default values
        $this->assertEmpty($object->apiKey);
        $this->assertTrue($object->autoNotify);
        $this->assertTrue($object->batchSending);
        $this->assertTrue($object->useSSL);
        $this->assertEmpty($object->endpoint);
        $this->assertEmpty($object->notifyReleaseStages);
        $this->assertEquals(['password'], $object->filters);
        $this->assertEmpty($object->projectRoot);
        $this->assertEmpty($object->projectRootRegex);
        $this->assertEmpty($object->proxySettings);
        $this->assertEquals([
            'name' => 'Bugsnag PHP (Official)',
            'version' => '2.9.2',
            'url' => 'https://bugsnag.com',
        ], $object->notifier);
        $this->assertFalse($object->sendEnvironment);
        $this->assertTrue($object->sendCookies);
        $this->assertTrue($object->sendSession);
        $this->assertTrue($object->sendCode);
        $this->assertEmpty($object->stripPath);
        $this->assertEmpty($object->stripPathRegex);
        $this->assertEmpty($object->context);
        $this->assertEmpty($object->type);
        $this->assertEmpty($object->user);
        $this->assertEquals('production', $object->releaseStage);
        $this->assertEmpty($object->appVersion);
        $this->assertEmpty($object->hostname);
        $this->assertEmpty($object->metaData);
        $this->assertEmpty($object->beforeNotifyFunction);
        $this->assertEmpty($object->errorReportingLevel);
        $this->assertEmpty($object->curlOptions);
        $this->assertFalse($object->debug);
    }

    /**
     * @test
     * @dataProvider getNotifyEndpointCases
     */
    public function getNotifyEndpoint($data)
    {
        $object = new Bugsnag_Configuration();
        $object->endpoint = $data['endpoint'];
        $object->useSSL = $data['useSSL'];
        $this->assertEquals($data['expect'], $object->getNotifyEndpoint());
    }

    /**
     * @return array
     */
    public function getNotifyEndpointCases()
    {
        return [
            [
                'data' => [
                    'endpoint' => null,
                    'useSSL' => false,
                    'expect' => 'http://notify.bugsnag.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => null,
                    'useSSL' => true,
                    'expect' => 'https://notify.bugsnag.com'
                ]
            ],
            // this is not right behaviour
            [
                'data' => [
                    'endpoint' => '',
                    'useSSL' => false,
                    'expect' => 'http://'
                ]
            ],
            [
                'data' => [
                    'endpoint' => '',
                    'useSSL' => true,
                    'expect' => 'https://'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'bolt.com',
                    'useSSL' => false,
                    'expect' => 'http://bolt.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'bolt.com',
                    'useSSL' => true,
                    'expect' => 'https://bolt.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'http://bolt.com',
                    'useSSL' => false,
                    'expect' => 'http://bolt.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'http://bolt.com',
                    'useSSL' => true,
                    'expect' => 'http://bolt.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'https://bolt.com',
                    'useSSL' => false,
                    'expect' => 'https://bolt.com'
                ]
            ],
            [
                'data' => [
                    'endpoint' => 'https://bolt.com',
                    'useSSL' => true,
                    'expect' => 'https://bolt.com'
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider shouldNotifyCases
     */
    public function shouldNotify($data)
    {
        $object = new Bugsnag_Configuration();
        $object->notifyReleaseStages = $data['notifyReleaseStages'];
        $object->releaseStage = $data['releaseStage'];
        $this->assertEquals($data['expect'], $object->shouldNotify());
    }

    /**
     * @return array
     */
    public function shouldNotifyCases()
    {
        return [
            [
                'data' => [
                    'notifyReleaseStages' => [],
                    'releaseStage' => '',
                    'expect' => false
                ]
            ],
            [
                'data' => [
                    'notifyReleaseStages' => [],
                    'releaseStage' => 'shipping',
                    'expect' => false
                ]
            ],
            [
                'data' => [
                    'notifyReleaseStages' => ['shipping'],
                    'releaseStage' => '',
                    'expect' => false
                ]
            ],
            [
                'data' => [
                    'notifyReleaseStages' => ['shipping'],
                    'releaseStage' => 'shipping',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'notifyReleaseStages' => ['shipping', 'payment'],
                    'releaseStage' => 'shipping',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'notifyReleaseStages' => ['shipping', 'payment'],
                    'releaseStage' => 'confirmation',
                    'expect' => false
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider shouldIgnoreErrorCodeCases
     */
    public function shouldIgnoreErrorCode($data)
    {
        $object = new Bugsnag_Configuration();
        $object->errorReportingLevel = $data['errorReportingLevel'];
        $this->assertEquals($data['expect'], $object->shouldIgnoreErrorCode($data['code']));
    }

    /**
     * @return array
     */
    public function shouldIgnoreErrorCodeCases()
    {
        return [
            [
                'data' => [
                    'errorReportingLevel' => '',
                    'code' => '',
                    'expect' => true
                ]
            ],
//             [
//                 'data' => [
//                     'errorReportingLevel' => '',
//                     'code' => 0,
//                     'expect' => true
//                 ]
//             ],
        ];
    }
}
