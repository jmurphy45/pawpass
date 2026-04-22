<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (Feature::inactive($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'PLAN_FEATURE_NOT_AVAILABLE',
                    'message' => "Feature '{$feature}' is not available on your current plan.",
                ], 403);
            }

            return redirect()->route('admin.billing.index')
                ->with('error', 'This feature is not available on your current plan. Upgrade to unlock it.');
        }

        return $next($request);
    }
}
