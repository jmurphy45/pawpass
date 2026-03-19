<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $packages = Package::where('is_active', true)
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
                'billing_interval' => $p->type === 'subscription' ? 'monthly' : null,
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

        $tenant = Tenant::find(app('current.tenant.id'));

        return Inertia::render('Portal/Purchase', [
            'packages'          => $packages,
            'dogs'              => $dogs,
            'stripe_key'        => config('services.stripe.key'),
            'stripe_account_id' => $tenant?->stripe_account_id,
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
        $dog = $customer->dogs()->findOrFail($validated['dog_id']);

        $amountCents = (int) round((float) $package->price * 100);
        $feePct = (float) ($tenant->platform_fee_pct ?? 5);
        $feeCents = (int) round($amountCents * $feePct / 100);

        $intent = $stripe->createPaymentIntent(
            amountCents: $amountCents,
            currency: 'usd',
            stripeAccount: $tenant->stripe_account_id,
            applicationFeeCents: $feeCents,
            metadata: [
                'tenant_id'   => $tenantId,
                'customer_id' => $customer->id,
                'package_id'  => $package->id,
                'dog_id'      => $dog->id,
            ]
        );

        return response()->json(['client_secret' => $intent->client_secret]);
    }
}
