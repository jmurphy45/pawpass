<?php

namespace App\Jobs;

use App\Models\PlatformSubscriptionEvent;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTrialExpirationWarnings implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(NotificationService $notificationService): void
    {
        $warningDays = [7, 3, 1];

        foreach ($warningDays as $days) {
            $start = now()->addDays($days)->startOfDay();
            $end = now()->addDays($days)->endOfDay();

            Tenant::where('status', 'trialing')
                ->whereBetween('trial_ends_at', [$start, $end])
                ->whereNotNull('owner_user_id')
                ->each(function (Tenant $tenant) use ($days, $notificationService) {
                    $notificationService->dispatch(
                        'trial.expiring_soon',
                        $tenant->id,
                        $tenant->owner_user_id,
                        ['days_remaining' => $days],
                    );
                });
        }
    }
}
