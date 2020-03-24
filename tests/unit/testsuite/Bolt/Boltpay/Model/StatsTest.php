<?php

/**
 * @coversDefaultClass Bolt_Boltpay_Model_Stats
 */
class Bolt_Boltpay_Model_StatsTest extends PHPUnit_Framework_TestCase
{
    use Bolt_Boltpay_MockingTrait;

    /** @var string Name of tested class */
    protected $testClassName = 'Bolt_Boltpay_Model_Stats';

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Bolt_Boltpay_Model_Stats
     */
    private $currentMock;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $adapterMock;

    /**
     * Setup test dependencies
     *
     * @throws Exception from mocking trait if test class name is not specified
     */
    protected function setUp()
    {
        $this->currentMock = $this->getTestClassPrototype()->setMethods(array('_getReadAdapter', '_init', 'getTable'))
            ->getMock();
        $this->adapterMock = $this->getClassPrototype('Magento_Db_Adapter_Pdo_Mysql')
            ->setMethods(array('select', 'fetchRow'))->getMock();
        $this->currentMock->method('_getReadAdapter')->willReturn($this->adapterMock);
    }

    /**
     * @test
     * that getAggregatedData executes select query with expected parameters and returns its result
     *
     * @covers ::getAggregatedData
     */
    public function getAggregatedData_always_executesSelectAndReturnsResult()
    {
        $result = array(
            'min_quote_id' => 1,
            'max_quote_id' => 100,
            'order_count'  => 100,
        );
        $this->currentMock->expects($this->once())->method('getTable')->with('sales/order')
            ->willReturn('sales_flat_order');
        $selectMock = $this->getClassPrototype('Varien_Db_Select')->setMethods(array('from', 'where'))
            ->getMock();
        $this->adapterMock->expects($this->once())->method('select')->willReturn($selectMock);
        $selectMock->expects($this->once())->method('from')->with(
            array('order' => 'sales_flat_order'),
            array(
                'min_quote_id' => new Zend_Db_Expr('MIN(order.quote_id)'),
                'max_quote_id' => new Zend_Db_Expr('MAX(order.quote_id)'),
                'order_count'  => new Zend_Db_Expr('COUNT(order.entity_id)'),
            )
        )->willReturnSelf();
        $selectMock->expects($this->exactly(3))->method('where')
            ->withConsecutive(
                array('created_at > ?', '2016-10-01'),
                array('created_at < ?', '2016-11-01'),
                array('state NOT IN (\'canceled\', \'closed\')')
            )->willReturnSelf();
        $this->adapterMock->expects($this->once())->method('fetchRow')->with($selectMock)->willReturn($result);

        $this->assertEquals($result, $this->currentMock->getAggregatedData());
    }

    /**
     * @test
     * that Mage internal constructor executes _init method with parameters
     *
     * @covers ::_construct
     */
    public function _construct_always_executesInitWithParameters()
    {
        $this->currentMock->expects($this->once())->method('_init')->with('sales/order', 'entity_id');
        Bolt_Boltpay_TestHelper::callNonPublicFunction(
            $this->currentMock,
            '_construct'
        );
    }
}
