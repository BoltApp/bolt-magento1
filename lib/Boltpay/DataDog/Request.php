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

class Boltpay_DataDog_Request
{
    /**
     * Are we currently processing a request?
     *
     * @return bool
     */
    public static function isRequest()
    {
        return isset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Get the request formatted as meta data.
     *
     * @return array
     */
    public static function getRequestMetaData()
    {
        static $requestData;

        if ($requestData !== null) {
            return $requestData;
        }

        $requestData = array();
        $methodsWithPayload = array('PUT');

        $requestData['host'] = @$_SERVER['HTTP_HOST'];
        $requestData['request_path'] = @$_SERVER['REQUEST_URI'];
        $requestData['time'] = date("D M j, Y - G:i:s T");
        $requestData['trace_id'] = @$_SERVER['HTTP_X_BOLT_TRACE_ID'];
        // Request Tab
        $requestData['request'] = array();
        $requestData['request']['url'] = self::getCurrentUrl();
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $requestData['request']['httpMethod'] = $_SERVER['REQUEST_METHOD'];
        }

        if (!empty($_POST)) {
            $requestData['request']['params'] = $_POST;
        } else {
            $input = file_get_contents('php://input');

            if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
                $requestData['request']['params'] = json_decode($input, true);
            }

            if (isset($_SERVER['REQUEST_METHOD']) && in_array(strtoupper($_SERVER['REQUEST_METHOD']), $methodsWithPayload)) {
                parse_str($input, $params);
                if (isset($requestData['request']['params']) && is_array($requestData['request']['params'])) {
                    $requestData['request']['params'] = array_merge($requestData['request']['params'], $params);
                } else {
                    $requestData['request']['params'] = $params;
                }
            }
        }

        $requestData['request_ip'] = self::getRequestIp();
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $requestData['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        $headers = self::getRequestHeaders();
        if (!empty($headers)) {
            $requestData['request']['headers'] = $headers;
        }

        return $requestData;
    }

    /**
     * Get the request id.
     *
     * @return string|null
     */
    public static function getUserId()
    {
        if (self::isRequest()) {
            return self::getRequestIp();
        }
    }

    /**
     * Get the request url.
     *
     * @return string
     */
    public static function getCurrentUrl()
    {
        $schema = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? 'https://' : 'http://';

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        return $schema . $host . $_SERVER['REQUEST_URI'];
    }

    /**
     * Get the request ip.
     *
     * @return string
     */
    public static function getRequestIp()
    {
        return isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Get the request headers.
     *
     * @return array
     */
    public static function getRequestHeaders()
    {
        $headers = array();

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}
