<?php

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_payment_can_be_created(): void
    {
        $order = Order::factory()->create();

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => 'full',
            'status'       => 'paid',
            'amount_cents' => 4900,
        ]);

        $this->assertDatabaseHas('order_payments', [
            'id'          => $payment->id,
            'order_id'    => $order->id,
            'amount_cents' => 4900,
            'type'        => 'full',
            'status'      => 'paid',
        ]);
    }

    public function test_order_payment_belongs_to_tenant_scope(): void
    {
        $tenant1 = Tenant::factory()->create(['slug' => 't1op', 'status' => 'active']);
        $tenant2 = Tenant::factory()->create(['slug' => 't2op', 'status' => 'active']);

        $package = Package::factory()->create(['tenant_id' => $tenant1->id]);
        $order1  = Order::factory()->create(['tenant_id' => $tenant1->id, 'package_id' => $package->id]);
        $order2  = Order::factory()->create(['tenant_id' => $tenant2->id]);

        OrderPayment::factory()->forOrder($order1)->create();
        OrderPayment::factory()->forOrder($order2)->create();

        app()->instance('current.tenant.id', $tenant1->id);

        $this->assertCount(1, OrderPayment::all());

        app()->forgetInstance('current.tenant.id');
    }

    public function test_order_has_many_payments(): void
    {
        $order = Order::factory()->create();

        OrderPayment::factory()->forOrder($order)->deposit()->create(['amount_cents' => 5000]);
        OrderPayment::factory()->forOrder($order)->balance()->create(['amount_cents' => 3000]);

        $this->assertCount(2, $order->payments);
    }

    public function test_order_has_many_line_items(): void
    {
        $order = Order::factory()->create();

        OrderLineItem::factory()->forOrder($order)->create(['description' => 'Nightly Rate × 2', 'unit_price_cents' => 5000]);
        OrderLineItem::factory()->forOrder($order)->create(['description' => 'Bath Add-on', 'unit_price_cents' => 1500, 'sort_order' => 1]);

        $this->assertCount(2, $order->lineItems);
    }

    public function test_order_line_item_total_cents(): void
    {
        $order = Order::factory()->create();

        $item = OrderLineItem::factory()->forOrder($order)->create([
            'quantity'         => 3,
            'unit_price_cents' => 5000,
        ]);

        $this->assertEquals(15000, $item->totalCents());
    }

    public function test_reservation_has_one_order(): void
    {
        $reservation = Reservation::factory()->create();
        $order       = Order::factory()->create([
            'tenant_id'      => $reservation->tenant_id,
            'customer_id'    => $reservation->customer_id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
        ]);

        $this->assertTrue($reservation->order->is($order));
    }

    public function test_order_belongs_to_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        $order       = Order::factory()->create([
            'tenant_id'      => $reservation->tenant_id,
            'customer_id'    => $reservation->customer_id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
        ]);

        $this->assertTrue($order->reservation->is($reservation));
    }
}
