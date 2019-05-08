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
        
        
        $this->assertEmpty($object->apiKey);
    }
}