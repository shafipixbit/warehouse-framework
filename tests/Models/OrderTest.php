<?php

namespace Just\Warehouse\Tests\Model;

use Just\Warehouse\Models\Order;
use Just\Warehouse\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Just\Warehouse\Events\OrderLineCreated;
use Just\Warehouse\Exceptions\InvalidGtinException;
use Just\Warehouse\Exceptions\InvalidOrderNumberException;

class OrderTest extends TestCase
{
    /** @test */
    public function it_uses_the_warehouse_database_connection()
    {
        $order = factory(Order::class)->make();

        $this->assertEquals('warehouse', $order->getConnectionName());
    }

    /** @test */
    public function it_casts_the_meta_column_to_an_array()
    {
        $order = factory(Order::class)->make();

        $this->assertTrue($order->hasCast('meta', 'array'));
    }

    /** @test */
    public function it_has_order_lines()
    {
        $order = factory(Order::class)->make();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $order->lines);
    }

    /** @test */
    public function it_gets_a_created_status()
    {
        $order = factory(Order::class)->create();

        $this->assertEquals('created', $order->fresh()->status);
    }

    /** @test */
    public function creating_an_order_without_an_order_number_throws_an_exception()
    {
        try {
            $order = factory(Order::class)->create([
                'order_number' => '',
            ]);
        } catch (InvalidOrderNumberException $e) {
            $this->assertEquals('The given data was invalid.', $e->getMessage());
            $this->assertCount(0, Order::all());

            return;
        }

        $this->fail('Creating an order without an order number succeeded.');
    }

    /** @test */
    public function it_can_add_an_order_line()
    {
        Event::fake(OrderLineCreated::class);
        $order = factory(Order::class)->create();

        $line = $order->addLine('1300000000000');

        $this->assertCount(1, $order->lines);
        $this->assertEquals($order->id, $line->order_id);
        $this->assertEquals('1300000000000', $line->gtin);
        Event::assertDispatched(OrderLineCreated::class, function ($event) use ($line) {
            return $event->line->is($line);
        });
    }

    /** @test */
    public function adding_an_order_line_with_an_invalid_gtin_throws_an_exception()
    {
        $order = factory(Order::class)->create();

        try {
            $order->addLine('invalid-gtin');
        } catch (InvalidGtinException $e) {
            $this->assertEquals('The given data was invalid.', $e->getMessage());
            $this->assertCount(0, $order->lines);

            return;
        }

        $this->fail('Adding an order line succeeded with an invalid gtin.');
    }
}
