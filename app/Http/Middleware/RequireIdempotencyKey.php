<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
                'error_code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ], 400);
        }

        $cacheKey = "idempotency:{$key}";

        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached['body'], $cached['status']);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'body' => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], now()->addDay());
        }

        return $response;
    }
}
