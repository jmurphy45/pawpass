<?php

namespace App\Http\Controllers\Portal\V1\Auth;

use App\Auth\JwtService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __construct(
        private JwtService $jwt,
        private NotificationService $notifications,
        private StripeService $stripe,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $tenantId = app('current.tenant.id');

        // Check for any existing customer record (PIMS-synced or staff-created) with this email.
        $existingCustomer = Customer::where('tenant_id', $tenantId)
            ->where('email', $request->email)
            ->first();

        if ($existingCustomer) {
            // Account already has a login — direct them to sign in.
            if ($existingCustomer->user_id) {
                throw ValidationException::withMessages([
                    'email' => ['An account already exists for this email. Please log in.'],
                ]);
            }

            // PIMS-synced or staff-created customer without a login yet — create user and link.
            $user = DB::transaction(function () use ($request, $tenantId, $existingCustomer) {
                $user = User::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $existingCustomer->id,
                    'name' => $existingCustomer->name,
                    'email' => $existingCustomer->email,
                    'password' => Hash::make($request->password),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $existingCustomer->update(['user_id' => $user->id]);

                return $user;
            });

            return response()->json([
                'data' => ['message' => 'Account activated. You can now log in.'],
            ], 200);
        }

        // Standard path — no existing customer record.
        $emailTaken = User::where('tenant_id', $tenantId)
            ->where('email', $request->email)
            ->exists();

        if ($emailTaken) {
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
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'status' => 'pending_verification',
                'email_verify_token' => $token,
                'email_verify_expires_at' => now()->addHours(24),
            ]);

            $customer->update(['user_id' => $user->id]);

            return $user;
        });

        $tenant = Tenant::find($tenantId);
        if ($tenant->stripe_account_id) {
            try {
                $stripeCustomer = $this->stripe->createCustomer(
                    $user->email,
                    $user->name,
                    $tenant->stripe_account_id,
                );
                Customer::where('id', $user->customer_id)->update(['stripe_customer_id' => $stripeCustomer->id]);
            } catch (\Throwable $e) {
                Log::warning('Portal API registration Stripe sync failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->notifications->dispatch('auth.verify_email', $tenantId, $user->id, [
            'name' => $user->name,
            'verify_url' => url('/my/verify-email?token='.$token),
        ]);

        return response()->json([
            'data' => ['message' => 'Registration successful. Please check your email to verify your account.'],
        ], 202);
    }
}
