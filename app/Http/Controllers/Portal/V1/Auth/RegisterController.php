<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(private JwtService $jwt) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        $emailExists = User::where('tenant_id', $tenantId)
            ->where('email', $request->email)
            ->exists();

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $user = DB::transaction(function () use ($request, $tenantId) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            $user = User::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $customer->update(['user_id' => $user->id]);

            return $user;
        });

        return response()->json([
            'data' => [
                'access_token' => $this->jwt->issue($user),
                'refresh_token' => $this->jwt->issueRefreshToken($user),
                'expires_in' => 900,
            ],
        ]);
    }
}
