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

        $amountCents = (int) round($package->price * 100);
        $applicationFeeCents = (int) round($amountCents * $tenant->platform_fee_pct / 100);

        $order = DB::transaction(function () use ($request, $package, $tenantId, $customer, $idempotencyKey) {
            $order = Order::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'status' => 'pending',
                'total_amount' => $package->price,
                'platform_fee_pct' => $customer->tenant->platform_fee_pct,
                'idempotency_key' => $idempotencyKey,
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

        $pi = $this->stripe->createPaymentIntent(
            $amountCents,
            'usd',
            $tenant->stripe_account_id,
            $applicationFeeCents,
            [
                'order_id' => $order->id,
                'tenant_id' => $tenantId,
                'package_name' => $package->name,
                'customer_name' => $customer->name,
                'dog_names' => $dogNames,
            ]
        );

        $order->lineItems()->create([
            'tenant_id'        => $tenantId,
            'description'      => $package->name,
            'quantity'         => 1,
            'unit_price_cents' => $amountCents,
            'sort_order'       => 0,
        ]);

        $order->payments()->create([
            'tenant_id'             => $tenantId,
            'stripe_pi_id'          => $pi->id,
            'amount_cents'          => $amountCents,
            'type'                  => 'full',
            'status'                => 'pending',
        ]);

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'client_secret' => $pi->client_secret,
            ],
        ], 201);
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
