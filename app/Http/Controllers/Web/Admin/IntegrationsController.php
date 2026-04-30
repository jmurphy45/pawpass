<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PimsIntegration;
use App\Models\Tenant;
use App\Services\Pims\PimsAdapterRegistry;
use App\Services\PlanFeatureCache;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function __construct(
        private PimsAdapterRegistry $registry,
        private PlanFeatureCache $planFeatureCache,
    ) {}

    public function index(): Response
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);

        if (! $this->planFeatureCache->hasFeature($tenant?->plan ?? 'free', 'pims_integration')) {
            abort(403);
        }

        $integrations = PimsIntegration::orderBy('created_at')->get()->map(fn ($i) => [
            'id' => $i->id,
            'provider' => $i->provider,
            'provider_label' => $this->registry->for($i->provider)->providerLabel(),
            'api_base_url' => $i->api_base_url,
            'status' => $i->status,
            'last_full_sync_at' => $i->last_full_sync_at?->toIso8601String(),
            'last_delta_sync_at' => $i->last_delta_sync_at?->toIso8601String(),
            'sync_error' => $i->sync_error,
        ]);

        return Inertia::render('Admin/Integrations/Index', [
            'providers' => $this->registry->providers(),
            'integrations' => $integrations,
        ]);
    }
}
