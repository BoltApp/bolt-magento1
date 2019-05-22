<?php
define('ROOT_DIR', dirname(dirname(__DIR__)));

define('BUGSNAG_ROOT', 'Bugsnag');
define('BUGSNAG_DIR', ROOT_DIR . '/lib/Boltpay/Bugsnag/');

function classAutoLoader($className)
{
    $classArray = explode('_', $className);
    if (is_array($classArray)) {
        $rootName = $classArray[0];
        switch ($rootName) {
            case BUGSNAG_ROOT:
                require_once BUGSNAG_DIR . $classArray[1].'.php';
                break;
        }
    }
}

spl_autoload_register('classAutoLoader');