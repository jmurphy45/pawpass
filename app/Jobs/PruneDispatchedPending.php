<?php

namespace App\Jobs;

use App\Models\NotificationPending;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneDispatchedPending implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        NotificationPending::allTenants()
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', now()->subHours(24))
            ->delete();
    }
}
