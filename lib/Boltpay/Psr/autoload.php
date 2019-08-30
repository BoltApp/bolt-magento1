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

// We used to have an autoloader, but it caused problems in some
// environments. So now we manually load the entire library upfront.
//
// The file is still called Autoload so that existing integration
// instructions continue to work.
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'MessageInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'RequestInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'ResponseInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'ServerRequestInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'StreamInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'UploadedFileInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Message'.DIRECTORY_SEPARATOR.'UriInterface.php';