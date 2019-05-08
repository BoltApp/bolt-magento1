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
        
        
        $this->assertEmpty($object->apiKey);
    }
}