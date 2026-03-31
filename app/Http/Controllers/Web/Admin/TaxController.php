<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function index(): Response
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        return Inertia::render('Admin/Tax/Index', [
            'stripe_key'             => config('services.stripe.key'),
            'stripe_account_id'      => $tenant->stripe_account_id,
            'tax_collection_enabled' => (bool) $tenant->tax_collection_enabled,
        ]);
    }

    public function toggleCollection(): JsonResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        $tenant->update(['tax_collection_enabled' => ! $tenant->tax_collection_enabled]);

        return response()->json(['data' => ['tax_collection_enabled' => $tenant->tax_collection_enabled]]);
    }

    public function accountSession(): JsonResponse
    {
        $this->requireOwner();

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->stripe_account_id) {
            return response()->json(['error' => 'STRIPE_ACCOUNT_PROVISIONING'], 422);
        }

        $session = $this->stripe->createAccountSession($tenant->stripe_account_id, [
            'tax_settings'      => ['enabled' => true],
            'tax_registrations' => ['enabled' => true],
        ]);

        return response()->json(['client_secret' => $session->client_secret]);
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage tax settings.');
        }
    }
}
