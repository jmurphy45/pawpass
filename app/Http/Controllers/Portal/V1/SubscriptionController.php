<?php

namespace App\Http\Controllers\Portal\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\V1\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $package = Package::findOrFail($request->package_id);

        if (! $package->is_active) {
            return response()->json([
                'message' => 'This package is no longer available.',
                'error_code' => 'PACKAGE_ARCHIVED',
            ], 409);
        }

        if ($package->type !== 'subscription') {
            return response()->json([
                'message' => 'Only subscription packages can be purchased via this endpoint.',
                'error_code' => 'INVALID_PACKAGE_TYPE',
            ], 422);
        }

        $customer = auth()->user()->customer;
        $dog = Dog::findOrFail($request->dog_id);

        $existingActive = Subscription::where('dog_id', $dog->id)
            ->where('package_id', $package->id)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'message' => 'This dog already has an active subscription for this package.',
                'error_code' => 'ALREADY_SUBSCRIBED',
            ], 409);
        }

        $tenant = $customer->tenant;

        if ($customer->stripe_customer_id) {
            $stripeCustomerId = $customer->stripe_customer_id;
        } else {
            $stripeCustomer = $this->stripe->createCustomer(
                $customer->email ?? '',
                $customer->name,
            );
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'dog_id' => $dog->id,
            'status' => 'active',
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        $setupIntent = $this->stripe->createSetupIntent(
            $stripeCustomerId,
            ['local_subscription_id' => $subscription->id],
        );

        return response()->json([
            'data' => [
                'subscription_id' => $subscription->id,
                'client_secret' => $setupIntent->client_secret,
            ],
        ], 201);
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse|SubscriptionResource
    {
        $customer = auth()->user()->customer;

        if ($subscription->customer_id !== $customer->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($subscription->status !== 'active') {
            return response()->json([
                'message' => 'Only active subscriptions can be cancelled.',
                'error_code' => 'NOT_CANCELLABLE',
            ], 409);
        }

        $this->stripe->cancelSubscriptionAtPeriodEnd(
            $subscription->stripe_sub_id,
        );

        $subscription->update(['cancelled_at' => now()]);
        $subscription->load(['package', 'dog']);

        return new SubscriptionResource($subscription);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = auth()->user()->customer;

        $subscriptions = Subscription::where('customer_id', $customer->id)
            ->with(['package', 'dog'])
            ->latest()
            ->get();

        return SubscriptionResource::collection($subscriptions);
    }
}
