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

class Bugsnag_Diagnostics
{
    /**
     * The config instance.
     *
     * @var Bugsnag_Configuration
     */
    private $config;

    /**
     * Create a new diagnostics instance.
     *
     * @param Bugsnag_Configuration $config the configuration instance
     *
     * @return void
     */
    public function __construct(Bugsnag_Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * Get the application information.
     *
     * @return array
     */
    public function getAppData()
    {
        $appData = array();

        if (!is_null($this->config->appVersion)) {
            $appData['version'] = $this->config->appVersion;
        }

        if (!is_null($this->config->releaseStage)) {
            $appData['releaseStage'] = $this->config->releaseStage;
        }

        if (!is_null($this->config->type)) {
            $appData['type'] = $this->config->type;
        }

        return $appData;
    }

    /**
     * Get the device information.
     *
     * @return array
     */
    public function getDeviceData()
    {
        return array(
            'hostname' => $this->config->get('hostname', php_uname('n')),
        );
    }

    /**
     * Get the error context.
     *
     * @return array
     */
    public function getContext()
    {
        return $this->config->get('context', Bugsnag_Request::getContext());
    }

    /**
     * Get the current user.
     *
     * @return array
     */
    public function getUser()
    {
        $defaultUser = array();
        $userId = Bugsnag_Request::getUserId();

        if (!is_null($userId)) {
            $defaultUser['id'] = $userId;
        }

        return $this->config->get('user', $defaultUser);
    }
}
