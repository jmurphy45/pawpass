<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return CustomerResource::collection($query->cursorPaginate(20));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
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
                $customer->email ?? '',
                $customer->name,
                $tenant->stripe_account_id,
            );
            $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
        }

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load('dogs');

        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->only(['name', 'email', 'phone', 'notes']));

        return response()->json(['data' => new CustomerResource($customer->fresh())]);
    }
}
