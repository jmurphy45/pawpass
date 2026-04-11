<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly MagicLinkService $magicLink,
    ) {}

    public function index(Request $request): Response
    {
        $query = Customer::withCount(['dogs', 'orders'])
            ->withSum(['orders as orders_sum_total_amount' => fn ($q) => $q->whereIn('status', ['paid', 'partially_refunded'])], 'total_amount')
            ->latest();

        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(20)->through(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'email'        => $c->email,
            'phone'        => $c->phone,
            'dogs_count'   => $c->dogs_count,
            'orders_count' => $c->orders_count,
            'total_spent'  => (float) ($c->orders_sum_total_amount ?? 0),
            'has_portal'   => $c->user_id !== null,
            'created_at'   => $c->created_at->toIso8601String(),
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
                    $customer->email,
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

        // Send welcome email with portal access link if the customer has a user account
        if (! empty($validated['email']) && $customer->user_id) {
            $user = User::allTenants()->find($customer->user_id);
            if ($user) {
                try {
                    $rawToken = $this->magicLink->generateToken($user, [], $request->ip(), expiryMinutes: 72 * 60);
                    Mail::to($user->email)->send(new CustomerWelcomeMail($user, $tenant, $rawToken));
                } catch (\Throwable $e) {
                    Log::warning('Customer welcome email failed', [
                        'customer_id' => $customer->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): Response
    {
        $customer->loadCount('orders')->loadSum(['orders as orders_sum_total_amount' => fn ($q) => $q->whereIn('status', ['paid', 'partially_refunded'])], 'total_amount');
        $customer->load(['dogs' => fn ($q) => $q->withTrashed(), 'orders.package']);

        $dogs = $customer->dogs->map(function ($dog) {
            $lastAttendance = $dog->attendances()->latest('checked_in_at')->value('checked_in_at');

            return [
                'id'                        => $dog->id,
                'name'                      => $dog->name,
                'breed'                     => $dog->breed,
                'sex'                       => $dog->sex,
                'dob'                       => $dog->dob?->toDateString(),
                'status'                    => $dog->status?->value ?? 'active',
                'credit_balance'            => $dog->credit_balance,
                'credits_expire_at'         => $dog->credits_expire_at?->toIso8601String(),
                'unlimited_pass_expires_at' => $dog->unlimited_pass_expires_at?->toIso8601String(),
                'vet_name'                  => $dog->vet_name,
                'vet_phone'                 => $dog->vet_phone,
                'last_attendance_at'        => $lastAttendance ? \Illuminate\Support\Carbon::parse($lastAttendance)->toIso8601String() : null,
                'deleted_at'                => $dog->deleted_at?->toIso8601String(),
            ];
        });

        $orders = $customer->orders()->with('package')->latest()->get()->map(fn ($o) => [
            'id'           => $o->id,
            'package_name' => $o->package?->name,
            'type'         => $o->type,
            'total_amount' => (float) $o->total_amount,
            'status'       => $o->status->value,
            'created_at'   => $o->created_at->toIso8601String(),
        ]);

        $totalCredits = $customer->dogs->sum('credit_balance');

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id'                       => $customer->id,
                'name'                     => $customer->name,
                'email'                    => $customer->email,
                'phone'                    => $customer->phone,
                'notes'                    => $customer->notes,
                'has_portal'               => $customer->user_id !== null,
                'stripe_pm_last4'          => $customer->stripe_pm_last4,
                'stripe_pm_brand'          => $customer->stripe_pm_brand,
                'outstanding_balance_cents' => $customer->outstanding_balance_cents ?? 0,
                'has_stripe_customer'      => $customer->stripe_customer_id !== null,
                'is_owner'                 => auth()->user()->role === 'business_owner',
                'total_orders'             => $customer->orders_count,
                'total_spent'              => (float) ($customer->orders_sum_total_amount ?? 0),
                'total_credits'            => $totalCredits,
                'created_at'               => $customer->created_at->toIso8601String(),
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
