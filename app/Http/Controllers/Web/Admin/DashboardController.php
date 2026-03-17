<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Tenant;
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
                'id'             => $dog->id,
                'name'           => $dog->name,
                'credit_balance' => $dog->credit_balance,
                'customer_name'  => $dog->customer?->name,
            ]);

        $recentAttendance = Attendance::with(['dog', 'dog.customer'])
            ->orderByDesc('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'dog_name'       => $a->dog?->name,
                'customer_name'  => $a->dog?->customer?->name,
                'checked_in_at'  => $a->checked_in_at->toIso8601String(),
                'checked_out_at' => $a->checked_out_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'checkinsToday'   => $checkinsToday,
            'customersCount'  => $customersCount,
            'dogsCount'       => $dogsCount,
            'lowCreditDogs'   => $lowCreditDogs,
            'recentAttendance' => $recentAttendance,
        ]);
    }
}
