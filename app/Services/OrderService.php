<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    /**
     * Returns [taxAmountCents, taxCalcId|null].
     * Skips calculation when tax collection is disabled or required config is missing.
     */
    public function resolveTax(int $subtotalCents, Tenant $tenant, string $reference = 'order'): array
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
                    'country' => $tenant->billing_address['country'] ?? 'US',
                ],
                reference: $reference,
            );

            return [$calculation->tax_amount_exclusive, $calculation->id];
        } catch (\Throwable $e) {
            Log::warning('OrderService: tax calculation failed, proceeding without tax', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [0, null];
        }
    }

    /**
     * Create an order with tax resolved and merged into the attributes.
     * Pass base attributes without subtotal_cents/tax_amount_cents/total_amount —
     * this method computes all three and sets them on the created order.
     */
    public function create(array $attributes, Tenant $tenant, int $subtotalCents, string $taxReference = 'order'): Order
    {
        [$taxAmountCents, $taxCalcId] = $this->resolveTax($subtotalCents, $tenant, $taxReference);

        $totalCents = $subtotalCents + $taxAmountCents;

        return Order::create(array_merge($attributes, [
            'subtotal_cents' => $subtotalCents,
            'tax_amount_cents' => $taxAmountCents,
            'stripe_tax_calc_id' => $taxCalcId,
            'total_amount' => number_format($totalCents / 100, 2, '.', ''),
        ]));
    }
}
