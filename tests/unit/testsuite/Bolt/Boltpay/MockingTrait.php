<?php

trait Bolt_Boltpay_MockingTrait {

    /**
     * Returns the prototype of the focus class type which is then used to generally call
     * @see PHPUnit_Framework_MockObject_MockBuilder::setMethods and
     * @see PHPUnit_Framework_MockObject_MockBuilder::getMock
     *
     * @param bool   $preInitializeDefaults     Sets whether common settings like disabling constructor calling and
     *                                          and cloning is applied.  This is false by default
     *
     * @return PHPUnit_Framework_MockObject_MockBuilder
     *
     * @throws Exception if the variable $testClassName is not specified in the trait using class
     */
    protected function getTestClassPrototype( $preInitializeDefaults = false ) {

        if ( empty(@$this->testClassName) ) {
            throw new Exception('Variable $testClassName must be defined in '.get_class($this) );
        }

        /** @var  PHPUnit_Framework_MockObject_MockBuilder $mockBuilder */
        return $this->getClassPrototype($this->testClassName, $preInitializeDefaults);
    }

    /**
     * Returns the prototype of the specified class type which is then used to generally call
     * @see PHPUnit_Framework_MockObject_MockBuilder::setMethods and
     * @see PHPUnit_Framework_MockObject_MockBuilder::getMock
     *
     * @param string $className                 The name of the class to be prototyped
     * @param bool   $preInitializeDefaults     Sets whether common settings like disabling constructor calling and
     *                                          and cloning is applied.  This is true by default
     *
     * @return PHPUnit_Framework_MockObject_MockBuilder
     */
    public function getClassPrototype( $className, $preInitializeDefaults = true ) {

        /** @var  PHPUnit_Framework_MockObject_MockBuilder $mockBuilder */
        $mockBuilder = $this->getMockBuilder($className);

        if ($preInitializeDefaults) {
            $mockBuilder
                ->disableOriginalConstructor()
                ->disableOriginalClone()
                ->disableArgumentCloning();
        }

        return $mockBuilder;
    }
}