<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query();

        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return CustomerResource::collection($query->cursorPaginate(20));
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $tenantId = app('current.tenant.id');

        $customer = DB::transaction(function () use ($request, $tenantId) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'notes' => $request->notes,
            ]);

            if ($request->filled('email')) {
                $user = User::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'customer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $customer->update(['user_id' => $user->id]);
            }

            return $customer;
        });

        $tenant = Tenant::find($tenantId);
        if ($tenant->stripe_account_id) {
            $stripeCustomer = $this->stripe->createCustomer(
                $customer->email,
                $customer->name,
                $tenant->stripe_account_id,
            );
            $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
        }

        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        $customer->load('dogs');

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->only(['name', 'email', 'phone', 'notes']));

        return new CustomerResource($customer->fresh());
    }

    public function chargeBalance(Customer $customer): JsonResponse
    {
        if (auth()->user()->role !== 'business_owner') {
            abort(403);
        }

        if ($customer->outstanding_balance_cents <= 0) {
            return response()->json(['error' => 'NO_BALANCE_OWED'], 422);
        }

        if (! $customer->stripe_payment_method_id || ! $customer->stripe_customer_id) {
            return response()->json(['error' => 'NO_PAYMENT_METHOD'], 422);
        }

        $tenant = Tenant::find(app('current.tenant.id'));

        if (! $tenant->stripe_account_id) {
            abort(422);
        }

        $amountCents = $customer->outstanding_balance_cents;
        $feeCents = (int) round($amountCents * $tenant->effectivePlatformFeePct($amountCents) / 100);

        try {
            $pi = $this->stripe->createOutstandingBalancePaymentIntent(
                amountCents: $amountCents,
                stripeAccountId: $tenant->stripe_account_id,
                applicationFeeCents: $feeCents,
                stripeCustomerId: $customer->stripe_customer_id,
                paymentMethodId: $customer->stripe_payment_method_id,
                metadata: [
                    'customer_id' => $customer->id,
                    'tenant_id' => $tenant->id,
                ],
            );
        } catch (\Stripe\Exception\CardException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'CARD_DECLINED',
            ], 422);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'PAYMENT_ERROR',
            ], 502);
        }

        return response()->json(['data' => ['pi_id' => $pi->id, 'status' => $pi->status]], 202);
    }

    public function requestPaymentUpdate(Customer $customer): JsonResponse
    {
        if (! $customer->user_id) {
            return response()->json(['error' => 'NO_PORTAL_ACCESS'], 422);
        }

        $user = User::allTenants()->find($customer->user_id);

        if ($user) {
            $this->notificationService->dispatch(
                'payment.update_requested',
                app('current.tenant.id'),
                $user->id,
                ['portal_url' => route('portal.account')],
            );
        }

        return response()->json(['data' => ['message' => 'Notification sent']]);
    }
}
