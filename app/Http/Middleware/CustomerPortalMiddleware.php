<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerPortalMiddleware extends TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, function (Request $req) use ($next) {
            $user = Auth::user();

            if (! $user || $user->role !== 'customer') {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            $tenantId = app('current.tenant.id');
            if ($user->tenant_id !== $tenantId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return $next($req);
        });

        return $response;
    }
}
