<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->extractSubdomain($request);

        if (! $slug) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if ($tenant->status === 'suspended') {
            return response()->json(['message' => 'Tenant is suspended.'], 403);
        }

        $allowedStatuses = ['active', 'trialing', 'free_tier', 'past_due'];
        if (! in_array($tenant->status, $allowedStatuses, true)) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        app()->instance('current.tenant.id', $tenant->id);

        return $next($request);
    }

    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) < 2) {
            return null;
        }

        $subdomain = $parts[0];

        if ($subdomain === 'platform' || $subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }
}
