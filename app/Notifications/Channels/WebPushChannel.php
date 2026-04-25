<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Notifications\PawPassNotification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushChannel
{
    public function __construct(private WebPush $webPush) {}

    public function send($notifiable, $notification): void
    {
        if (! $notification instanceof PawPassNotification) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->id)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush()->toArray());

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh,
                    'auth' => $sub->auth_token,
                ],
            ]);

            $report = $this->webPush->sendOneNotification($subscription, $payload);

            if (! $report->isSuccess() && $report->getResponse()?->getStatusCode() === 410) {
                $sub->delete();
            }
        }
    }
}
