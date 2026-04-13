<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendUpgradeNudges implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(NotificationService $notificationService): void
    {
        $nudgeDays = [7, 30];

        foreach ($nudgeDays as $daysAgo) {
            $start = now()->subDays($daysAgo)->startOfDay();
            $end = now()->subDays($daysAgo)->endOfDay();

            Tenant::where('status', 'free_tier')
                ->whereBetween('trial_ends_at', [$start, $end])
                ->whereNotNull('owner_user_id')
                ->each(function (Tenant $tenant) use ($daysAgo, $notificationService) {
                    $notificationService->dispatch(
                        'trial.upgrade_nudge',
                        $tenant->id,
                        $tenant->owner_user_id,
                        ['days_since_trial_ended' => $daysAgo],
                    );
                });
        }
    }
}
