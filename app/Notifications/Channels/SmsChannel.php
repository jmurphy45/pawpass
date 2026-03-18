<?php

namespace App\Notifications\Channels;

use App\Services\SmsUsageService;
use App\Services\TwilioService;

class SmsChannel
{
    public function __construct(
        private TwilioService $twilio,
        private SmsUsageService $smsUsage,
    ) {}

    public function send($notifiable, $notification): void
    {
        if (! $notifiable->phone) {
            return;
        }

        $segments = $this->twilio->send($notifiable->phone, $notification->toSms($notifiable));

        $tenantId = $notification->tenantId ?? null;
        if ($tenantId) {
            $this->smsUsage->track($tenantId, $segments);
        }
    }
}
