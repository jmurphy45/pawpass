<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private NotificationService $notifications,
    ) {}

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

        $token = Str::random(64);

        $user = DB::transaction(function () use ($request, $tenantId, $token) {
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
                'status' => 'pending_verification',
                'email_verify_token' => $token,
                'email_verify_expires_at' => now()->addHours(24),
            ]);

            $customer->update(['user_id' => $user->id]);

            return $user;
        });

        $this->notifications->dispatch('auth.verify_email', $tenantId, $user->id, [
            'name' => $user->name,
            'verify_url' => url('/my/verify-email?token='.$token),
        ]);

        return response()->json([
            'data' => ['message' => 'Registration successful. Please check your email to verify your account.'],
        ], 202);
    }
}
