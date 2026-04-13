<?php

namespace App\Jobs;

use App\Models\NotificationPending;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneDispatchedPending implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        NotificationPending::allTenants()
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', now()->subHours(24))
            ->delete();
    }
}
