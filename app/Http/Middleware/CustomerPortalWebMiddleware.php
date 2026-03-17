<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerPortalWebMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user || $user->role !== 'customer') {
            return redirect()->route('portal.login');
        }

        $tenantId = app('current.tenant.id');
        if ($user->tenant_id !== $tenantId) {
            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
