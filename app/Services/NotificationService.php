<?php

namespace App\Services;

use App\Jobs\DispatchGroupedAlertJob;
use App\Models\NotificationPending;
use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    private const CRITICAL = [
        'payment.confirmed',
        'payment.refunded',
        'subscription.payment_failed',
        'subscription.cancelled',
        'credits.empty',
        'auto_replenish.succeeded',
        'auto_replenish.failed',
        'auth.verify_email',
        'auth.registration_confirmed',
        'auth.password_reset',
        'staff.invite',
    ];

    public function dispatch(string $type, string $tenantId, string $userId, array $data = []): void
    {
        $isCritical = in_array($type, self::CRITICAL, true);

        if (! $isCritical) {
            $disabled = DB::table('tenant_notification_settings')
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('is_enabled', false)
                ->exists();

            if ($disabled) {
                return;
            }
        }

        $channels = $this->resolveChannels($type, $tenantId, $userId);

        if (empty($channels)) {
            return;
        }

        $user = User::allTenants()->find($userId);

        if (! $user) {
            return;
        }

        Notification::send($user, new PawPassNotification($type, $tenantId, $data, $channels));
    }

    public function enqueueGrouped(string $type, string $tenantId, string $userId, string $dogId): void
    {
        DB::transaction(function () use ($type, $tenantId, $userId, $dogId) {
            $pending = NotificationPending::allTenants()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->whereNull('dispatched_at')
                ->lockForUpdate()
                ->first();

            if (! $pending) {
                $pending = NotificationPending::create([
                    'tenant_id' => $tenantId,
                    'user_id'   => $userId,
                    'type'      => $type,
                    'dog_ids'   => [$dogId],
                ]);

                DispatchGroupedAlertJob::dispatch($pending->id)
                    ->onQueue('notifications')
                    ->delay(60);
            } else {
                $pending->dog_ids = array_values(array_unique(array_merge($pending->dog_ids ?? [], [$dogId])));
                $pending->save();
            }
        });
    }

    private function resolveChannels(string $type, string $tenantId, string $userId): array
    {
        $channels = ['database']; // in_app is always on

        foreach (['email' => 'mail', 'sms' => 'sms'] as $prefChannel => $laravelChannel) {
            $disabled = DB::table('user_notification_preferences')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('channel', $prefChannel)
                ->where('is_enabled', false)
                ->exists();

            if (! $disabled) {
                $channels[] = $laravelChannel;
            }
        }

        return $channels;
    }
}
