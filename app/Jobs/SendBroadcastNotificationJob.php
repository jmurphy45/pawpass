<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\PawPassNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendBroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $tenantId,
        private string $subject,
        private string $body,
        private array $requestedChannels,
    ) {
        $this->onQueue('notifications');
    }

    public int $tries = 1;

    public function handle(): void
    {
        app()->instance('current.tenant.id', $this->tenantId);

        User::where('tenant_id', $this->tenantId)
            ->where('role', 'customer')
            ->whereNull('deleted_at')
            ->chunkById(100, function ($chunk) {
                $disabledPrefs = DB::table('user_notification_preferences')
                    ->where('type', 'announcement')
                    ->whereIn('user_id', $chunk->pluck('id'))
                    ->where('is_enabled', false)
                    ->get(['user_id', 'channel'])
                    ->groupBy('user_id')
                    ->map(fn ($rows) => $rows->pluck('channel')->all());

                foreach ($chunk as $user) {
                    $disabled = $disabledPrefs[$user->id] ?? [];
                    $channels = [];

                    if (in_array('in_app', $this->requestedChannels)) {
                        $channels[] = 'database';
                    }

                    if (in_array('email', $this->requestedChannels) && ! in_array('email', $disabled)) {
                        $channels[] = 'mail';
                    }

                    if (in_array('sms', $this->requestedChannels)
                        && $user->phone
                        && ! in_array('sms', $disabled)
                    ) {
                        $channels[] = 'sms';
                    }

                    if (empty($channels)) {
                        continue;
                    }

                    $user->notify(new PawPassNotification(
                        'announcement',
                        $this->tenantId,
                        ['subject' => $this->subject, 'body' => $this->body],
                        $channels,
                    ));
                }
            });
    }
}
