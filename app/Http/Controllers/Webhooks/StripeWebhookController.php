<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RawWebhook;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Carbon\Carbon;
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

        RawWebhook::create([
            'provider' => 'stripe',
            'event_id' => $event->id,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        return match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event->data->object),
            'charge.dispute.created' => $this->handleDisputeCreated($event->data->object),
            'charge.dispute.closed' => $this->handleDisputeClosed($event->data->object),
            'setup_intent.succeeded' => $this->handleSetupIntentSucceeded($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'account.updated' => $this->handleAccountUpdated($event->data->object),
            default => response()->json(['data' => 'ok']),
        };
    }

    private function handlePaymentIntentSucceeded(object $pi): JsonResponse
    {
        $order = Order::where('stripe_pi_id', $pi->id)->first();

        if (! $order) {
            return response()->json(['data' => 'ok']);
        }

        if ($order->status === 'paid') {
            return response()->json(['data' => 'ok']);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'paid', 'paid_at' => now()]);

            $order->load(['orderDogs.dog', 'package']);

            foreach ($order->orderDogs as $orderDog) {
                $this->creditService->issueFromOrder($order, $orderDog->dog);
            }
        });

        $order->load('customer');
        if ($order->customer?->user_id) {
            $this->notificationService->dispatch('payment.confirmed', $order->tenant_id, $order->customer->user_id, ['order_id' => $order->id]);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handlePaymentIntentFailed(object $pi): JsonResponse
    {
        $order = Order::where('stripe_pi_id', $pi->id)->first();

        if ($order) {
            Log::warning('payment_intent.failed', ['order_id' => $order->id, 'pi_id' => $pi->id]);
            $order->update(['status' => 'failed']);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleDisputeCreated(object $dispute): JsonResponse
    {
        $piId = $dispute->payment_intent ?? null;

        if ($piId) {
            $order = Order::where('stripe_pi_id', $piId)->first();
            $order?->update(['status' => 'disputed']);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleDisputeClosed(object $dispute): JsonResponse
    {
        return response()->json(['data' => 'ok']);
    }

    private function handleSetupIntentSucceeded(object $setupIntent): JsonResponse
    {
        $localSubId = $setupIntent->metadata->local_subscription_id ?? null;

        if (! $localSubId) {
            return response()->json(['data' => 'ok']);
        }

        $subscription = Subscription::allTenants()->find($localSubId);

        if (! $subscription) {
            return response()->json(['data' => 'ok']);
        }

        $package = $subscription->package;

        if (! $package->stripe_price_id) {
            Log::error('setup_intent.succeeded: package has no stripe_price_id', [
                'subscription_id' => $subscription->id,
                'package_id' => $package->id,
            ]);

            return response()->json(['data' => 'ok']);
        }

        $tenant = Tenant::find($subscription->tenant_id);

        if (! $tenant) {
            Log::error('setup_intent.succeeded: tenant not found', [
                'subscription_id' => $subscription->id,
                'tenant_id'       => $subscription->tenant_id,
            ]);

            return response()->json(['data' => 'tenant not found'], 422);
        }

        $stripeSub = $this->stripe->createSubscription(
            $subscription->stripe_customer_id,
            $package->stripe_price_id,
            $setupIntent->payment_method,
            $tenant->stripe_account_id,
            (float) $tenant->platform_fee_pct,
            ['local_subscription_id' => $subscription->id],
        );

        $subscription->update([
            'stripe_sub_id' => $stripeSub->id,
            'current_period_start' => Carbon::createFromTimestamp($stripeSub->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($stripeSub->current_period_end),
        ]);

        return response()->json(['data' => 'ok']);
    }

    private function handleInvoicePaymentSucceeded(object $invoice): JsonResponse
    {
        if (! ($invoice->subscription ?? null)) {
            return response()->json(['data' => 'ok']);
        }

        $subscription = Subscription::allTenants()
            ->where('stripe_sub_id', $invoice->subscription)
            ->first();

        if (! $subscription) {
            return response()->json(['data' => 'ok']);
        }

        if ($subscription->status !== 'active') {
            return response()->json(['data' => 'ok']);
        }

        $period = $invoice->lines->data[0]->period ?? null;
        $periodStart = $period ? Carbon::createFromTimestamp($period->start) : now();
        $periodEnd = $period ? Carbon::createFromTimestamp($period->end) : now()->addMonth();

        $subscription->update([
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
        ]);

        $dog = $subscription->dog;

        $this->creditService->issueFromSubscription($subscription, $dog, $periodEnd);

        $userId = $dog->customer?->user_id;
        if ($userId) {
            $this->notificationService->dispatch('subscription.renewed', $subscription->tenant_id, $userId, ['subscription_id' => $subscription->id]);
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleInvoicePaymentFailed(object $invoice): JsonResponse
    {
        if (! ($invoice->subscription ?? null)) {
            return response()->json(['data' => 'ok']);
        }

        $subscription = Subscription::allTenants()
            ->where('stripe_sub_id', $invoice->subscription)
            ->first();

        if (! $subscription) {
            return response()->json(['data' => 'ok']);
        }

        $subscription->update(['status' => 'past_due']);

        $userId = $subscription->dog->customer?->user_id;
        if ($userId) {
            $this->notificationService->dispatch('subscription.payment_failed', $subscription->tenant_id, $userId, ['subscription_id' => $subscription->id]);
        }

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

    private function handleSubscriptionDeleted(object $stripeSub): JsonResponse
    {
        $subscription = Subscription::allTenants()
            ->where('stripe_sub_id', $stripeSub->id)
            ->first();

        if (! $subscription) {
            return response()->json(['data' => 'ok']);
        }

        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $dog = $subscription->dog;
            $this->creditService->expireCredits($dog);
        });

        $userId = $subscription->dog->customer?->user_id;
        if ($userId) {
            $this->notificationService->dispatch('subscription.cancelled', $subscription->tenant_id, $userId, ['subscription_id' => $subscription->id]);
        }

        return response()->json(['data' => 'ok']);
    }
}
