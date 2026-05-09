<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\PlanFeatureCache;
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
        $tenantModel = null;
        $tenantLoaded = false;
        $getTenant = function () use (&$tenantModel, &$tenantLoaded) {
            if (! $tenantLoaded) {
                $tenantLoaded = true;
                $id = app('current.tenant.id');
                $tenantModel = $id ? Tenant::find($id) : null;
            }

            return $tenantModel;
        };

        return [
            ...parent::share($request),
            'tenant' => function () use ($getTenant) {
                $t = $getTenant();
                if (! $t) {
                    return null;
                }

                return [
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'primary_color' => $t->primary_color ?? '#4f46e5',
                    'logo_url' => $t->logo_url ?? null,
                    'business_type' => $t->business_type ?? 'daycare',
                    'timezone' => $t->timezone ?? 'UTC',
                ];
            },
            'tenantPlan' => fn () => $getTenant()?->plan,
            'tenantStatus' => fn () => $getTenant()?->status,
            'tenantFeatures' => fn () => ($plan = $getTenant()?->plan)
                ? app(PlanFeatureCache::class)->featuresForPlan($plan)
                : [],
            'tenantTrialEndsAt' => fn () => $getTenant()?->trial_ends_at?->toIso8601String(),
            'tenantBillingPmAttached' => fn () => $getTenant()?->billing_pm_attached_at !== null,
            'auth' => function () {
                $user = Auth::guard('web')->user();

                return [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? null,
                        'role' => $user->role,
                    ] : null,
                ];
            },
            'unreadCount' => function () {
                $user = Auth::guard('web')->user();

                return $user ? $user->notifications()->whereNull('read_at')->count() : 0;
            },
            'apiToken' => function () {
                $user = Auth::guard('web')->user();

                return $user && in_array($user->role, ['staff', 'business_owner'])
                    ? app(\App\Auth\JwtService::class)->issue($user)
                    : null;
            },
            'vapidPublicKey' => config('webpush.vapid.public_key') ?: null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
