<?php

/**
 * Stream wrapper used to stub streams that use php protocol e.g. php://input
 * Also contains static methods used to initiate stream stubbing and to restore default functionality
 *
 * @link https://www.php.net/manual/en/class.streamwrapper.php
 */
class Bolt_Boltpay_StreamHelper
{
    /**
     * @var int Current position of the stream
     */
    protected $_index = 0;

    /**
     * @var int Length of data to be returned from stream
     */
    protected $_length = 0;

    /**
     * @var string Content to be returned by the stream
     */
    public static $content = '';

    /**
     * Initiate values, called before opening stream
     */
    public function __construct()
    {
        $this->_index = 0;
        $this->_length = strlen(self::$content);
    }

    /**
     * This method is called immediately after the wrapper is initialized (f.e. by fopen() and file_get_contents())
     * Because we are stubbing every stream that uses php protocol we always return true
     *
     * @param string $path URL that was passed to the original function
     * @param string $mode used to open the file, as detailed for fopen()
     * @param int $options additional flags set by the streams API
     * @param string $opened_path full path of the file/resource that was actually opened
     * @return bool always true because we are stubbing any stream
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    /**
     * This method is called in response to fread() and fgets()
     * Here we return $count number of bytes in $content property starting from current position
     * Then we increment the current position by the number of bytes returned
     *
     * @param int $count of data bytes from the current position to be returned
     * @return string up to $count number of bytes from $content if available
     */
    public function stream_read($count)
    {
        if (is_null($this->_length) === true) {
            $this->_length = strlen(self::$content);
        }

        $length = min($count, $this->_length - $this->_index);
        $data = substr(self::$content, $this->_index);
        $this->_index = $this->_index + $length;
        return $data;
    }

    /**
     * Tests for end-of-file on a file pointer
     * Instead of file we check for end of data stored in $content
     *
     * @return bool true if all data from $content was returned, otherwise false
     */
    public function stream_eof()
    {
        return ($this->_index >= $this->_length ? true : false);
    }

    /**
     * Replace default stream wrapper for php protocol with this helper class
     */
    public static function register()
    {
        stream_wrapper_unregister("php");
        stream_wrapper_register("php", self::class);
    }

    /**
     * Restore default stream wrapper for streams that use php protocol
     */
    public static function restore()
    {
        stream_wrapper_restore("php");
    }

    /**
     * Sets content to be returned by the next stream
     * We store it in a static variable because each stream is a new instance of streamWrapper
     *
     * @param string $data to be returned by next stream
     */
    public static function setData($data)
    {
        self::$content = $data;
    }
}