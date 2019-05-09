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
 * @copyright  Copyright (c) 2019 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Bolt_Boltpay_Helper_DataDogTest extends PHPUnit_Framework_TestCase
{
    const INFO_MESSAGE = 'Datadog UnitTest Info';
    const WARNING_MESSAGE = 'Datadog UnitTest Warning';
    const ERROR_MESSAGE = 'Datadog UnitTest Error';

    /**
     * @var Bolt_Boltpay_Helper_Data
     */
    private $datadogHelper;

    public function setUp()
    {
        $datadogOptions = array(
            'datadogKey' => Bolt_Boltpay_Helper_DataDogTrait::$defaultDataDogKey,
            'datadogKeySeverity' => 'error,info,warning'
        );

        Mage::app()->getStore()->setConfig('payment/boltpay/extra_options', json_encode($datadogOptions));
        $this->datadogHelper = Mage::helper('boltpay');
    }

    /**
     * Function test log info
     */
    public function testLogInfo()
    {
        $infoLog = $this->datadogHelper->logInfo(self::INFO_MESSAGE);
        $this->assertTrue($infoLog->getLastResponseStatus());
    }

    /**
     * Function test log warning
     */
    public function testLogWarning()
    {
        $warningLog = $this->datadogHelper->logWarning(self::WARNING_MESSAGE);
        $this->assertTrue($warningLog->getLastResponseStatus());
    }

    /**
     * Function test log error
     */
    public function testLogException()
    {
        $exception = new Exception(self::ERROR_MESSAGE);
        $errorLog = $this->datadogHelper->logException($exception);
        $this->assertTrue($errorLog->getLastResponseStatus());
    }
}
