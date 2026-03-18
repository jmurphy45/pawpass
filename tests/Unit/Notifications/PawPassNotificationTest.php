<?php

namespace Tests\Unit\Notifications;

use App\Notifications\PawPassNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class PawPassNotificationTest extends TestCase
{
    private PawPassNotification $notification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notification = new PawPassNotification(
            type: 'payment.confirmed',
            tenantId: 'tenant123',
            data: ['order_id' => 'order_abc'],
            channels: ['database', 'mail', 'sms'],
        );
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->notification);
    }

    public function test_queue_is_notifications(): void
    {
        // $queue is set via onQueue() in constructor (Queueable trait owns the property)
        $this->assertSame('notifications', $this->notification->queue);
    }

    public function test_via_returns_given_channels(): void
    {
        $this->assertSame(['database', 'mail', 'sms'], $this->notification->via(null));
    }

    public function test_to_array_includes_required_keys(): void
    {
        $array = $this->notification->toArray(null);

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('tenant_id', $array);
        $this->assertArrayHasKey('subject', $array);
        $this->assertArrayHasKey('body', $array);
        $this->assertArrayHasKey('data', $array);

        $this->assertSame('payment.confirmed', $array['type']);
        $this->assertSame('tenant123', $array['tenant_id']);
        $this->assertSame(['order_id' => 'order_abc'], $array['data']);
    }

    public function test_to_array_subject_matches_known_type(): void
    {
        $array = $this->notification->toArray(null);
        $this->assertSame('Payment Confirmed', $array['subject']);
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $mail = $this->notification->toMail(null);
        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_to_mail_has_correct_subject(): void
    {
        $mail = $this->notification->toMail(null);
        $this->assertSame('Payment Confirmed', $mail->subject);
    }

    public function test_to_sms_returns_string(): void
    {
        $sms = $this->notification->toSms(null);
        $this->assertIsString($sms);
        $this->assertNotEmpty($sms);
    }

    public function test_to_sms_matches_body_for_known_type(): void
    {
        $sms = $this->notification->toSms(null);
        $this->assertStringContainsString('confirmed', strtolower($sms));
    }

    public function test_default_type_falls_back_to_type_string(): void
    {
        $notif = new PawPassNotification(
            type: 'unknown.type',
            tenantId: 'tenant123',
        );

        $array = $notif->toArray(null);
        $this->assertSame('unknown.type', $array['subject']);
    }

    public function test_announcement_type_uses_subject_from_data(): void
    {
        $notif = new PawPassNotification(
            type: 'announcement',
            tenantId: 'tenant123',
            data: ['subject' => 'Big News', 'body' => 'Something happened.'],
        );

        $array = $notif->toArray(null);
        $this->assertSame('Big News', $array['subject']);
        $this->assertSame('Something happened.', $array['body']);
    }

    public function test_announcement_type_sms_returns_body(): void
    {
        $notif = new PawPassNotification(
            type: 'announcement',
            tenantId: 'tenant123',
            data: ['subject' => 'Big News', 'body' => 'Something happened.'],
        );

        $this->assertSame('Something happened.', $notif->toSms(null));
    }

    public function test_announcement_type_falls_back_to_defaults_when_data_missing(): void
    {
        $notif = new PawPassNotification(
            type: 'announcement',
            tenantId: 'tenant123',
            data: [],
        );

        $array = $notif->toArray(null);
        $this->assertSame('Announcement', $array['subject']);
        $this->assertSame('', $array['body']);
    }
}
