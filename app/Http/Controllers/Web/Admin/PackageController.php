<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPackageToStripe;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $this->requireOwner();

        $packages = Package::withTrashed()->latest()->get()->map(fn ($p) => [
            'id'               => $p->id,
            'name'             => $p->name,
            'type'             => $p->type,
            'price'            => $p->price,
            'credit_count'     => $p->credit_count,
            'dog_limit'        => $p->dog_limit,
            'is_active'        => $p->is_active,
            'archived_at'           => $p->deleted_at?->toIso8601String(),
            'is_recurring_enabled'  => (bool) $p->is_recurring_enabled,
        ]);

        return Inertia::render('Admin/Packages/Index', [
            'packages' => $packages,
        ]);
    }

    public function create(): Response
    {
        $this->requireOwner();

        return Inertia::render('Admin/Packages/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'type'         => ['required', 'string', 'in:one_time,subscription,unlimited'],
            'price'        => ['required', 'numeric', 'min:0'],
            'credit_count' => [
                'required', 'integer',
                Rule::when($request->input('type') === 'unlimited', ['min:0'], ['min:1']),
            ],
            'dog_limit'    => ['nullable', 'integer', 'min:1'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'is_active'    => ['boolean'],
        ]);

        Package::create([
            'tenant_id'    => app('current.tenant.id'),
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'type'         => $validated['type'],
            'price'        => $validated['price'],
            'credit_count' => $validated['credit_count'],
            'dog_limit'    => $validated['dog_limit'] ?? 1,
            'duration_days' => $validated['duration_days'] ?? null,
            'is_active'    => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package created.');
    }

    public function edit(Package $package): Response
    {
        $this->requireOwner();

        return Inertia::render('Admin/Packages/Edit', [
            'package' => [
                'id'                     => $package->id,
                'name'                   => $package->name,
                'description'            => $package->description,
                'type'                   => $package->type,
                'price'                  => $package->price,
                'credit_count'           => $package->credit_count,
                'dog_limit'              => $package->dog_limit,
                'duration_days'          => $package->duration_days,
                'is_active'              => $package->is_active,
                'is_recurring_enabled'   => $package->is_recurring_enabled,
                'recurring_interval_days' => $package->recurring_interval_days,
            ],
        ]);
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'price'                   => ['required', 'numeric', 'min:0'],
            'credit_count'            => [
                'required', 'integer',
                Rule::when($package->type === 'unlimited', ['min:0'], ['min:1']),
            ],
            'dog_limit'               => ['nullable', 'integer', 'min:1'],
            'duration_days'           => ['nullable', 'integer', 'min:1'],
            'is_active'               => ['boolean'],
            'is_recurring_enabled'    => ['sometimes', 'boolean'],
            'recurring_interval_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $recurringChanged = $request->has('is_recurring_enabled') || $request->has('recurring_interval_days');

        $package->update($validated);

        if ($recurringChanged) {
            SyncPackageToStripe::dispatch($package->fresh());
        }

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package updated.');
    }

    public function archive(Package $package): RedirectResponse
    {
        $this->requireOwner();

        if ($package->trashed()) {
            return back()->with('error', 'Package is already archived.');
        }

        $package->update(['is_active' => false]);
        $package->delete();

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package archived.');
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage packages.');
        }
    }
}
