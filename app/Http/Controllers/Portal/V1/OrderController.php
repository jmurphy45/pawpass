<?php

namespace App\Http\Controllers\Portal\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\V1\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Package;
use App\Services\PromotionService;
use App\Services\StripeService;
use App\Services\TenantEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class OrderController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly TenantEventService $events,
        private readonly PromotionService $promotions,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $package = Package::findOrFail($request->package_id);

        if (! $package->is_active) {
            return response()->json([
                'message' => 'This package is no longer available.',
                'error_code' => 'PACKAGE_ARCHIVED',
            ], 409);
        }

        if ($package->type !== 'one_time') {
            return response()->json([
                'message' => 'Only one-time packages can be purchased via this endpoint.',
                'error_code' => 'INVALID_PACKAGE_TYPE',
            ], 422);
        }

        $tenantId = app('current.tenant.id');
        $customer = auth()->user()->customer;
        $tenant = $customer->tenant;
        $idempotencyKey = $request->header('Idempotency-Key');

        // DB-level idempotency fallback (handles expired cache)
        $existing = Order::where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'paid'])
            ->first();

        if ($existing) {
            return response()->json([
                'data' => [
                    'order_id' => $existing->id,
                    'client_secret' => null,
                ],
            ]);
        }

        if ($customer->stripe_customer_id) {
            $stripeCustomerId = $customer->stripe_customer_id;
        } else {
            $stripeCustomer = $this->stripe->createCustomer($customer->email, $customer->name, $tenant->stripe_account_id);
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $subtotalCents = (int) round($package->price * 100);

        // Validate promo code before any Stripe calls
        $promoResult = null;
        $promoDiscountCents = 0;
        if ($request->promo_code) {
            $promoResult = $this->promotions->validate($request->promo_code, $customer, $package, $subtotalCents);
            if (! $promoResult->valid) {
                return response()->json([
                    'message' => $promoResult->message ?: 'Invalid promo code.',
                    'error_code' => 'INVALID_PROMO_CODE',
                ], 422);
            }
            $promoDiscountCents = $promoResult->discountCents;
        }

        $discountedSubtotal = $subtotalCents - $promoDiscountCents;
        $effectiveFeePct = $tenant->effectivePlatformFeePct($discountedSubtotal);
        // Platform fee is on subtotal only — tax is a pass-through for the daycare
        $applicationFeeCents = (int) round($discountedSubtotal * $effectiveFeePct / 100);

        $taxAmountCents = 0;
        $taxCalcId = null;

        if (Feature::active('tax_daycare_orders') && $request->postal_code && $tenant->stripe_account_id) {
            try {
                $calculation = $this->stripe->calculateTax(
                    $discountedSubtotal,
                    'usd',
                    $tenant->stripe_account_id,
                    ['postal_code' => $request->postal_code, 'country' => $request->country ?? 'US'],
                    $package->id,
                );
                $taxAmountCents = $calculation->tax_amount_exclusive;
                $taxCalcId = $calculation->id;
            } catch (\Stripe\Exception\ApiErrorException) {
                // Proceed without tax if calculation fails
            }
        }

        $totalCents = $discountedSubtotal + $taxAmountCents;

        $order = DB::transaction(function () use ($request, $package, $tenantId, $customer, $idempotencyKey, $effectiveFeePct, $applicationFeeCents, $discountedSubtotal, $taxAmountCents, $taxCalcId, $totalCents, $promoResult) {
            $order = Order::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'status' => 'pending',
                'cancellable_at' => now()->addHour(),
                'total_amount' => $totalCents / 100,
                'subtotal_cents' => $discountedSubtotal,
                'tax_amount_cents' => $taxAmountCents,
                'stripe_tax_calc_id' => $taxCalcId,
                'platform_fee_pct' => $effectiveFeePct,
                'platform_fee_amount_cents' => $applicationFeeCents,
                'idempotency_key' => $idempotencyKey,
                'promotion_id' => $promoResult?->promotion?->id,
            ]);

            foreach ($request->dog_ids as $dogId) {
                $order->orderDogs()->create([
                    'dog_id' => $dogId,
                    'credits_issued' => 0,
                ]);
            }

            return $order;
        });

        $dogNames = $order->orderDogs()->with('dog')->get()->pluck('dog.name')->implode(', ');

        $metadata = [
            'order_id' => $order->id,
            'tenant_id' => $tenantId,
            'package_name' => $package->name,
            'customer_name' => $customer->name,
            'dog_names' => $dogNames,
        ];

        if ($taxCalcId) {
            $metadata['tax_calculation_id'] = $taxCalcId;
        }

        try {
            $pi = $this->stripe->createPaymentIntent(
                $totalCents,
                'usd',
                $tenant->stripe_account_id,
                $applicationFeeCents,
                $metadata,
                $stripeCustomerId,
                paymentMethodTypes: ['card', 'us_bank_account'],
            );
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $order->transitionTo(OrderStatus::Canceled);
            $order->update(['idempotency_key' => null]);

            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'STRIPE_ERROR',
            ], 422);
        }

        $order->lineItems()->create([
            'tenant_id' => $tenantId,
            'description' => $package->name,
            'quantity' => 1,
            'unit_price_cents' => $discountedSubtotal,
            'sort_order' => 0,
        ]);

        $order->payments()->create([
            'tenant_id' => $tenantId,
            'stripe_pi_id' => $pi->id,
            'amount_cents' => $totalCents,
            'type' => PaymentType::Full,
            'status' => 'pending',
        ]);

        if ($promoResult?->valid && $promoResult->promotion) {
            $this->promotions->apply($promoResult->promotion, $order, $promoResult->discountCents, $subtotalCents);
        }

        $this->events->recordOnce($tenantId, 'first_purchase');

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'client_secret' => $pi->client_secret,
            ],
        ], 201);
    }

    public function taxPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'string'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        if (! Feature::active('tax_daycare_orders')) {
            return response()->json(['data' => ['tax_enabled' => false, 'tax_amount' => '0.00', 'subtotal_amount' => '0.00', 'total_amount' => '0.00']]);
        }

        $package = Package::find($validated['package_id']);
        if (! $package || ! $package->is_active) {
            return response()->json(['message' => 'Package not found.'], 404);
        }

        $tenant = app('current.tenant.id')
            ? \App\Models\Tenant::find(app('current.tenant.id'))
            : null;

        if (! $tenant?->stripe_account_id) {
            return response()->json(['data' => ['tax_enabled' => false, 'tax_amount' => '0.00', 'subtotal_amount' => number_format($package->price, 2), 'total_amount' => number_format($package->price, 2)]]);
        }

        $subtotalCents = (int) round($package->price * 100);

        try {
            $calculation = $this->stripe->calculateTax(
                $subtotalCents,
                'usd',
                $tenant->stripe_account_id,
                ['postal_code' => $validated['postal_code'], 'country' => $validated['country'] ?? 'US'],
                $package->id,
            );
        } catch (\Stripe\Exception\ApiErrorException) {
            return response()->json(['data' => ['tax_enabled' => false, 'tax_amount' => '0.00', 'subtotal_amount' => number_format($subtotalCents / 100, 2), 'total_amount' => number_format($subtotalCents / 100, 2)]]);
        }

        return response()->json(['data' => [
            'tax_enabled' => true,
            'subtotal_amount' => number_format($subtotalCents / 100, 2),
            'tax_amount' => number_format($calculation->tax_amount_exclusive / 100, 2),
            'total_amount' => number_format($calculation->amount_total / 100, 2),
        ]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = auth()->user()->customer;

        $orders = Order::where('customer_id', $customer->id)
            ->with(['package', 'orderDogs.dog'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }
}
