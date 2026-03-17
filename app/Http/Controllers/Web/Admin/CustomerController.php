<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function index(Request $request): Response
    {
        $query = Customer::withCount('dogs')->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->through(fn ($c) => [
            'id'        => $c->id,
            'name'      => $c->name,
            'email'     => $c->email,
            'phone'     => $c->phone,
            'dogs_count' => $c->dogs_count,
            'created_at' => $c->created_at->toIso8601String(),
        ]);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters'   => ['search' => $request->query('search', '')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Customers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOwnerOrStaff();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenantId = app('current.tenant.id');

        $customer = DB::transaction(function () use ($validated, $tenantId) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name'      => $validated['name'],
                'email'     => $validated['email'] ?? null,
                'phone'     => $validated['phone'] ?? null,
                'notes'     => $validated['notes'] ?? null,
            ]);

            if (! empty($validated['email'])) {
                $user = User::create([
                    'tenant_id'   => $tenantId,
                    'customer_id' => $customer->id,
                    'name'        => $validated['name'],
                    'email'       => $validated['email'],
                    'password'    => bcrypt(str()->random(24)),
                    'role'        => 'customer',
                    'status'      => 'active',
                    'email_verified_at' => now(),
                ]);

                $customer->update(['user_id' => $user->id]);
            }

            return $customer;
        });

        $tenant = Tenant::find($tenantId);
        if ($tenant->stripe_account_id) {
            try {
                $stripeCustomer = $this->stripe->createCustomer(
                    $customer->email ?? '',
                    $customer->name,
                    $tenant->stripe_account_id,
                );
                $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
            } catch (\Throwable $e) {
                Log::warning('Web admin customer Stripe sync failed', [
                    'customer_id' => $customer->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['dogs', 'orders.package']);

        $dogs = $customer->dogs->map(fn ($dog) => [
            'id'             => $dog->id,
            'name'           => $dog->name,
            'breed'          => $dog->breed,
            'credit_balance' => $dog->credit_balance,
            'deleted_at'     => $dog->deleted_at?->toIso8601String(),
        ]);

        $orders = $customer->orders()->with('package')->latest()->paginate(10)->through(fn ($o) => [
            'id'           => $o->id,
            'package_name' => $o->package?->name,
            'amount_cents' => $o->amount_cents,
            'status'       => $o->status,
            'created_at'   => $o->created_at->toIso8601String(),
        ]);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
            ],
            'dogs'   => $dogs,
            'orders' => $orders,
        ]);
    }

    private function authorizeOwnerOrStaff(): void
    {
        // Both staff and business_owner can create customers
        // (plan gate enforced by route middleware if needed)
    }
}
