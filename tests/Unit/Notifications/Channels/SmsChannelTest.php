<?php

namespace Tests\Unit\Notifications\Channels;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\PawPassNotification;
use App\Services\SmsUsageService;
use App\Services\TwilioService;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    public function test_send_calls_twilio_with_phone_and_message(): void
    {
        $twilio = $this->mock(TwilioService::class);
        $twilio->shouldReceive('send')
            ->once()
            ->with('+15005550007', \Mockery::type('string'))
            ->andReturn(1);

        $smsUsage = $this->mock(SmsUsageService::class);
        $smsUsage->shouldReceive('track')->once();

        $channel = new SmsChannel($twilio, $smsUsage);

        $notifiable = new class
        {
            public string $phone = '+15005550007';
        };

        $notification = new PawPassNotification('payment.confirmed', 'tenant123');

        $channel->send($notifiable, $notification);
    }

    public function test_send_skips_when_phone_is_null(): void
    {
        $twilio = $this->mock(TwilioService::class);
        $twilio->shouldNotReceive('send');

        $smsUsage = $this->mock(SmsUsageService::class);
        $smsUsage->shouldNotReceive('track');

        $channel = new SmsChannel($twilio, $smsUsage);

        $notifiable = new class
        {
            public ?string $phone = null;
        };

        $notification = new PawPassNotification('payment.confirmed', 'tenant123');

        $channel->send($notifiable, $notification);
    }

    public function test_send_skips_when_phone_is_empty_string(): void
    {
        $twilio = $this->mock(TwilioService::class);
        $twilio->shouldNotReceive('send');

        $smsUsage = $this->mock(SmsUsageService::class);
        $smsUsage->shouldNotReceive('track');

        $channel = new SmsChannel($twilio, $smsUsage);

        $notifiable = new class
        {
            public string $phone = '';
        };

        $notification = new PawPassNotification('payment.confirmed', 'tenant123');

        $channel->send($notifiable, $notification);
    }
}
