<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneOldNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Notification::allTenants()
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }
}
