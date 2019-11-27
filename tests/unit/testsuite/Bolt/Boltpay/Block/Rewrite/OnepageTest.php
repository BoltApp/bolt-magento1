<?php

/**
 * Class Bolt_Boltpay_Block_Rewrite_OnepageTest
 */
class Bolt_Boltpay_Block_Rewrite_OnepageTest extends PHPUnit_Framework_TestCase
{
    private $app = null;

    /**
     * @var string
     */
    private $onepageRewriteClass = null;

    public function setUp() 
    {
        $this->app = Mage::app('default');
        $this->onepageRewriteClass = Mage::getConfig()->getBlockClassName('checkout/onepage');
    }

    /**
     * @inheritdoc
     */
    public function testOnepageCheckoutBlockIsOverridenCorrectly()
    {
        $this->assertEquals('Bolt_Boltpay_Block_Rewrite_Onepage', $this->onepageRewriteClass);
    }

    /**
     * @inheritdoc
     */
    public function testStepsWhenSkipPaymentIsTrue()
    {
        $this->markTestIncomplete('Need to FIX');
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 1);
        $onepageRewrite = new $this->onepageRewriteClass;

        $result = array(
            'login',
            'billing',
            'shipping',
            'shipping_method',
            'review'
        );

        $this->assertEquals(
            $result,
            array_keys($onepageRewrite->getSteps())
        );
    }

    /**
     * @inheritdoc
     */
    public function testStepsWhenSkipPaymentIsFalse()
    {
        $this->app->getStore()->setConfig('payment/boltpay/skip_payment', 0);
        $onepageRewrite = new $this->onepageRewriteClass;

        $result = array(
            'login',
            'billing',
            'shipping',
            'shipping_method',
            'payment',
            'review'
        );

        $this->assertEquals(
            $result,
            array_keys($onepageRewrite->getSteps())
        );
    }
}
