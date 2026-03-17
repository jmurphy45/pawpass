<?php

namespace App\Jobs;

use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireTrials implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService): void
    {
        Tenant::where('status', 'trialing')
            ->where('trial_ends_at', '<', now())
            ->whereNotNull('owner_user_id')
            ->each(function (Tenant $tenant) use ($notificationService) {
                $tenant->update([
                    'status'                 => 'free_tier',
                    'plan'                   => 'free',
                    'platform_stripe_sub_id' => null,
                    'plan_current_period_end' => null,
                    'plan_cancel_at_period_end' => false,
                ]);

                PlatformSubscriptionEvent::create([
                    'tenant_id'  => $tenant->id,
                    'event_type' => 'trial_expired',
                    'payload'    => ['trial_ends_at' => $tenant->trial_ends_at?->toIso8601String()],
                ]);

                $notificationService->dispatch(
                    'trial.expired',
                    $tenant->id,
                    $tenant->owner_user_id,
                    [],
                );
            });
    }
}
