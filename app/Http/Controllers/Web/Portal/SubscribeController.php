<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscribeController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $packages = Package::where('type', 'subscription')
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'description'      => $p->description,
                'type'             => $p->type,
                'price_cents'      => (int) round((float) $p->price * 100),
                'credits'          => $p->credit_count,
                'max_dogs'         => $p->dog_limit,
                'billing_interval' => 'monthly',
                'duration_days'    => $p->duration_days,
                'is_featured'      => $p->is_featured,
            ]);

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id'               => $d->id,
                'name'             => $d->name,
                'credits_expire_at' => $d->credits_expire_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Subscribe', [
            'packages'   => $packages,
            'dogs'       => $dogs,
            'stripe_key' => config('services.stripe.key'),
        ]);
    }

    public function store(Request $request, StripeService $stripe): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'string'],
            'dog_id'     => ['required', 'string'],
        ]);

        $customer = Auth::user()->customer;
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        abort_unless($tenant && $tenant->stripe_account_id, 422, 'Stripe not configured for this tenant.');

        $package = Package::findOrFail($validated['package_id']);

        if ($package->type !== 'subscription') {
            return response()->json([
                'message'    => 'Only subscription packages can be purchased via this endpoint.',
                'error_code' => 'INVALID_PACKAGE_TYPE',
            ], 422);
        }

        $dog = $customer->dogs()->findOrFail($validated['dog_id']);

        $existingActive = Subscription::where('dog_id', $dog->id)
            ->where('package_id', $package->id)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'message'    => 'This dog already has an active subscription for this package.',
                'error_code' => 'ALREADY_SUBSCRIBED',
            ], 409);
        }

        if ($customer->stripe_customer_id) {
            $stripeCustomerId = $customer->stripe_customer_id;
        } else {
            $stripeCustomer = $stripe->createCustomer(
                $customer->email ?? '',
                $customer->name,
                $tenant->stripe_account_id,
            );
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $subscription = Subscription::create([
            'tenant_id'          => $tenant->id,
            'customer_id'        => $customer->id,
            'package_id'         => $package->id,
            'dog_id'             => $dog->id,
            'status'             => 'active',
            'stripe_customer_id' => $stripeCustomerId,
        ]);

        $setupIntent = $stripe->createSetupIntent(
            $stripeCustomerId,
            ['local_subscription_id' => $subscription->id],
            $tenant->stripe_account_id,
        );

        return response()->json([
            'client_secret'   => $setupIntent->client_secret,
            'subscription_id' => $subscription->id,
        ], 201);
    }
}
