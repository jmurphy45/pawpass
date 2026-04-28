<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenantId = app('current.tenant.id');
        $tenant = Tenant::find($tenantId);
        $threshold = $tenant?->low_credit_threshold ?? 2;

        $checkinsToday = Attendance::whereDate('checked_in_at', today())
            ->whereNull('checked_out_at')
            ->count();

        $customersCount = Customer::count();
        $dogsCount = Dog::count();

        $lowCreditDogs = Dog::with('customer')
            ->where('credit_balance', '<=', $threshold)
            ->orderBy('credit_balance')
            ->limit(10)
            ->get()
            ->map(fn ($dog) => [
                'id' => $dog->id,
                'name' => $dog->name,
                'credit_balance' => $dog->credit_balance,
                'customer_name' => $dog->customer?->name,
            ]);

        $recentAttendance = Attendance::with(['dog', 'dog.customer'])
            ->orderByDesc('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'dog_name' => $a->dog?->name,
                'customer_name' => $a->dog?->customer?->name,
                'checked_in_at' => $a->checked_in_at->toIso8601String(),
                'checked_out_at' => $a->checked_out_at?->toIso8601String(),
            ]);

        $outstandingCustomers = Customer::where('outstanding_balance_cents', '>', 0)
            ->orderByDesc('outstanding_balance_cents')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'outstanding_balance_cents' => $c->outstanding_balance_cents,
                'has_payment_method' => $c->stripe_payment_method_id !== null && $c->stripe_customer_id !== null,
                'charge_pending_at' => $c->charge_pending_at?->toIso8601String(),
            ]);

        $outstandingTotal = Customer::where('outstanding_balance_cents', '>', 0)->sum('outstanding_balance_cents');
        $outstandingCount = Customer::where('outstanding_balance_cents', '>', 0)->count();

        $checklist = $this->buildChecklist($tenant);

        return Inertia::render('Admin/Dashboard', [
            'checkinsToday' => $checkinsToday,
            'customersCount' => $customersCount,
            'dogsCount' => $dogsCount,
            'lowCreditDogs' => $lowCreditDogs,
            'recentAttendance' => $recentAttendance,
            'onboarding' => $checklist,
            'outstandingTotal' => (int) $outstandingTotal,
            'outstandingCount' => $outstandingCount,
            'outstandingCustomers' => $outstandingCustomers,
        ]);
    }

    private function buildChecklist(?Tenant $tenant): array
    {
        if (! $tenant) {
            return [];
        }

        $tenantId = $tenant->id;

        $steps = [
            [
                'key' => 'stripe',
                'label' => 'Connect Stripe to accept payments',
                'done' => $tenant->stripe_onboarded_at !== null,
                'owner_only' => true,
                'route' => 'admin.billing.index',
            ],
            [
                'key' => 'package',
                'label' => 'Create your first package',
                'done' => Package::allTenants()->where('tenant_id', $tenantId)->exists(),
                'owner_only' => true,
                'route' => 'admin.packages.create',
            ],
            [
                'key' => 'customer',
                'label' => 'Add your first customer',
                'done' => Customer::count() > 0,
                'owner_only' => false,
                'route' => 'admin.customers.create',
            ],
            [
                'key' => 'staff',
                'label' => 'Invite a staff member',
                'done' => User::where('tenant_id', $tenantId)
                    ->where('id', '!=', $tenant->owner_user_id)
                    ->exists(),
                'owner_only' => true,
                'route' => 'admin.settings.index',
            ],
            [
                'key' => 'logo',
                'label' => 'Upload your business logo',
                'done' => $tenant->logo_url !== null,
                'owner_only' => false,
                'route' => 'admin.settings.index',
            ],
        ];

        $allDone = collect($steps)->every('done');

        return $allDone ? [] : $steps;
    }
}
