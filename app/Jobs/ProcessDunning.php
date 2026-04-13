<?php

namespace App\Jobs;

use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDunning implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(NotificationService $notificationService): void
    {
        $cutoff = now()->subDays(21);

        Tenant::where('status', 'past_due')
            ->where('plan_past_due_since', '<', $cutoff)
            ->whereNotNull('owner_user_id')
            ->each(function (Tenant $tenant) use ($notificationService) {
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
                    'payload'    => ['reason' => 'dunning_21_days'],
                ]);

                $notificationService->dispatch(
                    'subscription.payment_failed_platform',
                    $tenant->id,
                    $tenant->owner_user_id,
                    ['reason' => 'downgraded_after_21_days_past_due'],
                );
            });
    }
}
