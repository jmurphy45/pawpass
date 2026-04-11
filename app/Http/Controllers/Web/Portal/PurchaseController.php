<?php

namespace App\Http\Controllers\Web\Portal;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use App\Services\PromotionService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly DogCreditService $creditService,
        private readonly NotificationService $notificationService,
        private readonly PromotionService $promotionService,
    ) {}

    public function index(): Response
    {
        $customer = Auth::user()->customer;

        $packages = Package::where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => [
                'id'                         => $p->id,
                'name'                       => $p->name,
                'description'                => $p->description,
                'type'                       => $p->type,
                'price_cents'                => (int) round((float) $p->price * 100),
                'credits'                    => $p->credit_count,
                'max_dogs'                   => $p->dog_limit,
                'duration_days'              => $p->duration_days,
                'is_featured'                => $p->is_featured,
                'is_auto_replenish_eligible' => (bool) $p->is_auto_replenish_eligible,
            ]);

        $dogs = $customer->dogs()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id'                        => $d->id,
                'name'                      => $d->name,
                'credits_expire_at'         => $d->credits_expire_at?->toIso8601String(),
                'auto_replenish_enabled'    => (bool) $d->auto_replenish_enabled,
                'auto_replenish_package_id' => $d->auto_replenish_package_id,
            ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        return Inertia::render('Portal/Purchase', [
            'packages'                   => $packages,
            'dogs'                       => $dogs,
            'stripe_key'                 => config('services.stripe.key'),
            'stripe_account_id'          => $tenant?->stripe_account_id,
            'auto_replenish_enabled'     => Feature::active('recurring_checkout'),
            'tax_enabled'                => (bool) $tenant?->tax_collection_enabled,
            'saved_card'                 => $customer->stripe_payment_method_id
                ? ['last4' => $customer->stripe_pm_last4, 'brand' => $customer->stripe_pm_brand]
                : null,
        ]);
    }

    public function store(Request $request, StripeService $stripe): JsonResponse
    {
        $request->validate([
            'package_id'     => ['required', 'string'],
            'dog_ids'        => ['required', 'array', 'min:1'],
            'dog_ids.*'      => ['required', 'string'],
            'save_card'      => ['sometimes', 'boolean'],
            'auto_replenish' => ['sometimes', 'boolean'],
        ]);

        $customer = Auth::user()->customer;
        $tenantId = app('current.tenant.id');
        $tenant   = Tenant::find($tenantId);

        abort_unless($tenant && $tenant->stripe_account_id, 422, 'Stripe not configured for this tenant.');

        $package = Package::findOrFail($request->package_id);

        $request->validate([
            'dog_ids' => ['max:' . $package->dog_limit],
        ]);

        $dogs = collect($request->dog_ids)->map(fn ($id) => $customer->dogs()->findOrFail($id));

        $amountCents = (int) round((float) $package->price * 100);
        $feePct      = $tenant->effectivePlatformFeePct($amountCents);
        $feeCents    = (int) round($amountCents * $feePct / 100);

        if ($customer->stripe_customer_id) {
            $stripeCustomerId = $customer->stripe_customer_id;
        } else {
            $stripeCustomer   = $stripe->createCustomer($customer->email ?? '', $customer->name, $tenant->stripe_account_id);
            $stripeCustomerId = $stripeCustomer->id;
            $customer->update(['stripe_customer_id' => $stripeCustomerId]);
        }

        $order = DB::transaction(function () use ($tenantId, $customer, $package, $dogs, $feePct, $amountCents) {
            $order = Order::create([
                'tenant_id'        => $tenantId,
                'customer_id'      => $customer->id,
                'package_id'       => $package->id,
                'status'           => 'pending',
                'total_amount'     => $package->price,
                'subtotal_cents'   => $amountCents,
                'platform_fee_pct' => $feePct,
            ]);

            foreach ($dogs as $dog) {
                $order->orderDogs()->create([
                    'dog_id'         => $dog->id,
                    'credits_issued' => 0,
                ]);
            }

            return $order;
        });

        // Validate the saved PM is still usable by ensuring it's attached to the customer.
        // If it's not (e.g., was saved without attachment), clear it so the frontend
        // falls back to the card element.
        $savedPmId = $customer->stripe_payment_method_id;
        if ($savedPmId && $stripeCustomerId) {
            try {
                $stripe->attachPaymentMethod($savedPmId, $stripeCustomerId, $tenant->stripe_account_id);
            } catch (ApiErrorException $e) {
                if (str_contains($e->getMessage(), 'already been attached')) {
                    // Already attached — PM is valid, no action needed
                } else {
                    // PM is permanently unusable; clear it so the user is shown the card input
                    $customer->update([
                        'stripe_payment_method_id' => null,
                        'stripe_pm_last4'           => null,
                        'stripe_pm_brand'           => null,
                    ]);
                    $savedPmId = null;
                }
            }
        }

        $taxAmountCents = 0;
        $taxCalcId = null;
        if ($tenant->tax_collection_enabled && !empty($tenant->billing_address['postal_code']) && $tenant->stripe_account_id) {
            try {
                $calculation = $stripe->calculateTax(
                    $amountCents,
                    'usd',
                    $tenant->stripe_account_id,
                    [
                        'postal_code' => $tenant->billing_address['postal_code'],
                        'country'     => $tenant->billing_address['country'] ?? 'US',
                    ],
                    $package->id,
                );
                $taxAmountCents = $calculation->tax_amount_exclusive;
                $taxCalcId = $calculation->id;
                $order->update(['tax_amount_cents' => $taxAmountCents, 'stripe_tax_calc_id' => $taxCalcId]);
            } catch (ApiErrorException $e) {
                // Tax calculation failed — proceed without tax
            }
        }
        $totalCents = $amountCents + $taxAmountCents;

        $saveCard = $request->boolean('save_card');

        try {
            $intent = $stripe->createPaymentIntent(
                amountCents: $totalCents,
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
                setupFutureUsage: $saveCard ? 'off_session' : null,
                paymentMethodTypes: ['card', 'us_bank_account'],
            );
        } catch (ApiErrorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        OrderLineItem::create([
            'tenant_id'       => $tenantId,
            'order_id'        => $order->id,
            'description'     => $package->name,
            'quantity'        => 1,
            'unit_price_cents' => $amountCents,
            'sort_order'      => 0,
        ]);

        OrderPayment::create([
            'tenant_id'   => $tenantId,
            'order_id'    => $order->id,
            'stripe_pi_id' => $intent->id,
            'amount_cents' => $totalCents,
            'type'         => PaymentType::Full,
            'status'       => 'pending',
        ]);

        return response()->json([
            'client_secret'     => $intent->client_secret,
            'payment_method_id' => $savedPmId,
            'tax_amount_cents'  => $taxAmountCents,
        ]);
    }

    public function checkPromo(Request $request): JsonResponse
    {
        $request->validate([
            'code'       => ['required', 'string'],
            'package_id' => ['required', 'string'],
        ]);

        $customer = Auth::user()->customer;
        $package  = Package::findOrFail($request->package_id);
        $subtotalCents = (int) round((float) $package->price * 100);

        $result = $this->promotionService->validate($request->code, $customer, $package, $subtotalCents);

        return response()->json([
            'valid'         => $result->valid,
            'discount_cents' => $result->discountCents,
            'message'       => $result->message,
        ]);
    }

    public function taxPreview(Request $request, StripeService $stripe): JsonResponse
    {
        $request->validate([
            'package_id' => ['required', 'string'],
        ]);

        $tenantId = app('current.tenant.id');
        $tenant   = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->tax_collection_enabled || empty($tenant->billing_address['postal_code']) || ! $tenant->stripe_account_id) {
            return response()->json(['tax_enabled' => false]);
        }

        $package = Package::findOrFail($request->package_id);
        $subtotalCents = (int) round((float) $package->price * 100);

        try {
            $calculation = $stripe->calculateTax(
                $subtotalCents,
                'usd',
                $tenant->stripe_account_id,
                [
                    'postal_code' => $tenant->billing_address['postal_code'],
                    'country'     => $tenant->billing_address['country'] ?? 'US',
                ],
                $package->id,
            );
        } catch (ApiErrorException $e) {
            return response()->json(['tax_enabled' => false]);
        }

        return response()->json([
            'tax_enabled'    => true,
            'subtotal_cents' => $subtotalCents,
            'tax_cents'      => $calculation->tax_amount_exclusive,
            'total_cents'    => $subtotalCents + $calculation->tax_amount_exclusive,
        ]);
    }

    public function confirm(Request $request, StripeService $stripe): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string'],
            'save_card'         => ['sometimes', 'boolean'],
            'auto_replenish'    => ['sometimes', 'boolean'],
        ]);

        $customer = Auth::user()->customer;

        $payment = OrderPayment::where('stripe_pi_id', $validated['payment_intent_id'])
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('order')
            ->first();

        if (!$payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $order = $payment->order;

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['status' => 'paid']);
        }

        $stripeAccountId = Tenant::find($order->tenant_id)?->stripe_account_id;
        $pi = $stripe->retrievePaymentIntent($validated['payment_intent_id'], $stripeAccountId);

        if ($pi->status !== 'succeeded') {
            return response()->json(['status' => $pi->status]);
        }

        DB::transaction(function () use ($order, $payment, $validated, $request) {
            $order->transitionTo(OrderStatus::Paid);
            $payment->transitionTo(PaymentStatus::Paid);
            $payment->update(['paid_at' => now()]);
            $order->load(['orderDogs.dog', 'package']);
            foreach ($order->orderDogs as $orderDog) {
                if ($order->package->type === 'unlimited') {
                    $this->creditService->issueUnlimitedPass($order, $orderDog->dog);
                } else {
                    $this->creditService->issueFromOrder($order, $orderDog->dog);
                }

                if ($request->boolean('auto_replenish') && $order->package->is_auto_replenish_eligible) {
                    $orderDog->dog->update([
                        'auto_replenish_enabled'    => true,
                        'auto_replenish_package_id' => $order->package_id,
                    ]);
                }
            }
        });

        if ($request->boolean('save_card') && $pi->payment_method) {
            $pm = $stripe->retrievePaymentMethod($pi->payment_method, $stripeAccountId);
            if ($customer->stripe_customer_id) {
                try {
                    $stripe->attachPaymentMethod($pm->id, $customer->stripe_customer_id, $stripeAccountId);
                } catch (ApiErrorException) {
                    // Already attached — safe to ignore
                }
            }
            $customer->update([
                'stripe_payment_method_id' => $pm->id,
                'stripe_pm_last4'          => $pm->card?->last4,
                'stripe_pm_brand'          => $pm->card?->brand,
            ]);
        }

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
