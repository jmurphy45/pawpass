<?php

namespace App\Http\Controllers\Web\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function show(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone'    => ['nullable', 'string', 'max:30'],
        ]);

        $tenantId = app('current.tenant.id');

        $emailExists = User::where('tenant_id', $tenantId)
            ->where('email', $validated['email'])
            ->exists();

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $customer = null;

        $user = DB::transaction(function () use ($validated, $tenantId, &$customer) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'] ?? null,
            ]);

            $user = User::create([
                'tenant_id'         => $tenantId,
                'customer_id'       => $customer->id,
                'name'              => $validated['name'],
                'email'             => $validated['email'],
                'password'          => $validated['password'],
                'phone'             => $validated['phone'] ?? null,
                'role'              => 'customer',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            $customer->update(['user_id' => $user->id]);

            return $user;
        });

        $tenant = Tenant::find($tenantId);
        if ($tenant->stripe_account_id && $customer) {
            try {
                $stripeCustomer = $this->stripe->createCustomer(
                    $customer->email ?? '',
                    $customer->name,
                    $tenant->stripe_account_id,
                );
                $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
            } catch (\Throwable $e) {
                Log::warning('Portal registration Stripe sync failed', [
                    'customer_id' => $customer->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }
}
