<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $customer = $user->customer;
        $tenantId = app('current.tenant.id');

        $tenant = Tenant::find($tenantId);
        $threshold = $tenant?->low_credit_threshold ?? 2;

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($dog) => [
                'id'             => $dog->id,
                'name'           => $dog->name,
                'breed'          => $dog->breed,
                'credit_balance' => $dog->credit_balance,
                'credits_expire_at' => $dog->credits_expire_at?->toIso8601String(),
                'credit_status'  => match (true) {
                    $dog->credit_balance <= 0 => 'empty',
                    $dog->credit_balance <= $threshold => 'low',
                    default => 'ok',
                },
            ]);

        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? $n->type,
                'message'    => $n->data['body'] ?? null,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return Inertia::render('Portal/Dashboard', [
            'dogs'                => $dogs,
            'recentNotifications' => $recentNotifications,
        ]);
    }
}
