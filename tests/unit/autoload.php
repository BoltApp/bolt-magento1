<?php
ini_set(
    'include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/../../app' . PATH_SEPARATOR
);
ini_set(
    'include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/../../app/code/community/Bolt/Boltpay' . PATH_SEPARATOR . dirname(__FILE__)
    . PATH_SEPARATOR
);
ini_set(
    'include_path', ini_get('include_path') . PATH_SEPARATOR
    . dirname(__FILE__) . '/testsuite/Bolt/Boltpay' . PATH_SEPARATOR
);

error_reporting(E_ALL | E_STRICT);

//Set custom memory limit
ini_set('memory_limit', '512M');

//Include Magento libraries
require_once 'Mage.php';

//autoload test helpers and controllers
spl_autoload_register(
    function ($name) {
        $classPathParts = explode('_', $name);

        $classPath = implode(DS, array_merge(array(BP, 'tests', 'unit', 'testsuite'), $classPathParts)) . '.php';
        if (file_exists($classPath)) {
            require $classPath;
            return;
        }

        foreach (array('local', 'community', 'core') as $codePool) {
            $controllerPathParts = $classPathParts;
            array_splice($controllerPathParts, 2, 0, 'controllers');
            array_unshift($controllerPathParts, BP, 'app', 'code', $codePool);
            $controllerPath = implode(DS, $controllerPathParts) . '.php';
            if (file_exists($controllerPath)) {
                require $controllerPath;
                return;
            }
        }
    }
);

//Start the Magento application
Mage::app('default');

//Avoid issues "Headers already send"
session_start();

/**
 * Class SetupHelper
 * Ensures that the Bolt Magento table extensions have been added to the underlying database
 */
class BoltDbSetup extends Mage_Eav_Model_Entity_Setup {
    /**
     * Creates a setup object required to run sql migration scripts and then executes all migration scripts
     */
    public function setupDb() {
        $filePattern = __DIR__ ."/../../app/code/community/Bolt/Boltpay/sql/bolt_boltpay_setup/*.php";
        $dbSetupScripts =
            glob(
                $filePattern
            )
        ;
        foreach($dbSetupScripts as $fileName) {
            require_once $fileName;
        }
    }
}

$setupModel = new BoltDbSetup('core_setup');
$setupModel->setupDb();