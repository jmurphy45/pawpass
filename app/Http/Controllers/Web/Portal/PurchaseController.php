<?php

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly DogCreditService $creditService,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $packages = Package::where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => [
                'id'                     => $p->id,
                'name'                   => $p->name,
                'description'            => $p->description,
                'type'                   => $p->type,
                'price_cents'            => (int) round((float) $p->price * 100),
                'credits'                => $p->credit_count,
                'max_dogs'               => $p->dog_limit,
                'billing_interval'       => $p->type === 'subscription' ? 'monthly' : null,
                'has_monthly_price'      => $p->stripe_price_id_monthly !== null,
                'duration_days'          => $p->duration_days,
                'is_featured'            => $p->is_featured,
                'is_recurring_enabled'   => $p->is_recurring_enabled,
                'recurring_interval_days' => $p->recurring_interval_days,
            ]);

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id'               => $d->id,
                'name'             => $d->name,
                'credits_expire_at' => $d->credits_expire_at?->toIso8601String(),
            ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        return Inertia::render('Portal/Purchase', [
            'packages'          => $packages,
            'dogs'              => $dogs,
            'stripe_key'        => config('services.stripe.key'),
            'stripe_account_id' => $tenant?->stripe_account_id,
        ]);
    }

    public function store(Request $request, StripeService $stripe): JsonResponse
    {
        $request->validate([
            'package_id'   => ['required', 'string'],
            'dog_ids'      => ['required', 'array', 'min:1'],
            'dog_ids.*'    => ['required', 'string'],
            'billing_mode' => ['sometimes', 'string', 'in:one_time,subscription,recurring'],
        ]);

        $billingMode = $request->input('billing_mode', 'one_time');
        $customer    = Auth::user()->customer;
        $tenantId    = app('current.tenant.id');
        $tenant      = Tenant::find($tenantId);

        abort_unless($tenant && $tenant->stripe_account_id, 422, 'Stripe not configured for this tenant.');

        $package = Package::findOrFail($request->package_id);

        // --- Subscription billing mode ---
        if ($billingMode === 'subscription') {
            abort_unless(
                $package->stripe_price_id_monthly,
                422,
                'This package is not available for monthly subscription billing.',
            );

            $dog = $customer->dogs()->findOrFail($request->dog_ids[0]);

            $existingActive = Subscription::where('dog_id', $dog->id)
                ->where('package_id', $package->id)
                ->where('status', 'active')
                ->first();

            if ($existingActive) {
                return response()->json([
                    'message'    => 'This dog already has an active subscription for this package.',
                    'error_code' => 'ALREADY_SUBSCRIBED',
                ], 409);
            }

            if ($customer->stripe_customer_id) {
                $stripeCustomerId = $customer->stripe_customer_id;
            } else {
                $stripeCustomer   = $stripe->createCustomer($customer->email ?? '', $customer->name, $tenant->stripe_account_id);
                $stripeCustomerId = $stripeCustomer->id;
                $customer->update(['stripe_customer_id' => $stripeCustomerId]);
            }

            $subscription = Subscription::create([
                'tenant_id'          => $tenant->id,
                'customer_id'        => $customer->id,
                'package_id'         => $package->id,
                'dog_id'             => $dog->id,
                'status'             => 'active',
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            $setupIntent = $stripe->createSetupIntent(
                $stripeCustomerId,
                ['local_subscription_id' => $subscription->id],
                $tenant->stripe_account_id,
            );

            return response()->json([
                'client_secret'   => $setupIntent->client_secret,
                'subscription_id' => $subscription->id,
            ], 201);
        }

        // --- Recurring billing mode (non-native subscription using SetupIntent) ---
        if ($billingMode === 'recurring') {
            abort_unless(
                $package->is_recurring_enabled && $package->stripe_price_id_recurring,
                422,
                'This package is not available for recurring billing.',
            );

            $dog = $customer->dogs()->findOrFail($request->dog_ids[0]);

            if ($customer->stripe_customer_id) {
                $stripeCustomerId = $customer->stripe_customer_id;
            } else {
                $stripeCustomer   = $stripe->createCustomer($customer->email ?? '', $customer->name, $tenant->stripe_account_id);
                $stripeCustomerId = $stripeCustomer->id;
                $customer->update(['stripe_customer_id' => $stripeCustomerId]);
            }

            $subscription = Subscription::create([
                'tenant_id'          => $tenant->id,
                'customer_id'        => $customer->id,
                'package_id'         => $package->id,
                'dog_id'             => $dog->id,
                'status'             => 'active',
                'stripe_customer_id' => $stripeCustomerId,
            ]);

            $setupIntent = $stripe->createSetupIntent(
                $stripeCustomerId,
                ['local_subscription_id' => $subscription->id],
                $tenant->stripe_account_id,
            );

            return response()->json([
                'client_secret'   => $setupIntent->client_secret,
                'subscription_id' => $subscription->id,
            ], 201);
        }

        // --- One-time purchase flow ---
        $request->validate([
            'dog_ids' => ['max:' . $package->dog_limit],
        ]);

        $dogs = collect($request->dog_ids)->map(fn ($id) => $customer->dogs()->findOrFail($id));

        $amountCents = (int) round((float) $package->price * 100);
        $feePct      = (float) ($tenant->platform_fee_pct ?? 5);
        $feeCents    = (int) round($amountCents * $feePct / 100);

        if ($customer->stripe_customer_id) {
            $stripeCustomerId = $customer->stripe_customer_id;
        } else {
            $stripeCustomer   = $stripe->createCustomer($customer->email ?? '', $customer->name, $tenant->stripe_account_id);
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $order = DB::transaction(function () use ($tenantId, $customer, $package, $dogs) {
            $order = Order::create([
                'tenant_id'        => $tenantId,
                'customer_id'      => $customer->id,
                'package_id'       => $package->id,
                'status'           => 'pending',
                'total_amount'     => $package->price,
                'platform_fee_pct' => $customer->tenant->platform_fee_pct,
            ]);

            foreach ($dogs as $dog) {
                $order->orderDogs()->create([
                    'dog_id'         => $dog->id,
                    'credits_issued' => 0,
                ]);
            }

            return $order;
        });

        $intent = $stripe->createPaymentIntent(
            amountCents: $amountCents,
            currency: 'usd',
            stripeAccountId: $tenant->stripe_account_id,
            applicationFeeCents: $feeCents,
            metadata: [
                'order_id'    => $order->id,
                'tenant_id'   => $tenantId,
                'customer_id' => $customer->id,
                'package_id'  => $package->id,
                'dog_ids'     => $dogs->pluck('id')->implode(','),
            ],
            stripeCustomerId: $stripeCustomerId,
        );

        $order->update(['stripe_pi_id' => $intent->id]);

        return response()->json(['client_secret' => $intent->client_secret]);
    }

    public function confirm(Request $request, StripeService $stripe): JsonResponse
    {
        $validated = $request->validate(['payment_intent_id' => ['required', 'string']]);

        $customer = Auth::user()->customer;

        $order = Order::where('stripe_pi_id', $validated['payment_intent_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (!$order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($order->status === 'paid') {
            return response()->json(['status' => 'paid']);
        }

        $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
        $pi = $stripe->retrievePaymentIntent($validated['payment_intent_id'], $stripeAccountId);

        if ($pi->status !== 'succeeded') {
            return response()->json(['status' => $pi->status]);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $order->load(['orderDogs.dog', 'package']);
            foreach ($order->orderDogs as $orderDog) {
                if ($order->package->type === 'unlimited') {
                    $this->creditService->issueUnlimitedPass($order, $orderDog->dog);
                } else {
                    $this->creditService->issueFromOrder($order, $orderDog->dog);
                }
            }
        });

        $order->load('customer');
        if ($order->customer?->user_id) {
            $this->notificationService->dispatch(
                'payment.confirmed',
                $order->tenant_id,
                $order->customer->user_id,
                ['order_id' => $order->id]
            );
        }

        return response()->json(['status' => 'paid']);
    }
}
