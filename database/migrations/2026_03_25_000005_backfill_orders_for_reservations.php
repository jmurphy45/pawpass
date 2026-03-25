<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        // Only process reservations that have payment activity
        DB::table('reservations')
            ->where(function ($q) {
                $q->where('deposit_amount_cents', '>', 0)
                    ->orWhereNotNull('checkout_pi_id');
            })
            ->orderBy('created_at')
            ->chunk(200, function ($reservations) use ($now) {
                foreach ($reservations as $reservation) {
                    // Determine nights charged
                    $checkoutAt = $reservation->actual_checkout_at
                        ? new \DateTime($reservation->actual_checkout_at)
                        : new \DateTime($reservation->ends_at);

                    $startsAt = new \DateTime($reservation->starts_at);
                    $nights   = max(1, $startsAt->diff($checkoutAt)->days);

                    // Fetch addons total
                    $addonsTotal = (int) DB::table('reservation_addons')
                        ->where('reservation_id', $reservation->id)
                        ->sum(DB::raw('unit_price_cents * quantity'));

                    $depositCents  = (int) ($reservation->deposit_amount_cents ?? 0);
                    $nightlyTotal  = $nights * (int) ($reservation->nightly_rate_cents ?? 0);
                    $checkoutCents = (int) ($reservation->checkout_charge_cents ?? 0);
                    $totalCents    = $depositCents + $checkoutCents;
                    $totalAmount   = number_format($totalCents / 100, 2, '.', '');

                    // Map reservation status → order status
                    $orderStatus = match ($reservation->status) {
                        'checked_out' => $totalCents > 0 ? 'paid' : 'paid',
                        'cancelled'   => $reservation->deposit_refunded_at ? 'refunded' : 'refunded',
                        default       => 'pending',
                    };

                    $orderId = (string) Str::ulid();

                    // Create order
                    DB::table('orders')->insert([
                        'id'               => $orderId,
                        'tenant_id'        => $reservation->tenant_id,
                        'customer_id'      => $reservation->customer_id,
                        'package_id'       => null,
                        'reservation_id'   => $reservation->id,
                        'type'             => 'boarding',
                        'status'           => $orderStatus,
                        'total_amount'     => $totalAmount,
                        'platform_fee_pct' => 5.00,
                        'idempotency_key'  => null,
                        'created_at'       => $reservation->created_at ?? $now,
                        'updated_at'       => $now,
                    ]);

                    // Create line item: nightly rate
                    if ($reservation->nightly_rate_cents) {
                        DB::table('order_line_items')->insert([
                            'id'               => (string) Str::ulid(),
                            'tenant_id'        => $reservation->tenant_id,
                            'order_id'         => $orderId,
                            'description'      => 'Nightly Rate × '.$nights,
                            'quantity'         => $nights,
                            'unit_price_cents' => (int) $reservation->nightly_rate_cents,
                            'sort_order'       => 0,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                    }

                    // Create line items for addons
                    $addons = DB::table('reservation_addons')
                        ->join('addon_types', 'reservation_addons.addon_type_id', '=', 'addon_types.id')
                        ->where('reservation_addons.reservation_id', $reservation->id)
                        ->select('addon_types.name', 'reservation_addons.quantity', 'reservation_addons.unit_price_cents')
                        ->get();

                    foreach ($addons as $i => $addon) {
                        DB::table('order_line_items')->insert([
                            'id'               => (string) Str::ulid(),
                            'tenant_id'        => $reservation->tenant_id,
                            'order_id'         => $orderId,
                            'description'      => $addon->name,
                            'quantity'         => $addon->quantity,
                            'unit_price_cents' => $addon->unit_price_cents,
                            'sort_order'       => $i + 1,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                    }

                    // Create deposit payment
                    if ($depositCents > 0 && $reservation->stripe_pi_id) {
                        $depositStatus = match (true) {
                            ! is_null($reservation->deposit_refunded_at) => 'refunded',
                            ! is_null($reservation->deposit_captured_at) => 'paid',
                            default                                       => 'authorized',
                        };

                        DB::table('order_payments')->insert([
                            'id'               => (string) Str::ulid(),
                            'tenant_id'        => $reservation->tenant_id,
                            'order_id'         => $orderId,
                            'stripe_pi_id'     => $reservation->stripe_pi_id,
                            'amount_cents'     => $depositCents,
                            'type'             => 'deposit',
                            'status'           => $depositStatus,
                            'paid_at'          => $reservation->deposit_captured_at,
                            'refunded_at'      => $reservation->deposit_refunded_at,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ]);
                    }

                    // Create checkout payment
                    if ($checkoutCents > 0 && $reservation->checkout_pi_id) {
                        DB::table('order_payments')->insert([
                            'id'           => (string) Str::ulid(),
                            'tenant_id'    => $reservation->tenant_id,
                            'order_id'     => $orderId,
                            'stripe_pi_id' => $reservation->checkout_pi_id,
                            'amount_cents' => $checkoutCents,
                            'type'         => 'balance',
                            'status'       => 'paid',
                            'paid_at'      => $reservation->actual_checkout_at ?? $now,
                            'refunded_at'  => null,
                            'created_at'   => $now,
                            'updated_at'   => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Remove orders created for reservations
        DB::table('orders')->whereNotNull('reservation_id')->delete();
    }
};
