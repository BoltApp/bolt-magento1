<?php

$mapping = array(
    'Bugsnag\Breadcrumbs\Breadcrumb' => __DIR__ . '/Bugsnag/Breadcrumbs/Breadcrumb.php',
    'Bugsnag\Breadcrumbs\Recorder' => __DIR__ . '/Bugsnag/Breadcrumbs/Recorder.php',
    'Bugsnag\Callbacks\CustomUser' => __DIR__ . '/Bugsnag/Callbacks/CustomUser.php',
    'Bugsnag\Callbacks\EnvironmentData' => __DIR__ . '/Bugsnag/Callbacks/EnvironmentData.php',
    'Bugsnag\Callbacks\GlobalMetaData' => __DIR__ . '/Bugsnag/Callbacks/GlobalMetaData.php',
    'Bugsnag\Callbacks\RequestContext' => __DIR__ . '/Bugsnag/Callbacks/RequestContext.php',
    'Bugsnag\Callbacks\RequestCookies' => __DIR__ . '/Bugsnag/Callbacks/RequestCookies.php',
    'Bugsnag\Callbacks\RequestMetaData' => __DIR__ . '/Bugsnag/Callbacks/RequestMetaData.php',
    'Bugsnag\Callbacks\RequestSession' => __DIR__ . '/Bugsnag/Callbacks/RequestSession.php',
    'Bugsnag\Callbacks\RequestUser' => __DIR__ . '/Bugsnag/Callbacks/RequestUser.php',
    'Bugsnag\Client' => __DIR__ . '/Bugsnag/Client.php',
    'Bugsnag\Configuration' => __DIR__ . '/Bugsnag/Configuration.php',
    'Bugsnag\ErrorTypes' => __DIR__ . '/Bugsnag/ErrorTypes.php',
    'Bugsnag\Handler' => __DIR__ . '/Bugsnag/Handler.php',
    'Bugsnag\HttpClient' => __DIR__ . '/Bugsnag/HttpClient.php',
    'Bugsnag\Middleware\BreadcrumbData' => __DIR__ . '/Bugsnag/Middleware/BreadcrumbData.php',
    'Bugsnag\Middleware\CallbackBridge' => __DIR__ . '/Bugsnag/Middleware/CallbackBridge.php',
    'Bugsnag\Middleware\NotificationSkipper' => __DIR__ . '/Bugsnag/Middleware/NotificationSkipper.php',
    'Bugsnag\Pipeline' => __DIR__ . '/Bugsnag/Pipeline.php',
    'Bugsnag\Report' => __DIR__ . '/Bugsnag/Report.php',
    'Bugsnag\Request\BasicResolver' => __DIR__ . '/Bugsnag/Request/BasicResolver.php',
    'Bugsnag\Request\ConsoleRequest' => __DIR__ . '/Bugsnag/Request/ConsoleRequest.php',
    'Bugsnag\Request\NullRequest' => __DIR__ . '/Bugsnag/Request/NullRequest.php',
    'Bugsnag\Request\PhpRequest' => __DIR__ . '/Bugsnag/Request/PhpRequest.php',
    'Bugsnag\Request\RequestInterface' => __DIR__ . '/Bugsnag/Request/RequestInterface.php',
    'Bugsnag\Request\ResolverInterface' => __DIR__ . '/Bugsnag/Request/ResolverInterface.php',
    'Bugsnag\Stacktrace' => __DIR__ . '/Bugsnag/Stacktrace.php',
);


spl_autoload_register(function ($class) use ($mapping) {
    if (isset($mapping[$class])) {
        require_once $mapping[$class];
    }
}, true, true);
