<?php

namespace App\Services;

use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\OrderLineItem;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DogCreditService;

class AutoReplenishService
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly NotificationService $notifications,
        private readonly DogCreditService $credits,
    ) {}

    /**
     * Synchronously charge the customer and issue credits during check-in.
     * Returns true if credits were issued, false on any failure or missing config.
     */
    public function triggerSync(Dog $dog): bool
    {
        if (! $dog->auto_replenish_enabled) {
            return false;
        }

        $customer = $dog->customer;

        if (! $customer?->stripe_payment_method_id) {
            return false;
        }

        if (! $dog->auto_replenish_package_id) {
            return false;
        }

        $package = Package::find($dog->auto_replenish_package_id);

        if (! $package) {
            return false;
        }

        $tenant = $dog->tenant;

        if (! $tenant?->stripe_account_id) {
            return false;
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
                paymentMethodTypes: ['card'],
            );

            if ($intent->status !== 'succeeded') {
                $order->update(['status' => 'failed']);

                return false;
            }

            DB::transaction(function () use ($order, $dog, $package, $tenant, $intent, $amountCents) {
                $order->lineItems()->create([
                    'tenant_id'        => $tenant->id,
                    'description'      => $package->name,
                    'quantity'         => 1,
                    'unit_price_cents' => $amountCents,
                    'sort_order'       => 0,
                ]);

                $order->payments()->create([
                    'tenant_id'    => $tenant->id,
                    'stripe_pi_id' => $intent->id,
                    'amount_cents' => $amountCents,
                    'type'         => 'full',
                    'status'       => 'paid',
                    'paid_at'      => now(),
                ]);

                $order->update(['status' => 'paid']);

                $this->credits->issueFromOrder($order, $dog);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('AutoReplenish (sync): PaymentIntent failed', [
                'dog_id'   => $dog->id,
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            $order->update(['status' => 'failed']);

            return false;
        }
    }

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
                paymentMethodTypes: ['card'],
            );

            $order->lineItems()->create([
                'tenant_id'        => $tenant->id,
                'description'      => $package->name,
                'quantity'         => 1,
                'unit_price_cents' => $amountCents,
                'sort_order'       => 0,
            ]);

            $order->payments()->create([
                'tenant_id'    => $tenant->id,
                'stripe_pi_id' => $intent->id,
                'amount_cents' => $amountCents,
                'type'         => 'full',
                'status'       => 'pending',
            ]);
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
