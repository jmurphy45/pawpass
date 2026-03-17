<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PlatformSubscriptionEvent;
use App\Models\RawWebhook;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\StripeBillingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

class StripeBillingWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $billing,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.billing_webhook_secret');

        try {
            $event = $this->billing->constructWebhookEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        RawWebhook::create([
            'provider'    => 'stripe_billing',
            'event_id'    => $event->id,
            'payload'     => $payload,
            'received_at' => now(),
        ]);

        return match ($event->type) {
            'customer.subscription.created'  => $this->handleSubscriptionCreated($event->data->object),
            'customer.subscription.updated'  => $this->handleSubscriptionUpdated($event->data->object),
            'invoice.payment_succeeded'      => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed'         => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted'  => $this->handleSubscriptionDeleted($event->data->object),
            default => response()->json(['data' => 'ok']),
        };
    }

    private function resolveTenant(object $stripeSub): ?Tenant
    {
        $tenantId = $stripeSub->metadata->tenant_id ?? null;

        if (! $tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    private function handleSubscriptionCreated(object $stripeSub): JsonResponse
    {
        $tenant = $this->resolveTenant($stripeSub);

        if (! $tenant) {
            return response()->json(['data' => 'ok']);
        }

        $status = $stripeSub->status === 'trialing' ? 'trialing' : 'active';

        $tenant->update([
            'platform_stripe_sub_id'  => $stripeSub->id,
            'status'                  => $status,
            'plan_current_period_end' => Carbon::createFromTimestamp($stripeSub->current_period_end),
            'plan_cancel_at_period_end' => false,
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'subscribed',
            'payload'    => ['stripe_sub_id' => $stripeSub->id, 'status' => $status],
        ]);

        return response()->json(['data' => 'ok']);
    }

    private function handleSubscriptionUpdated(object $stripeSub): JsonResponse
    {
        $tenant = $this->resolveTenant($stripeSub);

        if (! $tenant) {
            return response()->json(['data' => 'ok']);
        }

        $tenant->update([
            'plan_current_period_end'   => Carbon::createFromTimestamp($stripeSub->current_period_end),
            'plan_cancel_at_period_end' => (bool) $stripeSub->cancel_at_period_end,
        ]);

        return response()->json(['data' => 'ok']);
    }

    private function handleInvoicePaymentSucceeded(object $invoice): JsonResponse
    {
        if (! ($invoice->subscription ?? null)) {
            return response()->json(['data' => 'ok']);
        }

        $tenant = Tenant::where('platform_stripe_sub_id', $invoice->subscription)->first();

        if (! $tenant) {
            return response()->json(['data' => 'ok']);
        }

        $previousPlan = $tenant->plan;

        $tenant->update(['plan_past_due_since' => null]);

        if ($tenant->status === 'past_due') {
            $tenant->update(['status' => 'active']);
        }

        if ($previousPlan !== $tenant->plan && $tenant->owner_user_id) {
            $this->notificationService->dispatch(
                'subscription.plan_changed',
                $tenant->id,
                $tenant->owner_user_id,
                ['plan' => $tenant->plan],
            );
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleInvoicePaymentFailed(object $invoice): JsonResponse
    {
        if (! ($invoice->subscription ?? null)) {
            return response()->json(['data' => 'ok']);
        }

        $tenant = Tenant::where('platform_stripe_sub_id', $invoice->subscription)->first();

        if (! $tenant) {
            return response()->json(['data' => 'ok']);
        }

        if (! $tenant->plan_past_due_since) {
            $tenant->update([
                'plan_past_due_since' => now(),
                'status'              => 'past_due',
            ]);
        }

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'payment_failed',
            'payload'    => ['invoice_id' => $invoice->id ?? null],
        ]);

        if ($tenant->owner_user_id) {
            $this->notificationService->dispatch(
                'subscription.payment_failed_platform',
                $tenant->id,
                $tenant->owner_user_id,
                [],
            );
        }

        return response()->json(['data' => 'ok']);
    }

    private function handleSubscriptionDeleted(object $stripeSub): JsonResponse
    {
        $tenant = $this->resolveTenant($stripeSub);

        if (! $tenant) {
            return response()->json(['data' => 'ok']);
        }

        $tenant->update([
            'status'                    => 'free_tier',
            'plan'                      => 'free',
            'platform_stripe_sub_id'    => null,
            'plan_current_period_end'   => null,
            'plan_cancel_at_period_end' => false,
            'plan_past_due_since'       => null,
        ]);

        PlatformSubscriptionEvent::create([
            'tenant_id'  => $tenant->id,
            'event_type' => 'downgraded',
            'payload'    => ['reason' => 'subscription_deleted'],
        ]);

        return response()->json(['data' => 'ok']);
    }
}
