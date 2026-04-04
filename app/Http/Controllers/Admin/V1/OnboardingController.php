<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function createAccount(): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        if ($tenant->stripe_account_id) {
            return response()->json([
                'message' => 'Connect account already exists.',
                'error_code' => 'ALREADY_CONNECTED',
            ], 409);
        }

        $account = $this->stripe->createConnectAccount(
            auth()->user()->email,
            $tenant->name,
            $tenant->billing_address,
            "https://{$tenant->slug}.pawpass.com",
            auth()->user()->name,
        );
        $tenant->update(['stripe_account_id' => $account->id]);

        return response()->json(['data' => ['stripe_account_id' => $account->id]], 201);
    }

    public function createAccountLink(Request $request): JsonResponse
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->stripe_account_id) {
            return response()->json([
                'message' => 'No Connect account. Create one first.',
                'error_code' => 'NO_CONNECT_ACCOUNT',
            ], 422);
        }

        $validated = $request->validate([
            'refresh_url' => ['required', 'url'],
            'return_url' => ['required', 'url'],
        ]);

        $link = $this->stripe->createAccountLink(
            $tenant->stripe_account_id,
            $validated['refresh_url'],
            $validated['return_url']
        );

        return response()->json(['data' => ['url' => $link->url]]);
    }
}
