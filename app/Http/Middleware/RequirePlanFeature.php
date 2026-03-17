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
            return response()->json([
                'error'   => 'PLAN_FEATURE_NOT_AVAILABLE',
                'message' => "Feature '{$feature}' is not available on your current plan.",
            ], 403);
        }

        return $next($request);
    }
}
