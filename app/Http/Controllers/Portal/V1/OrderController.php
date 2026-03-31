<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\V1\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class OrderController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

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
            $stripeCustomer   = $this->stripe->createCustomer($customer->email ?? '', $customer->name, $tenant->stripe_account_id);
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $subtotalCents = (int) round($package->price * 100);
        $effectiveFeePct = $tenant->effectivePlatformFeePct($subtotalCents);
        // Platform fee is on subtotal only — tax is a pass-through for the daycare
        $applicationFeeCents = (int) round($subtotalCents * $effectiveFeePct / 100);

        $taxAmountCents = 0;
        $taxCalcId = null;

        if (Feature::active('tax_daycare_orders') && $request->postal_code && $tenant->stripe_account_id) {
            $calculation = $this->stripe->calculateTax(
                $subtotalCents,
                'usd',
                $tenant->stripe_account_id,
                ['postal_code' => $request->postal_code, 'country' => $request->country ?? 'US'],
                $package->id,
            );
            $taxAmountCents = $calculation->tax_amount_exclusive;
            $taxCalcId = $calculation->id;
        }

        $totalCents = $subtotalCents + $taxAmountCents;

        $order = DB::transaction(function () use ($request, $package, $tenantId, $customer, $idempotencyKey, $effectiveFeePct, $subtotalCents, $taxAmountCents, $taxCalcId, $totalCents) {
            $order = Order::create([
                'tenant_id'          => $tenantId,
                'customer_id'        => $customer->id,
                'package_id'         => $package->id,
                'status'             => 'pending',
                'total_amount'       => $totalCents / 100,
                'subtotal_cents'     => $subtotalCents,
                'tax_amount_cents'   => $taxAmountCents,
                'stripe_tax_calc_id' => $taxCalcId,
                'platform_fee_pct'   => $effectiveFeePct,
                'idempotency_key'    => $idempotencyKey,
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
            'order_id'     => $order->id,
            'tenant_id'    => $tenantId,
            'package_name' => $package->name,
            'customer_name' => $customer->name,
            'dog_names'    => $dogNames,
        ];

        if ($taxCalcId) {
            $metadata['tax_calculation_id'] = $taxCalcId;
        }

        $pi = $this->stripe->createPaymentIntent(
            $totalCents,
            'usd',
            $tenant->stripe_account_id,
            $applicationFeeCents,
            $metadata,
            $stripeCustomerId,
        );

        $order->lineItems()->create([
            'tenant_id'        => $tenantId,
            'description'      => $package->name,
            'quantity'         => 1,
            'unit_price_cents' => $subtotalCents,
            'sort_order'       => 0,
        ]);

        $order->payments()->create([
            'tenant_id'   => $tenantId,
            'stripe_pi_id' => $pi->id,
            'amount_cents' => $totalCents,
            'type'         => 'full',
            'status'       => 'pending',
        ]);

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
            'package_id'  => ['required', 'string'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'size:2'],
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

        $calculation = $this->stripe->calculateTax(
            $subtotalCents,
            'usd',
            $tenant->stripe_account_id,
            ['postal_code' => $validated['postal_code'], 'country' => $validated['country'] ?? 'US'],
            $package->id,
        );

        return response()->json(['data' => [
            'tax_enabled'    => true,
            'subtotal_amount' => number_format($subtotalCents / 100, 2),
            'tax_amount'     => number_format($calculation->tax_amount_exclusive / 100, 2),
            'total_amount'   => number_format($calculation->amount_total / 100, 2),
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
