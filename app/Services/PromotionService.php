<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;

class PromotionValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly int $discountCents,
        public readonly ?Promotion $promotion = null,
        public readonly string $message = '',
    ) {}
}

class PromotionService
{
    public function __construct(private readonly TenantEventService $events) {}

    /**
     * Validate a promo code against a purchase.
     *
     * @param  string  $code  Case-insensitive promo code
     * @param  Customer  $customer  The purchasing customer
     * @param  Package  $package  The package being purchased (for package-specific promos)
     * @param  int  $amountCents  Subtotal in cents before discount
     */
    public function validate(string $code, Customer $customer, Package $package, int $amountCents): PromotionValidationResult
    {
        $promo = Promotion::where('tenant_id', $customer->tenant_id)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->whereNull('deleted_at')
            ->first();

        if (! $promo) {
            return new PromotionValidationResult(false, 0, null, 'Invalid promo code.');
        }

        if (! $promo->is_active) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion is no longer active.');
        }

        if ($promo->isExpired()) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion has expired.');
        }

        if ($promo->isMaxedOut()) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion has reached its usage limit.');
        }

        if ($amountCents < $promo->min_purchase_cents) {
            return new PromotionValidationResult(false, 0, $promo, 'Minimum purchase not met for this promotion.');
        }

        // Applicability check: if applicable_type is set, it must match
        if ($promo->applicable_type !== null) {
            if ($promo->applicable_type === 'App\Models\Package' || $promo->applicable_type === Package::class) {
                if ($promo->applicable_id !== $package->id) {
                    return new PromotionValidationResult(false, 0, $promo, 'This promotion does not apply to the selected package.');
                }
            }
            // 'boarding' and 'daycare' type-only promos are validated at the call site
            // (this method is called from the package order flow, so 'boarding' promos won't match here)
            if (in_array($promo->applicable_type, ['boarding', 'daycare'])) {
                return new PromotionValidationResult(false, 0, $promo, 'This promotion does not apply to package purchases.');
            }
        }

        $discountCents = $this->calculateDiscount($promo, $amountCents);

        return new PromotionValidationResult(true, $discountCents, $promo);
    }

    /**
     * Validate a promo code for a boarding reservation.
     *
     * @param  string  $code  Case-insensitive promo code
     * @param  string  $tenantId
     * @param  int  $amountCents  Subtotal in cents before discount
     */
    public function validateForBoarding(string $code, string $tenantId, int $amountCents): PromotionValidationResult
    {
        $promo = Promotion::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->whereNull('deleted_at')
            ->first();

        if (! $promo) {
            return new PromotionValidationResult(false, 0, null, 'Invalid promo code.');
        }

        if (! $promo->is_active) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion is no longer active.');
        }

        if ($promo->isExpired()) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion has expired.');
        }

        if ($promo->isMaxedOut()) {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion has reached its usage limit.');
        }

        if ($amountCents < $promo->min_purchase_cents) {
            return new PromotionValidationResult(false, 0, $promo, 'Minimum purchase not met for this promotion.');
        }

        // Must be a boarding promo or a universal promo
        if ($promo->applicable_type !== null && $promo->applicable_type !== 'boarding') {
            return new PromotionValidationResult(false, 0, $promo, 'This promotion does not apply to boarding.');
        }

        $discountCents = $this->calculateDiscount($promo, $amountCents);

        return new PromotionValidationResult(true, $discountCents, $promo);
    }

    /**
     * Record a promo code redemption. Call after the order is persisted.
     */
    public function apply(Promotion $promo, Order $order, int $discountCents, int $originalAmountCents): PromotionRedemption
    {
        $redemption = PromotionRedemption::create([
            'tenant_id'             => $order->tenant_id,
            'promotion_id'          => $promo->id,
            'order_id'              => $order->id,
            'customer_id'           => $order->customer_id,
            'discount_amount_cents' => $discountCents,
            'original_amount_cents' => $originalAmountCents,
        ]);

        DB::table('promotions')
            ->where('id', $promo->id)
            ->increment('used_count');

        $this->events->record($order->tenant_id, 'promo_redeemed', [
            'promo_id'              => $promo->id,
            'promo_code'            => $promo->code,
            'discount_amount_cents' => $discountCents,
            'original_amount_cents' => $originalAmountCents,
        ]);

        return $redemption;
    }

    private function calculateDiscount(Promotion $promo, int $amountCents): int
    {
        $discount = $promo->type === 'percentage'
            ? (int) round($amountCents * $promo->discount_value / 100)
            : $promo->discount_value;

        return min($discount, $amountCents);
    }
}
