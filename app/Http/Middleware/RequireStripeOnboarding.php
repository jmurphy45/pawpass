<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStripeOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant || ! $tenant->stripe_account_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'STRIPE_ACCOUNT_PROVISIONING',
                    'message' => 'Stripe account is still being provisioned. Please try again shortly.',
                ], 422);
            }

            return redirect()->route('admin.billing.index')
                ->with('error', 'Your Stripe payment account is still being set up. Please try again shortly.');
        }

        if (! $tenant->stripe_onboarded_at) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'STRIPE_ONBOARDING_INCOMPLETE',
                    'message' => 'Stripe onboarding is not complete. Please complete setup in Billing.',
                ], 422);
            }

            return redirect()->route('admin.billing.index')
                ->with('error', 'Please complete your Stripe payment setup before continuing.');
        }

        return $next($request);
    }
}
