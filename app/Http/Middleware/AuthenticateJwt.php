<?php

namespace App\Http\Middleware;

use App\Auth\JwtService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            if ($user = Auth::guard('web')->user()) {
                Auth::setUser($user);
                $request->setUserResolver(fn () => $user);

                return $next($request);
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (Throwable) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::allTenants()->find($claims->sub);

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        app()->instance('current.tenant.id', $claims->tenant_id ?? null);

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
