<?php

/**
 * Used by {@see Bolt_Boltpay_TestHelper to replace Mage::_config}
 */
class Bolt_Boltpay_ConfigProxy
{
    /** @var Mage_Core_Model_Config|null Config object from Mage prior to initialization, used for restoration */
    private $_originalConfig = null;

    /** @var array of model instances to be returned instead of new instances */
    private $_stubbedModels = array();

    /**
     * Bolt_Boltpay_ConfigProxy constructor.
     *
     * @throws ReflectionException if Mage class doesn't have _config property
     */
    public function __construct()
    {
        $this->_originalConfig = Mage::getConfig();
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            'Mage',
            '_config',
            $this
        );
    }

    /**
     * Restore original on object destruct
     */
    public function __destruct()
    {
        $this->restoreOriginal();
    }

    /**
     * Restore original config object to resume normal operation and empty array of stubbed models
     *
     * @throws ReflectionException if Mage class doesn't have _config property
     */
    public function restoreOriginal()
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            'Mage',
            '_config',
            $this->_originalConfig
        );
        $this->_stubbedModels = array();
    }

    /**
     * Forward method calls we are not intercepting to original config
     *
     * @param string $name of the method called
     * @param array $arguments enumerated array containing the parameters passed to the method
     * @return mixed result of the original method call
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->_originalConfig, $name), $arguments);
    }

    /**
     * Forward static method calls to original config
     *
     * @param string $name of the method called
     * @param array $arguments enumerated array containing the parameters passed to the method
     * @return mixed result of the original method call
     */
    public static function __callStatic($name, $arguments)
    {
        return forward_static_call_array(array('Mage_Core_Model_Config', $name), $arguments);
    }

    /**
     * Substitutes return values of Mage::getModel
     * All following calls to Mage::getModel($name) will return $instance
     *
     * @param string $name unique Magento identification used as parameter for Mage::getModel
     * @param mixed  $instance to return on call to Mage::getModel
     *
     * @throws ReflectionException  if the Mage class does not exist.
     */
    public function stubModel($name, $instance)
    {
        if (Bolt_Boltpay_TestHelper::getNonPublicProperty('Mage', '_config') !== $this) {
            $this->__construct();
        }
        $this->_stubbedModels[$name] = $instance;
    }

    /**
     * Restores the original value returned by Mage::getModel($name)
     *
     * @param string $name unique Magento identification used as parameter for Mage::getModel
     */
    public function restoreModel($name)
    {
        unset($this->_stubbedModels[$name]);
    }

    /**
     * If model class exists in $_stubbedModels, return from there
     * Otherwise retrieve from original config
     *
     * @param string $modelClass identifier
     * @param array  $constructArguments for model instantiation
     * @return false|Mage_Core_Model_Abstract|mixed
     */
    public function getModelInstance($modelClass = '', $constructArguments = array())
    {
        if (key_exists($modelClass, $this->_stubbedModels)) {
            return $this->_stubbedModels[$modelClass];
        }

        return $this->_originalConfig->getModelInstance($modelClass, $constructArguments);
    }
}
