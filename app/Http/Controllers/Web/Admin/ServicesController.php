<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddonType;
use App\Models\Tenant;
use App\Services\PlanFeatureCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    public function __construct(private PlanFeatureCache $planFeatureCache) {}

    public function index(): Response
    {
        $tenant = Tenant::find(app('current.tenant.id'));
        if (! $this->planFeatureCache->hasFeature($tenant?->plan ?? 'free', 'addon_services')) {
            abort(403);
        }

        return Inertia::render('Admin/Services/Index', [
            'addonTypes' => AddonType::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'context'     => ['sometimes', 'string', 'in:boarding,daycare,both'],
        ]);

        AddonType::create([
            'tenant_id'   => app('current.tenant.id'),
            'name'        => $validated['name'],
            'price_cents' => $validated['price_cents'],
            'is_active'   => $request->boolean('is_active', true),
            'sort_order'  => $validated['sort_order'] ?? 0,
            'context'     => $validated['context'] ?? 'both',
        ]);

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function update(Request $request, AddonType $addonType): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'context'     => ['sometimes', 'string', 'in:boarding,daycare,both'],
        ]);

        $addonType->update($validated);

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(AddonType $addonType): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        if ($addonType->reservationAddons()->exists() || $addonType->attendanceAddons()->exists()) {
            abort(409, 'ADDON_TYPE_IN_USE');
        }

        $addonType->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }
}
