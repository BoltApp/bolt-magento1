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
 * @copyright  Copyright (c) 2016-2020 Bolt Financial, Inc (https://www.bolt.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Boltpay_Bugsnag_Notification
{
    private static $CONTENT_TYPE_HEADER = 'Content-type: application/json';

    /**
     * The config instance.
     *
     * @var Boltpay_Bugsnag_Configuration
     */
    private $config;

    /**
     * The queue of errors to send to Bugsnag.
     *
     * @var Boltpay_Bugsnag_Error[]
     */
    private $errorQueue = array();

    /**
     * Create a new notification instance.
     *
     * @param Boltpay_Bugsnag_Configuration $config the configuration instance
     *
     * @return void
     */
    public function __construct(Boltpay_Bugsnag_Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Add an error to the queue.
     *
     * @param Boltpay_Bugsnag_Error $config         the bugsnag error instance
     * @param array         $passedMetaData the associated meta data
     *
     * @return bool
     */
    public function addError(Boltpay_Bugsnag_Error $error, $passedMetaData = array())
    {
        // Check if this error should be sent to Bugsnag
        if (!$this->config->shouldNotify()) {
            return false;
        }

        // Add global meta-data to error
        $error->setMetaData($this->config->metaData);

        // Add request meta-data to error
        if (Boltpay_Bugsnag_Request::isRequest()) {
            $error->setMetaData(Boltpay_Bugsnag_Request::getRequestMetaData());
        }

        // Session Tab
        if ($this->config->sendSession && !empty($_SESSION)) {
            $error->setMetaData(array('session' => $_SESSION));
        }

        // Cookies Tab
        if ($this->config->sendCookies && !empty($_COOKIE)) {
            $error->setMetaData(array('cookies' => $_COOKIE));
        }

        // Add environment meta-data to error
        if ($this->config->sendEnvironment && !empty($_ENV)) {
            $error->setMetaData(array('Environment' => $_ENV));
        }

        // Add user-specified meta-data to error
        $error->setMetaData($passedMetaData);

        // Run beforeNotify function (can cause more meta-data to be merged)
        if (isset($this->config->beforeNotifyFunction) && is_callable($this->config->beforeNotifyFunction)) {
            $beforeNotifyReturn = call_user_func($this->config->beforeNotifyFunction, $error);
        }

        // Skip this error if the beforeNotify function returned FALSE
        if (!isset($beforeNotifyReturn) || $beforeNotifyReturn !== false) {
            $this->errorQueue[] = $error;

            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the array representation.
     *
     * @return array
     */
    public function toArray()
    {
        $events = array();
        foreach ($this->errorQueue as $error) {
            $errorArray = $error->toArray();

            if (!is_null($errorArray)) {
                $events[] = $errorArray;
            }
        }

        return array(
            'apiKey' => $this->config->apiKey,
            'notifier' => $this->config->notifier,
            'events' => $events,
        );
    }

    /**
     * Deliver everything on the queue to Bugsnag.
     *
     * @return void
     */
    public function deliver()
    {
        if (empty($this->errorQueue)) {
            return;
        }

        // Post the request to bugsnag
        $this->postJSON($this->config->getNotifyEndpoint(), $this->toArray());

        // Clear the error queue
        $this->errorQueue = array();
    }

    /**
     * Post the given data to Bugsnag in json form.
     *
     * @param string $url  the url to hit
     * @param array  $data the data send
     *
     * @return void
     */
    public function postJSON($url, $data)
    {
        // Try to send the whole lot, or without the meta data for the first
        // event. If failed, try to send the first event, and then the rest of
        // them, revursively. Decrease by a constant and concquer if you like.
        // Note that the base case is satisfied as soon as the payload is small
        // enought to send, or when it's simply discarded.
        try {
            $body = $this->encode($data);
        } catch (RuntimeException $e) {
            if (count($data['events']) > 1) {
                $event = array_shift($data['events']);
                $this->postJSON($url, array_merge($data, array('events' => array($event))));
                $this->postJSON($url, $data);
            } else {
                error_log('Bugsnag Warning: '.$e->getMessage());
            }

            return;
        }

        try {
            $this->sendToBugsnag($url, $body);
        } catch (Exception $e) {
            error_log('Bugsnag Warning: Couldn\'t notify. '.$e->getMessage());
        }
    }

    /**
     * Json encode the given data.
     *
     * We will also strip out the meta data if it's too large.
     *
     * @param array $data the data to encode
     *
     * @throws RuntimeException
     *
     * @return string
     */
    private function encode(array $data)
    {
        $body = json_encode($data);

        if ($this->length($body) > 500000) {
            unset($data['events'][0]['metaData']);
        }

        $body = json_encode($data);

        if ($this->length($body) > 500000) {
            throw new RuntimeException('Payload too large');
        }

        return $body;
    }

    /**
     * Get the length of the given string in bytes.
     *
     * @param string $str the string to get the length of
     *
     * @return int
     */
    private function length($str)
    {
        return function_exists('mb_strlen') ? mb_strlen($str, '8bit') : strlen($str);
    }

    /**
     * Post the given info to Bugsnag using guzzle
     *
     * @param string $url  the url to hit
     * @param string $body the request body
     *
     * @return void
     */
    private function sendToBugsnag($url, $body)
    {
        try {
            $client = new Boltpay_Guzzle_ApiClient();
            $client->post($url, $body);
        }catch (\Exception $exception){
            error_log($exception->getMessage());
        }

    }
}
