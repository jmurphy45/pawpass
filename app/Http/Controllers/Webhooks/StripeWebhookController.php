<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RawWebhook;
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

        RawWebhook::create([
            'provider' => 'stripe',
            'event_id' => $event->id,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        return match ($event->type) {
            'payment_intent.succeeded'      => $this->handlePaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event->data->object),
            'charge.dispute.created'        => $this->handleDisputeCreated($event->data->object),
            'charge.dispute.closed'         => $this->handleDisputeClosed($event->data->object),
            'account.updated'               => $this->handleAccountUpdated($event->data->object),
            default                         => response()->json(['data' => 'ok']),
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
                if ($order->package->type === 'unlimited') {
                    $this->creditService->issueUnlimitedPass($order, $orderDog->dog);
                } else {
                    $this->creditService->issueFromOrder($order, $orderDog->dog);
                }
            }
        });

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

    private function handlePaymentIntentFailed(object $pi): JsonResponse
    {
        $order = Order::where('stripe_pi_id', $pi->id)->first();

        if ($order) {
            Log::warning('payment_intent.failed', ['order_id' => $order->id, 'pi_id' => $pi->id]);
            $order->update(['status' => 'failed']);

            $isAutoReplenish = ($pi->metadata->auto_replenish ?? null) === 'true';
            if ($isAutoReplenish) {
                $order->load('customer');
                $userId = $order->customer?->user_id;
                if ($userId) {
                    $this->notificationService->dispatch('auto_replenish.failed', $order->tenant_id, $userId, ['order_id' => $order->id]);
                }
            }
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
}
