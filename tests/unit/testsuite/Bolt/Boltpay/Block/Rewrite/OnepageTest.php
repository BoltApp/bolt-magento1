<?php

class Bolt_Boltpay_Block_Rewrite_OnepageTest extends PHPUnit_Framework_TestCase
{
    private $app = null;
    private $onepageRewriteClass = null;

    public function setUp() 
    {
        $this->app = Mage::app('default');
        $this->onepageRewriteClass = Mage::getConfig()->getBlockClassName('checkout/onepage');
    }

    public function testOnepageCheckoutBlockIsOverridenCorrectly() 
    {
        $this->assertEquals('Bolt_Boltpay_Block_Rewrite_Onepage', $this->onepageRewriteClass);
    }

    public function testStepsWhenSkipPaymentIsTrue() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $onepageRewrite = new $this->onepageRewriteClass;
        $this->assertEquals(
            array(
                'login',
                'billing',
                'shipping',
                'shipping_method',
                'review'
            ),
            array_keys($onepageRewrite->getSteps())
        );
    }

    public function testStepsWhenSkipPaymentIsFalse() 
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 0);
        $onepageRewrite = new $this->onepageRewriteClass;
        $this->assertEquals(
            array(
                'login',
                'billing',
                'shipping',
                'shipping_method',
                'payment',
                'review'
            ),
            array_keys($onepageRewrite->getSteps())
        );
    }
}
