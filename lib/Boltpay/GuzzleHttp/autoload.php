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
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'ClientInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Client.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'functions.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'functions_include.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'HandlerStack.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'MessageFormatter.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Middleware.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'PrepareBodyMiddleware.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'RedirectMiddleware.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'RequestOptions.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'RetryMiddleware.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'TransferStats.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'UriTemplate.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'MessageTrait.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'StreamDecoratorTrait.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'PromiseInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'PromisorInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'CurlFactoryInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'AppendStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'BufferStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'DroppingStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'CachingStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'functions_include.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'FnStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'functions.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'InflateStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'LazyOpenStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'LimitStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'NoSeekStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'MultipartStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'PumpStream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'Request.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'Response.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'Rfc7230.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'ServerRequest.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'Stream.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'StreamWrapper.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'UploadedFile.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'Uri.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'UriNormalizer.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Psr7'.DIRECTORY_SEPARATOR.'UriResolver.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'Coroutine.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'EachPromise.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'FulfilledPromise.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'functions.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'functions_include.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'Promise.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'RejectedPromise.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'RejectionException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'AggregateException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'CancellationException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'TaskQueueInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Promise'.DIRECTORY_SEPARATOR.'TaskQueue.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'CurlFactory.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'CurlMultiHandler.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'CurlHandler.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'EasyHandle.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'MockHandler.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'Proxy.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Handler'.DIRECTORY_SEPARATOR.'StreamHandler.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'GuzzleException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'TransferException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'RequestException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'BadResponseException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'ClientException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'ConnectException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'SeekException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'TooManyRedirectsException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Exception'.DIRECTORY_SEPARATOR.'ServerException.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Cookie'.DIRECTORY_SEPARATOR.'CookieJarInterface.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Cookie'.DIRECTORY_SEPARATOR.'CookieJar.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Cookie'.DIRECTORY_SEPARATOR.'FileCookieJar.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Cookie'.DIRECTORY_SEPARATOR.'SessionCookieJar.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'Cookie'.DIRECTORY_SEPARATOR.'SetCookie.php';




