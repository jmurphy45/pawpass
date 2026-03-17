<?php

namespace App\Http\Controllers\Auth;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class LoginController extends Controller
{
    public function __construct(private JwtService $jwt) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::allTenants()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $accessToken = $this->jwt->issue($user);
        $refreshToken = $this->jwt->issueRefreshToken($user);

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 900,
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = $request->input('refresh_token');

        if (! $token) {
            return response()->json(['message' => 'Refresh token required.'], 422);
        }

        try {
            $tokens = $this->jwt->rotateRefreshToken($token);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json(['data' => $tokens]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->input('refresh_token');

        if ($token) {
            [$id] = array_pad(explode('|', $token, 2), 2, null);
            if ($id) {
                \Laravel\Sanctum\PersonalAccessToken::find($id)?->delete();
            }
        }

        return response()->json(['data' => null], 200);
    }
}
