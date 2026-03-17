<?php

namespace App\Jobs;

use App\Models\RawWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneRawWebhooks implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        RawWebhook::where('received_at', '<', now()->subDays(7))->delete();
    }
}
