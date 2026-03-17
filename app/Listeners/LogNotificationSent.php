<?php

namespace App\Listeners;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

class LogNotificationSent
{
    public function handleSent(NotificationSent $event): void
    {
        $notifId = $event->channel === 'database' ? ($event->response->id ?? null) : null;

        $channel = match ($event->channel) {
            'database' => 'in_app',
            'mail' => 'email',
            default => $event->channel,
        };

        DB::table('notification_logs')->insert([
            'notification_id' => $notifId,
            'tenant_id' => $event->notification->tenantId,
            'user_id' => $event->notifiable->id,
            'type' => $event->notification->type,
            'channel' => $channel,
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $channel = match ($event->channel) {
            'database' => 'in_app',
            'mail' => 'email',
            default => $event->channel,
        };

        $exception = $event->data['exception'] ?? null;

        DB::table('notification_logs')->insert([
            'notification_id' => null,
            'tenant_id' => $event->notification->tenantId,
            'user_id' => $event->notifiable->id,
            'type' => $event->notification->type,
            'channel' => $channel,
            'status' => 'failed',
            'error' => $exception?->getMessage(),
            'created_at' => now(),
        ]);
    }
}
