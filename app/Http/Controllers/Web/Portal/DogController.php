<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Breed;
use App\Models\Dog;
use App\Models\DogVaccination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DogController extends Controller
{
    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'breed_name' => $d->breed?->name,
                'color' => $d->color ?? null,
                'credit_balance' => $d->credit_balance,
                'credits_expire_at' => $d->credits_expire_at?->toIso8601String(),
                'unlimited_pass_expires_at' => $d->unlimited_pass_expires_at?->toIso8601String(),
                'deleted_at' => $d->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Dogs/Index', ['dogs' => $dogs]);
    }

    public function create(): Response
    {
        $breeds = Breed::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Portal/Dogs/Create', ['breeds' => $breeds]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'breed_id' => ['nullable', 'integer', 'exists:breeds,id'],
            'color' => ['nullable', 'string', 'max:7'],
            'dob' => ['nullable', 'date'],
        ]);

        $customer = Auth::user()->customer;
        $tenantId = app('current.tenant.id');

        Dog::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            ...$validated,
        ]);

        return redirect()->route('portal.dogs.index')->with('success', 'Dog added successfully.');
    }

    public function show(Dog $dog): Response
    {
        $this->authorizeCustomerDog($dog);

        $dog->load('breed');

        $ledger = $dog->creditLedger()
            ->orderByDesc('created_at')
            ->paginate(20);

        $vaccinations = $dog->vaccinations()->orderByDesc('administered_at')->get()->map(fn ($v) => [
            'id' => $v->id,
            'vaccine_name' => $v->vaccine_name,
            'administered_at' => $v->administered_at->toDateString(),
            'expires_at' => $v->expires_at?->toDateString(),
            'is_valid' => $v->isValid(),
        ]);

        $dog->load('autoReplenishPackage');

        return Inertia::render('Portal/Dogs/Show', [
            'dog' => [
                'id' => $dog->id,
                'name' => $dog->name,
                'breed_id' => $dog->breed_id,
                'breed_name' => $dog->breed?->name,
                'color' => $dog->color ?? null,
                'dob' => $dog->dob?->toDateString(),
                'credit_balance' => $dog->credit_balance,
                'credits_expire_at' => $dog->credits_expire_at?->toIso8601String(),
                'unlimited_pass_expires_at' => $dog->unlimited_pass_expires_at?->toIso8601String(),
                'auto_replenish_enabled' => $dog->auto_replenish_enabled,
                'auto_replenish_package' => $dog->auto_replenish_package_id
                    ? ['id' => $dog->autoReplenishPackage?->id, 'name' => $dog->autoReplenishPackage?->name]
                    : null,
            ],
            'vaccinations' => $vaccinations,
            'subscriptions' => $dog->subscriptions()
                ->where('status', 'active')
                ->with('package')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'status' => $s->status,
                    'cancelled_at' => $s->cancelled_at?->toIso8601String(),
                    'current_period_end' => $s->current_period_end?->toIso8601String(),
                    'package' => ['id' => $s->package->id, 'name' => $s->package->name],
                ]),
            'ledger' => [
                'data' => collect($ledger->items())->map(fn ($e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'amount' => $e->delta,
                    'balance_after' => $e->balance_after,
                    'note' => $e->note,
                    'expires_at' => $e->expires_at?->toIso8601String(),
                    'created_at' => $e->created_at->toIso8601String(),
                ]),
                'meta' => [
                    'total' => $ledger->total(),
                    'per_page' => $ledger->perPage(),
                    'current_page' => $ledger->currentPage(),
                    'last_page' => $ledger->lastPage(),
                ],
            ],
        ]);
    }

    public function edit(Dog $dog): Response
    {
        $this->authorizeCustomerDog($dog);

        $breeds = Breed::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Portal/Dogs/Edit', [
            'dog' => [
                'id' => $dog->id,
                'name' => $dog->name,
                'breed_id' => $dog->breed_id,
                'color' => $dog->color ?? null,
                'dob' => $dog->dob?->toDateString(),
            ],
            'breeds' => $breeds,
        ]);
    }

    public function update(Request $request, Dog $dog): RedirectResponse
    {
        $this->authorizeCustomerDog($dog);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'breed_id' => ['nullable', 'integer', 'exists:breeds,id'],
            'color' => ['nullable', 'string', 'max:7'],
            'dob' => ['nullable', 'date'],
        ]);

        $dog->update($validated);

        return redirect()->route('portal.dogs.show', $dog->id)->with('success', 'Dog updated.');
    }

    public function storeVaccination(Request $request, Dog $dog): RedirectResponse
    {
        $this->authorizeCustomerDog($dog);

        $validated = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:255'],
            'administered_at' => ['required', 'date_format:Y-m-d'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after:administered_at'],
            'administered_by' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $dog->vaccinations()->create($validated);

        return back()->with('success', 'Vaccination record added.');
    }

    public function destroyVaccination(Dog $dog, DogVaccination $vaccination): RedirectResponse
    {
        $this->authorizeCustomerDog($dog);
        abort_if($vaccination->dog_id !== $dog->id, 403);

        $vaccination->delete();

        return back()->with('success', 'Vaccination record removed.');
    }

    private function authorizeCustomerDog(Dog $dog): void
    {
        $customerId = Auth::user()->customer?->id;
        abort_unless($dog->customer_id === $customerId, 403);
    }
}
