<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Dog;
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
                'id'             => $d->id,
                'name'           => $d->name,
                'breed'          => $d->breed,
                'color'          => $d->color ?? null,
                'credit_balance' => $d->credit_balance,
                'credits_expire_at' => $d->credits_expire_at?->toIso8601String(),
                'deleted_at'     => $d->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('Portal/Dogs/Index', ['dogs' => $dogs]);
    }

    public function create(): Response
    {
        return Inertia::render('Portal/Dogs/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'dob'   => ['nullable', 'date'],
        ]);

        $customer = Auth::user()->customer;
        $tenantId = app('current.tenant.id');

        Dog::create([
            'tenant_id'   => $tenantId,
            'customer_id' => $customer->id,
            ...$validated,
        ]);

        return redirect()->route('portal.dogs.index')->with('success', 'Dog added successfully.');
    }

    public function show(Dog $dog): Response
    {
        $this->authorizeCustomerDog($dog);

        $ledger = $dog->creditLedger()
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Portal/Dogs/Show', [
            'dog' => [
                'id'             => $dog->id,
                'name'           => $dog->name,
                'breed'          => $dog->breed,
                'color'          => $dog->color ?? null,
                'dob'            => $dog->dob?->toDateString(),
                'credit_balance' => $dog->credit_balance,
                'credits_expire_at' => $dog->credits_expire_at?->toIso8601String(),
            ],
            'ledger' => [
                'data' => collect($ledger->items())->map(fn ($e) => [
                    'id'            => $e->id,
                    'type'          => $e->type,
                    'amount'        => $e->amount,
                    'balance_after' => $e->balance_after,
                    'note'          => $e->note,
                    'created_at'    => $e->created_at->toIso8601String(),
                ]),
                'meta' => [
                    'total'        => $ledger->total(),
                    'per_page'     => $ledger->perPage(),
                    'current_page' => $ledger->currentPage(),
                    'last_page'    => $ledger->lastPage(),
                ],
            ],
        ]);
    }

    public function edit(Dog $dog): Response
    {
        $this->authorizeCustomerDog($dog);

        return Inertia::render('Portal/Dogs/Edit', [
            'dog' => [
                'id'    => $dog->id,
                'name'  => $dog->name,
                'breed' => $dog->breed,
                'color' => $dog->color ?? null,
                'dob'   => $dog->dob?->toDateString(),
            ],
        ]);
    }

    public function update(Request $request, Dog $dog): RedirectResponse
    {
        $this->authorizeCustomerDog($dog);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'breed' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'dob'   => ['nullable', 'date'],
        ]);

        $dog->update($validated);

        return redirect()->route('portal.dogs.show', $dog->id)->with('success', 'Dog updated.');
    }

    private function authorizeCustomerDog(Dog $dog): void
    {
        $customerId = Auth::user()->customer?->id;
        abort_unless($dog->customer_id === $customerId, 403);
    }
}
