<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly DogCreditService $creditService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $inserted = DB::table('raw_webhooks')->insertOrIgnore([
            'provider' => 'stripe',
            'event_id' => $event->id,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['data' => 'ok']);
        }

        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event->data->object),
            'payment_intent.amount_capturable_updated' => $this->handleDepositAuthorized($event->data->object),
            'charge.dispute.created' => $this->handleDisputeCreated($event->data->object),
            'charge.dispute.closed' => $this->handleDisputeClosed($event->data->object),
            'account.updated' => $this->handleAccountUpdated($event->data->object),
            'payment_intent.canceled' => $this->handlePaymentIntentCanceled($event->data->object),
            'setup_intent.succeeded' => $this->handleSetupIntentSucceeded($event->data->object),
            default => response()->json(['data' => 'ok']),
        };
    }

    private function handlePaymentIntentSucceeded(object $pi): JsonResponse
    {
        if (($pi->metadata->charge_type ?? null) === 'outstanding_balance') {
            return $this->handleOutstandingBalanceCharged($pi);
        }

        $payment = OrderPayment::where('stripe_pi_id', $pi->id)->with('order')->first();
        $order = $payment?->order;

        if (! $order) {
            return response()->json(['data' => 'ok']);
        }

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['data' => 'ok']);
        }

        DB::transaction(function () use ($order, $payment) {
            // Re-read with a lock so concurrent confirm + webhook calls don't both issue credits
            $order = Order::lockForUpdate()->find($order->id);
            if ($order->status === OrderStatus::Paid) {
                return;
            }

            $payment->transitionTo(PaymentStatus::Paid);
            $payment->update(['paid_at' => now()]);
            $order->transitionTo(OrderStatus::Paid);

            if ($order->type !== OrderType::Daycare) {
                return;
            }

            $order->load(['orderDogs.dog', 'package']);

            foreach ($order->orderDogs as $orderDog) {
                if ($order->package->type === 'unlimited') {
                    $this->creditService->issueUnlimitedPass($order, $orderDog->dog);
                } else {
                    $this->creditService->issueFromOrder($order, $orderDog->dog);
                }
            }
        });

        $tenant = \App\Models\Tenant::find($order->tenant_id);

        $platformFeeCents = (int) ($pi->application_fee_amount ?? 0);
        if ($platformFeeCents > 0) {
            $order->increment('platform_fee_amount_cents', $platformFeeCents);
        }

        if ($tenant?->stripe_account_id) {
            try {
                $processingFee = $this->stripe->retrieveProcessingFee($pi->id, $tenant->stripe_account_id);
                if ($processingFee !== null) {
                    $order->increment('processing_fee_amount_cents', $processingFee);
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                Log::warning('Failed to retrieve processing fee', ['pi_id' => $pi->id, 'error' => $e->getMessage()]);
            }
        }

        $taxCalcId = $pi->metadata->tax_calculation_id ?? null;
        $autoTaxCalc = $pi->automatic_tax?->calculation ?? null;

        // Stripe auto-creates the tax transaction when automatic_tax.calculation is set on the PI.
        // Only create manually for older PIs that used the metadata-only approach.
        if ($taxCalcId && ! $autoTaxCalc && $tenant?->stripe_account_id) {
            $this->stripe->createTaxTransaction($taxCalcId, $order->id, $tenant->stripe_account_id);
        }

        $order->load('customer');
        $userId = $order->customer?->user_id;

        if ($userId) {
            $this->notificationService->dispatch('payment.confirmed', $order->tenant_id, $userId, ['order_id' => $order->id]);

            $isAutoReplenish = ($pi->metadata->auto_replenish ?? null) === 'true';
            if ($isAutoReplenish) {
                $this->notificationService->dispatch('auto_replenish.succeeded', $order->tenant_id, $userId, ['order_id' => $order->id]);
            }
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleOutstandingBalanceCharged(object $pi): JsonResponse
    {
        $customerId = $pi->metadata->customer_id ?? null;

        if (! $customerId) {
            return response()->json(['data' => 'ok']);
        }

        $customer = Customer::allTenants()->find($customerId);

        if (! $customer) {
            return response()->json(['data' => 'ok']);
        }

        $amountCents = (int) $pi->amount;

        DB::table('customers')
            ->where('id', $customer->id)
            ->update([
                'outstanding_balance_cents' => DB::raw("GREATEST(0, outstanding_balance_cents - {$amountCents})"),
                'charge_pending_at' => null,
            ]);

        Order::allTenants()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Failed)
            ->get()
            ->each(function ($order) {
                $order->transitionTo(OrderStatus::Paid);

                if ($order->type !== OrderType::Daycare) {
                    return;
                }

                $order->load(['orderDogs.dog', 'package']);
                foreach ($order->orderDogs as $orderDog) {
                    if ($order->package->type === 'unlimited') {
                        $this->creditService->issueUnlimitedPass($order, $orderDog->dog);
                    } else {
                        $this->creditService->issueFromOrder($order, $orderDog->dog);
                    }
                }
            });

        return response()->json(['data' => 'ok']);
    }

    private function handlePaymentIntentFailed(object $pi): JsonResponse
    {
        if (($pi->metadata->charge_type ?? null) === 'outstanding_balance') {
            $customerId = $pi->metadata->customer_id ?? null;
            if ($customerId) {
                DB::table('customers')->where('id', $customerId)->update(['charge_pending_at' => null]);
            }

            return response()->json(['data' => 'ok']);
        }

        $payment = OrderPayment::where('stripe_pi_id', $pi->id)->with('order')->first();
        $order = $payment?->order;

        if (! $order || $order->status === OrderStatus::Failed) {
            return response()->json(['data' => 'ok']);
        }

        Log::warning('payment_intent.failed', ['order_id' => $order->id, 'pi_id' => $pi->id]);
        $payment->transitionTo(PaymentStatus::Failed);
        $order->transitionTo(OrderStatus::Failed);

        $order->load('customer');

        if ($customer = $order->customer) {
            $customer->increment('outstanding_balance_cents', $payment->amount_cents);
        }

        $isAutoReplenish = ($pi->metadata->auto_replenish ?? null) === 'true';
        if ($isAutoReplenish) {
            $userId = $order->customer?->user_id;
            if ($userId) {
                $this->notificationService->dispatch('auto_replenish.failed', $order->tenant_id, $userId, ['order_id' => $order->id]);
            }
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleDepositAuthorized(object $pi): JsonResponse
    {
        $payment = OrderPayment::where('stripe_pi_id', $pi->id)->with('order.reservation')->first();
        $reservation = $payment?->order?->reservation;

        if ($reservation && $reservation->status === 'pending') {
            $payment->transitionTo(PaymentStatus::Authorized);
            $payment->order->transitionTo(OrderStatus::Authorized);
            $reservation->update(['status' => 'confirmed']);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleDisputeCreated(object $dispute): JsonResponse
    {
        $piId = $dispute->payment_intent ?? null;

        if ($piId) {
            $payment = OrderPayment::where('stripe_pi_id', $piId)->with('order')->first();
            if ($payment) {
                $payment->transitionTo(PaymentStatus::Disputed);
                $payment->order?->transitionTo(OrderStatus::Disputed);
            }
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleDisputeClosed(object $dispute): JsonResponse
    {
        return response()->json(['data' => 'ok']);
    }

    private function handleSetupIntentSucceeded(object $si): JsonResponse
    {
        $subscriptionId = $si->metadata->local_subscription_id ?? null;

        if (! $subscriptionId) {
            return response()->json(['data' => 'ok']);
        }

        $subscription = Subscription::find($subscriptionId);

        if (! $subscription || ! $subscription->canTransitionTo(SubscriptionStatus::Active)) {
            return response()->json(['data' => 'ok']);
        }

        $subscription->transitionTo(SubscriptionStatus::Active);

        return response()->json(['data' => 'ok']);
    }

    private function handleAccountUpdated(object $account): JsonResponse
    {
        if (! ($account->charges_enabled ?? false)) {
            return response()->json(['data' => 'ok']);
        }

        $tenant = Tenant::where('stripe_account_id', $account->id)->first();
        if ($tenant && ! $tenant->stripe_onboarded_at) {
            $tenant->update(['stripe_onboarded_at' => now()]);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handlePaymentIntentCanceled(object $pi): JsonResponse
    {
        $payment = OrderPayment::where('stripe_pi_id', $pi->id)->with('order')->first();
        $order = $payment?->order;

        if (! $order || $order->status === OrderStatus::Canceled) {
            return response()->json(['data' => 'ok']);
        }

        $payment->transitionTo(PaymentStatus::Canceled);
        $order->transitionTo(OrderStatus::Canceled);

        return response()->json(['data' => 'ok']);
    }
}
