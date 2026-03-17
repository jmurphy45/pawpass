<?php

namespace App\Notifications\Channels;

use App\Services\TwilioService;

class SmsChannel
{
    public function __construct(private TwilioService $twilio) {}

    public function send($notifiable, $notification): void
    {
        if (! $notifiable->phone) {
            return;
        }

        $this->twilio->send($notifiable->phone, $notification->toSms($notifiable));
    }
}
