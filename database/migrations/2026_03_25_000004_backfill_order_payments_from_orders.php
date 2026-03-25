<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        DB::table('orders')
            ->whereNotNull('stripe_pi_id')
            ->orderBy('created_at')
            ->chunk(500, function ($orders) use ($now) {
                $inserts = [];

                foreach ($orders as $order) {
                    $inserts[] = [
                        'id'                    => (string) Str::ulid(),
                        'tenant_id'             => $order->tenant_id,
                        'order_id'              => $order->id,
                        'stripe_pi_id'          => $order->stripe_pi_id,
                        'stripe_payment_method' => $order->stripe_payment_method,
                        'amount_cents'          => (int) round((float) $order->total_amount * 100),
                        'type'                  => 'full',
                        'status'                => $order->status === 'refunded' ? 'refunded' : ($order->status === 'paid' || $order->status === 'partially_refunded' ? 'paid' : $order->status),
                        'paid_at'               => $order->paid_at,
                        'refunded_at'           => $order->refunded_at,
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ];
                }

                if ($inserts) {
                    DB::table('order_payments')->insert($inserts);
                }
            });
    }

    public function down(): void
    {
        // Remove backfilled payments (those linked to orders that still have stripe_pi_id)
        DB::table('order_payments')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.id', 'order_payments.order_id')
                    ->whereNotNull('orders.stripe_pi_id');
            })
            ->delete();
    }
};
