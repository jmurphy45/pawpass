<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DogStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Dog::with('customer')->latest();

        $search = $request->query('search');
        $status = $request->query('status');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status && in_array($status, array_column(DogStatus::cases(), 'value'))) {
            $query->where('status', $status);
        }

        $dogs = $query->paginate(20)->through(fn ($dog) => [
            'id'             => $dog->id,
            'name'           => $dog->name,
            'breed'          => $dog->breed,
            'credit_balance' => $dog->credit_balance,
            'customer_name'  => $dog->customer?->name,
            'customer_id'    => $dog->customer_id,
            'status'         => $dog->status->value,
        ]);

        return Inertia::render('Admin/Dogs/Index', [
            'dogs'    => $dogs,
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Dogs/Create', [
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'name'        => ['required', 'string', 'max:255'],
            'breed'       => ['nullable', 'string', 'max:255'],
            'dob'         => ['nullable', 'date'],
            'sex'         => ['nullable', 'string', 'in:male,female,unknown'],
            'vet_name'    => ['nullable', 'string', 'max:255'],
            'vet_phone'   => ['nullable', 'string', 'max:50'],
        ]);

        $customer = Customer::find($validated['customer_id']);

        if (! $customer) {
            return back()->withErrors(['customer_id' => 'Customer not found.']);
        }

        $dog = Dog::create([
            'tenant_id'   => app('current.tenant.id'),
            'customer_id' => $customer->id,
            'name'        => $validated['name'],
            'breed'       => $validated['breed'] ?? null,
            'dob'         => $validated['dob'] ?? null,
            'sex'         => $validated['sex'] ?? null,
            'vet_name'    => $validated['vet_name'] ?? null,
            'vet_phone'   => $validated['vet_phone'] ?? null,
            'credit_balance' => 0,
        ]);

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', 'Dog created successfully.');
    }

    public function show(Dog $dog): Response
    {
        $ledger = $dog->creditLedger()->orderByDesc('created_at')->limit(20)->get()->map(fn ($entry) => [
            'id'            => $entry->id,
            'type'          => $entry->type,
            'amount'        => $entry->delta,
            'balance_after' => $entry->balance_after,
            'note'          => $entry->note,
            'created_at'    => $entry->created_at->toIso8601String(),
        ]);

        $attendance = $dog->attendances()->orderByDesc('checked_in_at')->limit(20)->get()->map(fn ($a) => [
            'id'             => $a->id,
            'checked_in_at'  => $a->checked_in_at->toIso8601String(),
            'checked_out_at' => $a->checked_out_at?->toIso8601String(),
        ]);

        $vaccinations = $dog->vaccinations()->orderByDesc('administered_at')->get()->map(fn ($v) => [
            'id'              => $v->id,
            'vaccine_name'    => $v->vaccine_name,
            'administered_at' => $v->administered_at->toDateString(),
            'expires_at'      => $v->expires_at?->toDateString(),
            'administered_by' => $v->administered_by,
            'notes'           => $v->notes,
            'is_valid'        => $v->isValid(),
        ]);

        return Inertia::render('Admin/Dogs/Show', [
            'dog' => [
                'id'             => $dog->id,
                'name'           => $dog->name,
                'breed'          => $dog->breed,
                'dob'            => $dog->dob?->toDateString(),
                'sex'            => $dog->sex,
                'credit_balance' => $dog->credit_balance,
                'vet_name'       => $dog->vet_name,
                'vet_phone'      => $dog->vet_phone,
                'customer_id'    => $dog->customer_id,
                'customer_name'  => $dog->customer?->name,
                'status'         => $dog->status->value,
            ],
            'ledger'       => $ledger,
            'attendance'   => $attendance,
            'vaccinations' => $vaccinations,
        ]);
    }

    public function edit(Dog $dog): Response
    {
        $eligiblePackages = Package::where('is_auto_replenish_eligible', true)
            ->where('is_active', true)
            ->get(['id', 'name', 'price', 'credit_count']);

        $statusOptions = array_map(fn (DogStatus $s) => [
            'value'   => $s->value,
            'label'   => $s->label(),
            'tooltip' => $s->tooltip(),
        ], DogStatus::cases());

        return Inertia::render('Admin/Dogs/Edit', [
            'dog' => [
                'id'                        => $dog->id,
                'name'                      => $dog->name,
                'breed'                     => $dog->breed,
                'dob'                       => $dog->dob?->toDateString(),
                'sex'                       => $dog->sex,
                'vet_name'                  => $dog->vet_name,
                'vet_phone'                 => $dog->vet_phone,
                'auto_replenish_enabled'    => (bool) $dog->auto_replenish_enabled,
                'auto_replenish_package_id' => $dog->auto_replenish_package_id,
                'status'                    => $dog->status->value,
            ],
            'eligiblePackages' => $eligiblePackages,
            'statusOptions'    => $statusOptions,
        ]);
    }

    public function update(Request $request, Dog $dog): RedirectResponse
    {
        $validated = $request->validate([
            'name'                      => ['required', 'string', 'max:255'],
            'breed'                     => ['nullable', 'string', 'max:255'],
            'dob'                       => ['nullable', 'date'],
            'sex'                       => ['nullable', 'string', 'in:male,female,unknown'],
            'vet_name'                  => ['nullable', 'string', 'max:255'],
            'vet_phone'                 => ['nullable', 'string', 'max:50'],
            'auto_replenish_enabled'    => ['boolean'],
            'auto_replenish_package_id' => ['nullable', 'string', 'exists:packages,id'],
            'status'                    => ['required', Rule::enum(DogStatus::class)],
        ]);

        if (! ($validated['auto_replenish_enabled'] ?? false)) {
            $validated['auto_replenish_package_id'] = null;
        }

        $dog->update($validated);

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', 'Dog updated successfully.');
    }

    public function storeVaccination(Request $request, Dog $dog): RedirectResponse
    {
        $validated = $request->validate([
            'vaccine_name'    => ['required', 'string', 'max:255'],
            'administered_at' => ['required', 'date'],
            'expires_at'      => ['nullable', 'date', 'after:administered_at'],
            'administered_by' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ]);

        DogVaccination::create([
            'tenant_id' => app('current.tenant.id'),
            'dog_id'    => $dog->id,
            ...$validated,
        ]);

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', 'Vaccination record added.');
    }

    public function destroyVaccination(Dog $dog, DogVaccination $vaccination): RedirectResponse
    {
        abort_unless($vaccination->dog_id === $dog->id, 404);

        $vaccination->delete();

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', 'Vaccination record removed.');
    }
}
