<?php

namespace App\Jobs;

use App\Models\Dog;
use App\Models\NotificationPending;
use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DispatchGroupedAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $pendingId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $pending = NotificationPending::allTenants()
                ->whereNull('dispatched_at')
                ->where('id', $this->pendingId)
                ->lockForUpdate()
                ->first();

            if (! $pending) {
                return;
            }

            $pending->update(['dispatched_at' => now()]);

            $dogIds = $pending->dog_ids ?? [];
            $dogs = Dog::allTenants()->whereIn('id', $dogIds)->get();

            $type = $dogs->contains(fn (Dog $d) => $d->credit_balance <= 0) ? 'credits.empty' : 'credits.low';

            Dog::allTenants()->whereIn('id', $dogIds)->update(['credits_alert_sent_at' => now()]);

            $data = ['dog_ids' => $dogIds, 'dog_count' => count($dogIds)];

            $user = User::allTenants()->find($pending->user_id);

            if (! $user) {
                return;
            }

            Notification::send($user, new PawPassNotification($type, $pending->tenant_id, $data, ['database', 'mail', 'sms']));
        });
    }
}
