<?php

namespace EventCentric\Protection\Tests\AggregateRoot;

use EventCentric\Protection\Tests\Sample\Order;
use EventCentric\Protection\Tests\Sample\OrderId;
use EventCentric\Protection\Tests\Sample\ProductId;
use PHPUnit_Framework_TestCase;

final class EventSourcingTest extends PHPUnit_Framework_TestCase
{
    /** @var Order */
    private $order;

    protected function setUp()
    {
        parent::setUp();
        $this->order = Order::orderProduct(OrderId::generate(), ProductId::generate(), 100);

    }

    /**
     * @test
     */
    public function it_should_track_changes()
    {
        $this->assertTrue(
            $this->order->hasChanges()
        );
    }

    /**
     * @test
     */
    public function it_should_clear_changes()
    {
        $this->order->clearChanges();

        $this->assertFalse(
            $this->order->hasChanges()
        );

    }

    /**
     * @test
     */
    public function it_should_record_new_changes()
    {
        $this->order->clearChanges();
        $this->order->pay(50);
        $this->assertCount(1, $this->order->getChanges());
    }
}
 