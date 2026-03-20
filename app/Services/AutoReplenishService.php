<?php

namespace App\Services;

use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoReplenishService
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly NotificationService $notifications,
    ) {}

    public function trigger(Dog $dog): void
    {
        if (! $dog->auto_replenish_enabled) {
            return;
        }

        $customer = $dog->customer;

        if (! $customer?->stripe_payment_method_id) {
            return;
        }

        if (! $dog->auto_replenish_package_id) {
            return;
        }

        $package = Package::find($dog->auto_replenish_package_id);

        if (! $package) {
            return;
        }

        $tenant = $dog->tenant;

        if (! $tenant?->stripe_account_id) {
            return;
        }

        $amountCents = (int) round((float) $package->price * 100);
        $feePct      = (float) ($tenant->platform_fee_pct ?? 5);
        $feeCents    = (int) round($amountCents * $feePct / 100);

        $order = DB::transaction(function () use ($dog, $customer, $package, $tenant) {
            $order = Order::create([
                'tenant_id'        => $tenant->id,
                'customer_id'      => $customer->id,
                'package_id'       => $package->id,
                'status'           => 'pending',
                'total_amount'     => $package->price,
                'platform_fee_pct' => $tenant->platform_fee_pct,
            ]);

            $order->orderDogs()->create([
                'dog_id'         => $dog->id,
                'credits_issued' => 0,
            ]);

            return $order;
        });

        try {
            $intent = $this->stripe->createPaymentIntent(
                amountCents: $amountCents,
                currency: 'usd',
                stripeAccountId: $tenant->stripe_account_id,
                applicationFeeCents: $feeCents,
                metadata: [
                    'order_id'       => $order->id,
                    'tenant_id'      => $tenant->id,
                    'customer_id'    => $customer->id,
                    'package_id'     => $package->id,
                    'dog_ids'        => $dog->id,
                    'auto_replenish' => 'true',
                ],
                stripeCustomerId: $customer->stripe_customer_id,
                confirm: true,
                offSession: true,
                paymentMethodId: $customer->stripe_payment_method_id,
            );

            $order->update(['stripe_pi_id' => $intent->id]);
        } catch (\Throwable $e) {
            Log::error('AutoReplenish: PaymentIntent failed', [
                'dog_id'  => $dog->id,
                'order_id' => $order->id,
                'error'   => $e->getMessage(),
            ]);

            $order->update(['status' => 'failed']);

            $userId = $customer->user_id;
            if ($userId) {
                $this->notifications->dispatch(
                    'auto_replenish.failed',
                    $tenant->id,
                    $userId,
                    ['dog_id' => $dog->id, 'package_id' => $package->id],
                );
            }
        }
    }
}
