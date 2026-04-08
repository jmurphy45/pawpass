<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Promotion;
use App\Services\TenantEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly TenantEventService $events) {}

    public function index(): Response
    {
        $promotions = Promotion::withCount('redemptions')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id'                 => $p->id,
                'name'               => $p->name,
                'code'               => $p->code,
                'type'               => $p->type,
                'discount_value'     => $p->discount_value,
                'applicable_type'    => $p->applicable_type,
                'applicable_id'      => $p->applicable_id,
                'min_purchase_cents' => $p->min_purchase_cents,
                'expires_at'         => $p->expires_at?->toIso8601String(),
                'max_uses'           => $p->max_uses,
                'used_count'         => $p->used_count,
                'is_active'          => $p->is_active,
                'description'        => $p->description,
                'redemptions_count'  => $p->redemptions_count,
                'is_expired'         => $p->isExpired(),
                'is_maxed_out'       => $p->isMaxedOut(),
            ]);

        $packages = Package::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
            'packages'   => $packages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'code'               => ['required', 'string', 'max:50', 'alpha_dash'],
            'type'               => ['required', 'string', 'in:percentage,fixed_cents'],
            'discount_value'     => ['required', 'integer', 'min:1'],
            'applicable_type'    => ['nullable', 'string', 'in:App\Models\Package,boarding,daycare'],
            'applicable_id'      => ['nullable', 'string'],
            'min_purchase_cents' => ['nullable', 'integer', 'min:0'],
            'expires_at'         => ['nullable', 'date', 'after:now'],
            'max_uses'           => ['nullable', 'integer', 'min:1'],
            'description'        => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['type'] === 'percentage' && $validated['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Percentage discount cannot exceed 100.']);
        }

        $tenantId = app('current.tenant.id');

        Promotion::create([
            'tenant_id'          => $tenantId,
            'name'               => $validated['name'],
            'code'               => strtoupper($validated['code']),
            'type'               => $validated['type'],
            'discount_value'     => $validated['discount_value'],
            'applicable_type'    => $validated['applicable_type'] ?? null,
            'applicable_id'      => $validated['applicable_id'] ?? null,
            'min_purchase_cents' => $validated['min_purchase_cents'] ?? 0,
            'expires_at'         => $validated['expires_at'] ?? null,
            'max_uses'           => $validated['max_uses'] ?? null,
            'is_active'          => true,
            'description'        => $validated['description'] ?? null,
            'created_by'         => auth()->id(),
        ]);

        $this->events->record($tenantId, 'promo_created', ['code' => strtoupper($validated['code']), 'type' => $validated['type']]);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion created.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'is_active'  => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'max_uses'   => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $promotion->update($validated);

        return back()->with('success', 'Promotion updated.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion deleted.');
    }
}
