<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $tenant = null;
        $tenantId = app('current.tenant.id');

        if ($tenantId) {
            $tenantModel = Tenant::find($tenantId);
            if ($tenantModel) {
                $tenant = [
                    'name'          => $tenantModel->name,
                    'slug'          => $tenantModel->slug,
                    'primary_color' => $tenantModel->primary_color ?? '#4f46e5',
                    'logo_url'      => null,
                ];
                $tenantPlan = $tenantModel->plan;
            }
        }

        $user = Auth::guard('web')->user();
        $unreadCount = 0;

        if ($user) {
            $unreadCount = $user->notifications()->whereNull('read_at')->count();
        }

        return [
            ...parent::share($request),
            'tenant'      => $tenant,
            'tenantPlan'  => $tenantPlan ?? null,
            'auth'        => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'role'  => $user->role,
                ] : null,
            ],
            'unreadCount' => $unreadCount,
            'flash'       => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
