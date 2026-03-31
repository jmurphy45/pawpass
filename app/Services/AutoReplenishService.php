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
     * Synchronously charge using the per-dog configured package.
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

        return $this->charge($dog, $package, $tenant);
    }

    /**
     * Synchronously charge using an explicitly provided package (tenant-level default).
     * Returns true if credits were issued, false on any failure or missing config.
     */
    public function triggerForPackage(Dog $dog, Package $package): bool
    {
        $customer = $dog->customer;

        if (! $customer?->stripe_payment_method_id) {
            return false;
        }

        $tenant = $dog->tenant;

        if (! $tenant?->stripe_account_id) {
            return false;
        }

        return $this->charge($dog, $package, $tenant);
    }

    /**
     * Asynchronous trigger (fire-and-forget via webhook).
     */
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

        // Idempotency guard: skip if a pending auto-replenish order was created recently
        $recentPending = Order::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->whereHas('orderDogs', fn ($q) => $q->where('dog_id', $dog->id))
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentPending) {
            return;
        }

        $subtotalCents = (int) round((float) $package->price * 100);
        $feePct        = $tenant->effectivePlatformFeePct($subtotalCents);
        $feeCents      = (int) round($subtotalCents * $feePct / 100);

        [$taxAmountCents, $taxCalcId] = $this->resolveTax($subtotalCents, $tenant, $package);
        $totalCents = $subtotalCents + $taxAmountCents;

        $order = DB::transaction(function () use ($dog, $customer, $package, $tenant, $feePct, $subtotalCents, $taxAmountCents, $taxCalcId, $totalCents) {
            $order = Order::create([
                'tenant_id'          => $tenant->id,
                'customer_id'        => $customer->id,
                'package_id'         => $package->id,
                'status'             => 'pending',
                'total_amount'       => $totalCents / 100,
                'subtotal_cents'     => $subtotalCents,
                'tax_amount_cents'   => $taxAmountCents,
                'stripe_tax_calc_id' => $taxCalcId,
                'platform_fee_pct'   => $feePct,
            ]);

            $order->orderDogs()->create([
                'dog_id'         => $dog->id,
                'credits_issued' => 0,
            ]);

            return $order;
        });

        $metadata = [
            'order_id'       => $order->id,
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'dog_ids'        => $dog->id,
            'auto_replenish' => 'true',
        ];

        if ($taxCalcId) {
            $metadata['tax_calculation_id'] = $taxCalcId;
        }

        try {
            $intent = $this->stripe->createPaymentIntent(
                amountCents: $totalCents,
                currency: 'usd',
                stripeAccountId: $tenant->stripe_account_id,
                applicationFeeCents: $feeCents,
                metadata: $metadata,
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
                'unit_price_cents' => $subtotalCents,
                'sort_order'       => 0,
            ]);

            $order->payments()->create([
                'tenant_id'    => $tenant->id,
                'stripe_pi_id' => $intent->id,
                'amount_cents' => $totalCents,
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

    /**
     * Core synchronous charge logic shared by triggerSync() and triggerForPackage().
     */
    private function charge(Dog $dog, Package $package, \App\Models\Tenant $tenant): bool
    {
        $customer      = $dog->customer;
        $subtotalCents = (int) round((float) $package->price * 100);
        $feePct        = $tenant->effectivePlatformFeePct($subtotalCents);
        $feeCents      = (int) round($subtotalCents * $feePct / 100);

        [$taxAmountCents, $taxCalcId] = $this->resolveTax($subtotalCents, $tenant, $package);
        $totalCents = $subtotalCents + $taxAmountCents;

        $order = DB::transaction(function () use ($dog, $customer, $package, $tenant, $feePct, $subtotalCents, $taxAmountCents, $taxCalcId, $totalCents) {
            $order = Order::create([
                'tenant_id'          => $tenant->id,
                'customer_id'        => $customer->id,
                'package_id'         => $package->id,
                'status'             => 'pending',
                'total_amount'       => $totalCents / 100,
                'subtotal_cents'     => $subtotalCents,
                'tax_amount_cents'   => $taxAmountCents,
                'stripe_tax_calc_id' => $taxCalcId,
                'platform_fee_pct'   => $feePct,
            ]);

            $order->orderDogs()->create([
                'dog_id'         => $dog->id,
                'credits_issued' => 0,
            ]);

            return $order;
        });

        $metadata = [
            'order_id'       => $order->id,
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'dog_ids'        => $dog->id,
            'auto_replenish' => 'true',
        ];

        if ($taxCalcId) {
            $metadata['tax_calculation_id'] = $taxCalcId;
        }

        try {
            $intent = $this->stripe->createPaymentIntent(
                amountCents: $totalCents,
                currency: 'usd',
                stripeAccountId: $tenant->stripe_account_id,
                applicationFeeCents: $feeCents,
                metadata: $metadata,
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

            DB::transaction(function () use ($order, $dog, $package, $tenant, $intent, $subtotalCents, $totalCents) {
                $order->lineItems()->create([
                    'tenant_id'        => $tenant->id,
                    'description'      => $package->name,
                    'quantity'         => 1,
                    'unit_price_cents' => $subtotalCents,
                    'sort_order'       => 0,
                ]);

                $order->payments()->create([
                    'tenant_id'    => $tenant->id,
                    'stripe_pi_id' => $intent->id,
                    'amount_cents' => $totalCents,
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

    /**
     * Calculate tax when the feature flag is active and billing address is available.
     * Returns [taxAmountCents, taxCalcId|null].
     */
    private function resolveTax(int $subtotalCents, \App\Models\Tenant $tenant, Package $package): array
    {
        if (! $tenant->tax_collection_enabled) {
            return [0, null];
        }

        $postalCode = $tenant->billing_address['postal_code'] ?? null;

        if (! $postalCode || ! $tenant->stripe_account_id) {
            return [0, null];
        }

        try {
            $calculation = $this->stripe->calculateTax(
                subtotalCents: $subtotalCents,
                currency: 'usd',
                stripeAccountId: $tenant->stripe_account_id,
                customerAddress: [
                    'postal_code' => $postalCode,
                    'country'     => $tenant->billing_address['country'] ?? 'US',
                ],
                reference: (string) $package->id,
            );

            return [$calculation->tax_amount_exclusive, $calculation->id];
        } catch (\Throwable $e) {
            Log::warning('AutoReplenish: tax calculation failed, proceeding without tax', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return [0, null];
        }
    }
}
