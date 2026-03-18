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

    public function handle(): void
    {
        app()->instance('current.tenant.id', $this->tenantId);

        $users = User::where('tenant_id', $this->tenantId)
            ->where('role', 'customer')
            ->whereNull('deleted_at')
            ->get();

        foreach ($users as $user) {
            $channels = [];

            if (in_array('in_app', $this->requestedChannels)) {
                $channels[] = 'database';
            }

            if (in_array('email', $this->requestedChannels)
                && $this->userAllowsChannel($user, 'email', 'announcement')
            ) {
                $channels[] = 'mail';
            }

            if (in_array('sms', $this->requestedChannels)
                && $user->phone
                && $this->userAllowsChannel($user, 'sms', 'announcement')
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
    }

    private function userAllowsChannel(User $user, string $channel, string $type): bool
    {
        $disabled = DB::table('user_notification_preferences')
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->where('is_enabled', false)
            ->exists();

        return ! $disabled;
    }
}
