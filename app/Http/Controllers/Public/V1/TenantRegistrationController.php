<?php

namespace App\Http\Controllers\Public\V1;

use App\Http\Controllers\Controller;
use App\Services\TenantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantRegistrationController extends Controller
{
    public function __construct(private readonly TenantRegistrationService $registration) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'slug'          => [
                'required',
                'string',
                'regex:/^[a-z0-9-]+$/',
                'max:63',
                Rule::unique('tenants', 'slug')->whereNull('deleted_at'),
            ],
            'owner_name'    => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', Rule::unique('users', 'email')],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'plan'          => [
                'required',
                'string',
                Rule::exists('platform_plans', 'slug')
                    ->where('is_active', true)
                    ->whereNotNull('stripe_monthly_price_id'),
            ],
            'billing_cycle' => ['required', 'in:monthly,annual'],
        ]);

        $result = $this->registration->register($validated);

        $tenant = $result['tenant'];

        return response()->json([
            'data' => [
                'tenant_id'    => $tenant->id,
                'slug'         => $tenant->slug,
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'access_token' => $result['access_token'],
                'portal_url'   => 'https://'.$tenant->slug.'.'.config('app.domain').'/admin',
            ],
        ], 201);
    }
}
