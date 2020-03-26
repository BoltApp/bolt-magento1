<?php

class Bolt_Boltpay_TestHelper
{

    /** @var Bolt_Boltpay_TestHelper singular instance of */
    private static $_instance = null;

    /** @var Bolt_Boltpay_ConfigProxy instance of ConfigProxy used to stup Mage::getModel */
    private static $_configProxy = null;

    /** @var array of original values for substituted registry values */
    private static $_substitutedRegistryValues = array();

    /** @var array of original values for substituted configuration values */
    private static $_substitutedConfigurationValues = array();

    /**
     * @param $productId
     * @param $quantity
     * @return Mage_Checkout_Model_Cart
     * @throws Exception
     */
    public static function addProduct($productId, $quantity)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($productId);
        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');
        $param = array(
            'product' => $productId,
            'qty' => $quantity
        );
        $cart->addProduct($product, $param);
        $cart->save();

        return $cart;
    }

    /**
     * @param array $addressData
     * @return Mage_Checkout_Model_Type_Onepage
     * @throws Exception
     */
    public function addTestBillingAddress($addressData = array())
    {
        if (!count($addressData)) {
            $addressData = array(
                'firstname' => 'Luke',
                'lastname' => 'Skywalker',
                'street' => 'Sample Street 10',
                'city' => 'Los Angeles',
                'postcode' => '90014',
                'telephone' => '+1 867 345 123 5681',
                'country_id' => 'US',
                'region_id' => 12
            );
        }
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getBillingAddress()->addData($addressData);
        $checkout->getQuote()->getBillingAddress()->save();

        return $checkout;
    }

    public function addTestFlatRateShippingAddress($addressData, $paymentMethod)
    {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $shippingAddress = $checkout->getQuote()->getShippingAddress()->addData($addressData);
        Mage::app('default')->getStore()->setConfig('carriers/flatrate/active', 1);

        $shippingAddress
            ->setCollectShippingRates(true)
            ->setShippingMethod('flatrate_flatrate')
            ->collectShippingRates()
            ->setPaymentMethod($paymentMethod);
        $checkout->getQuote()->getShippingAddress()->save();
        return $checkout;
    }

    /**
     * @param $checkoutType
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public static function createCheckout($checkoutType)
    {
        Mage::unregister('_singleton/checkout/type_onepage');
        Mage::unregister('_singleton/checkout/cart');
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkoutSession = $checkout->getCheckout();
        $checkoutSession->clear();
        $checkout->initCheckout();
        $checkout->saveCheckoutMethod($checkoutType);

        return $checkout;
    }

    public function addPaymentToQuote($method)
    {
        $checkout = Mage::getSingleton('checkout/type_onepage');
        $checkout->getQuote()->getPayment()->importData(array('method' => $method));
        $checkout->getQuote()->getPayment()->save();
        $checkout->getQuote()->collectTotals()->save();

        return $checkout;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public static function getCheckoutQuote()
    {
        /** @var Mage_Checkout_Model_Type_Onepage $checkout */
        $checkout = Mage::getSingleton('checkout/type_onepage');

        return $checkout->getQuote();
    }

    /**
     * @return mixed
     */
    public function submitCart()
    {
        $checkoutQuote = $this->getCheckoutQuote();
        $service = Mage::getModel('sales/service_quote', $checkoutQuote);
        $service->submitAll();

        return $service->getOrder();
    }

    public function resetApp()
    {
        $_POST = array();
        $_REQUEST = array();
        $_GET = array();
        $this->app = Mage::app('default');
        $this->app->getStore()->resetConfig();
    }

    /**
     * @param $checkoutType
     * @param $jsonCart
     * @param $quote
     * @param $jsonHints
     * @return string
     */
    public function buildCartDataJs($checkoutType, $jsonCart, $quote, $jsonHints)
    {
        /* @var Bolt_Boltpay_Helper_Data $boltHelper */
        $boltHelper = Mage::helper('boltpay');
        $quote->setIsVirtual(false);

        $hintsTransformFunction = $boltHelper->getExtraConfig('hintsTransform');
        $configCallbacks = $boltHelper->getBoltCallbacks($checkoutType, $quote);

        return ("
            var \$hints_transform = $hintsTransformFunction;

            var get_json_cart = function() { return $jsonCart };
            var json_hints = \$hints_transform($jsonHints);
            var quote_id = '{$quote->getId()}';
            var order_completed = false;
            var do_checks = 1;

            window.BoltModal = BoltCheckout.configure(
                get_json_cart(),
                json_hints,
                $configCallbacks
        );");
    }

    /**
     * Gets the object reports that reports information about a class.
     *
     * @param mixed $class Either a string containing the name of the class to reflect, or an object.
     *
     * @return ReflectionClass  instance of the object used for inspection of the passed class
     * @throws ReflectionException if the class does not exist.
     */
    public static function getReflectedClass( $class ) {
        // When using Reflection on mocked classes, properties with original names can only be found on parent class
        if (is_subclass_of($class, 'PHPUnit_Framework_MockObject_MockObject')) {
            return new ReflectionClass(get_parent_class($class));
        }
        
        return new ReflectionClass( $class );
    }

    /**
     * Convenience method to call a private or protected function
     *
     * @param object|string     $objectOrClassName  The object of the function to be called.
     *                                              If the function is static, then a this should be a string of the class name.
     * @param string            $functionName       The name of the function to be invoked
     * @param array             $arguments          An indexed array of arguments to be passed to the function in the
     *                                              order that they are declared
     *
     * @return mixed    the value returned by the function
     *
     * @throws ReflectionException   if a specified object, class or method does not exist.
     */
    public static function callNonPublicFunction($objectOrClassName, $functionName, $arguments = [] ) {
        try {
            $reflectedMethod = self::getReflectedClass($objectOrClassName)->getMethod($functionName);
            $reflectedMethod->setAccessible(true);

            return $reflectedMethod->invokeArgs(is_object($objectOrClassName) ? $objectOrClassName : null, $arguments);
        } finally {
            if ( $reflectedMethod && ($reflectedMethod->isProtected() || $reflectedMethod->isPrivate()) ) {
                $reflectedMethod->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to get a private or protected property
     *
     * @param object|string $objectOrClassName  The object of the property to be retreived
     *                                          If the property is static, then a this should be a string of the class name.
     * @param string        $propertyName       The name of the property to be retrieved
     *
     * @return mixed    The value of the property
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function getNonPublicProperty($objectOrClassName, $propertyName ) {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            return $reflectedProperty->getValue( is_object($objectOrClassName) ? $objectOrClassName : null );

        } finally {
            if ( $reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate()) ) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }

    /**
     * Convenience method to set a private or protected property
     *
     * @param object|string $objectOrClassName  The object of the property to be set
     *                                          If the property is static, then a this should be a string of the class name.
     * @param string        $propertyName       The name of the property to be set
     * @param mixed         $value              The value to be set to the named property
     *
     * @throws ReflectionException  if a specified object, class or property does not exist.
     */
    public static function setNonPublicProperty($objectOrClassName, $propertyName, $value ) {
        try {
            $reflectedProperty = self::getReflectedClass($objectOrClassName)->getProperty($propertyName);
            $reflectedProperty->setAccessible(true);

            if (is_object($objectOrClassName)) {
                $reflectedProperty->setValue( $objectOrClassName, $value );
            } else {
                $reflectedProperty->setValue( $value );
            }
        } finally {
            if ( $reflectedProperty && ($reflectedProperty->isProtected() || $reflectedProperty->isPrivate()) ) {
                $reflectedProperty->setAccessible(false);
            }
        }
    }

    /**
     * Utilize garbage collection to restore original values
     *
     * @throws ReflectionException if unable to restore _config property of Mage class
     * @throws Mage_Core_Model_Store_Exception if unable to restore original config values due to missing store
     * @throws Mage_Core_Exception if unable to restore original registry value due to key already been defined
     */
    public function __destruct()
    {
        static::restoreOriginals();
    }

    /**
     * Restore original values that were substituted
     *
     * @throws ReflectionException via _configProxy if Mage doesn't have _config property
     * @throws Mage_Core_Model_Store_Exception if store doesn't  exist
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function restoreOriginals()
    {
        if (static::$_configProxy) {
            static::$_configProxy->restoreOriginal();
        }

        foreach (static::$_substitutedRegistryValues as $key => $value) {
            Mage::unregister($key);
            if ($value) {
                Mage::register($key, $value);
            }
        }

        static::$_substitutedRegistryValues = array();

        if (!empty(static::$_substitutedConfigurationValues)) {
            $store = Mage::app()->getStore();
            foreach (static::$_substitutedConfigurationValues as $path => $value) {
                $store->setConfig($path, $value);
            }

            static::$_substitutedConfigurationValues = array();
        }
    }

    /**
     * Clears Mage::registry while preserving initializedBenchmark value
     *
     * @throws Mage_Core_Exception if initializedBenchmark registry value is already set
     * @throws ReflectionException if Mage doesn't have _registry property
     */
    public static function clearRegistry()
    {
        $initializedBenchmark = Mage::registry('initializedBenchmark');
        self::setNonPublicProperty('Mage', '_registry', array());
        if ($initializedBenchmark) {
            Mage::register('initializedBenchmark', $initializedBenchmark);
        }
    }

    /**
     * Substitutes return values of Mage::getModel
     * All following calls to Mage::getModel($name) will return $instance
     *
     * @param string $name unique Magento identification string of the registered model class
     * @param mixed  $instance to return
     *
     * @throws ReflectionException from Config Proxy constructor if Mage class doesn't have _config property
     */
    public static function stubModel($name, $instance)
    {
        static::_init();
        if (!static::$_configProxy) {
            static::$_configProxy = new Bolt_Boltpay_ConfigProxy();
        }

        static::$_configProxy->stubModel($name, $instance);
    }

    /**
     * Restores the original value returned by Mage::getModel($name)
     *
     * @param string $name unique Magento identification string of the registered model class
     */
    public static function restoreModel($name)
    {
        if (!static::$_configProxy) {
            return;
        }

        static::$_configProxy->restoreModel($name);
    }

    /**
     * Substitutes return values of Mage::helper
     * All following calls to Mage::helper($name) will return $instance
     *
     * @param string $name unique Magento identification string of the registered helper model class
     * @param mixed  $instance to return
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function stubHelper($name, $instance)
    {
        static::stubRegistryValue('_helper/' . $name, $instance);
    }

    /**
     * Restores the original value returned by Mage::helper($name)
     *
     * @param string $name unique Magento identification string of the registered helper model class
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function restoreHelper($name)
    {
        static::restoreRegistryValue('_helper/' . $name);
    }

    /**
     * Substitutes return values of Mage::getSingleton
     * All following calls to Mage::getSingleton($name) will return $instance
     *
     * @param string $name unique Magento identification string of the registered model class
     * @param mixed  $instance to return
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function stubSingleton($name, $instance)
    {
        static::stubRegistryValue('_singleton/' . $name, $instance);
    }

    /**
     * Restores the original value returned by Mage::getSingleton($name)
     *
     * @param string $name unique Magento identification string of the registered model class
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function restoreSingleton($name)
    {
        static::restoreRegistryValue('_singleton/' . $name);
    }

    /**
     * Substitutes return values of Mage::registry
     * All following calls to Mage::registry($key) will return $value
     *
     * @param string $key of the registry entry
     * @param mixed  $value to set to registry
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function stubRegistryValue($key, $value)
    {
        static::_init();
        if (Mage::registry($key)) {
            if (!isset(static::$_substitutedRegistryValues[$key])) { # ensure original value is preserved on subsequent calls
                static::$_substitutedRegistryValues[$key] = Mage::registry($key);
            }

            Mage::unregister($key);
        } else {
            if (!isset(static::$_substitutedRegistryValues[$key])) { # ensure original value is preserved on subsequent calls
                static::$_substitutedRegistryValues[$key] = null;
            }
        }

        Mage::register($key, $value);
    }

    /**
     * Restores a given registry value to what is was before stubbing
     *
     * @param string $key of the registry entry
     *
     * @throws Mage_Core_Exception from registry if key already exists
     */
    public static function restoreRegistryValue($key)
    {
        Mage::unregister($key);
        if (isset(static::$_substitutedRegistryValues[$key])) {
            Mage::register($key, static::$_substitutedRegistryValues[$key]);
            unset(static::$_substitutedRegistryValues[$key]);
        }
    }

    /**
     * Substitutes return values of Mage::registry
     * All following calls to Mage::registry($key) will return $value
     *
     * @param string $path Magento unique key for the config value
     * @param mixed  $value New value to be stubbed
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public static function stubConfigValue($path, $value)
    {
        static::_init();
        $store = Mage::app()->getStore();
        if (!isset(static::$_substitutedConfigurationValues[$path])) { # ensure original value is preserved on subsequent calls
            static::$_substitutedConfigurationValues[$path] = $store->getConfig($path);
        }

        $store->setConfig($path, $value);
    }

    /**
     * Restores a given configuration value to what is was before stubbing
     *
     * @param string $path Magento unique key for the config value
     *
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     */
    public static function restoreConfigValue($path)
    {
        if (isset(static::$_substitutedConfigurationValues[$path])) {
            $store = Mage::app()->getStore();
            $store->setConfig($path, static::$_substitutedConfigurationValues[$path]);
            unset(static::$_substitutedConfigurationValues[$path]);
        }
    }

    /**
     * Set value of current store property
     *
     * @param string $name of the store property
     * @param mixed  $value to set
     * @throws Mage_Core_Model_Store_Exception if store doesn't exist
     * @throws ReflectionException if property we're trying to set doesn't exist in store object
     */
    public static function setStoreProperty($name, $value)
    {
        Bolt_Boltpay_TestHelper::setNonPublicProperty(
            Mage::app()->getStore(),
            $name,
            $value
        );
    }

    /**
     * Registers an instance of this class in order to utilize garbage collection to restore original values
     */
    private static function _init()
    {
        if (!static::$_instance) {
            static::$_instance = new Bolt_Boltpay_TestHelper();
        }
    }
}