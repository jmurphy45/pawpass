<?php

namespace Tests\Feature\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PawPassNotification;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PawPassNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'phone' => '+15005550007',
        ]);
    }

    public function test_database_channel_stores_notification(): void
    {
        $notification = new PawPassNotification('payment.confirmed', $this->tenant->id, [], ['database']);

        $this->user->notify($notification);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_mail_channel_notification_is_sent(): void
    {
        // MailChannel uses mailer->send(view, data, callback) for MailMessage responses,
        // which MailFake does not track. Assert via Notification::fake() instead.
        Notification::fake();

        $notification = new PawPassNotification('payment.confirmed', $this->tenant->id, [], ['mail']);

        $this->user->notify($notification);

        Notification::assertSentTo($this->user, PawPassNotification::class,
            fn (PawPassNotification $n) => $n->type === 'payment.confirmed',
        );
    }

    public function test_sms_channel_calls_twilio(): void
    {
        $twilio = $this->mock(TwilioService::class);
        $twilio->shouldReceive('send')
            ->once()
            ->with($this->user->phone, \Mockery::type('string'));

        $notification = new PawPassNotification('payment.confirmed', $this->tenant->id, [], ['sms']);

        $this->user->notify($notification);
    }

    public function test_notification_log_written_via_event_listener(): void
    {
        $notification = new PawPassNotification('payment.confirmed', $this->tenant->id, [], ['database']);

        $this->user->notify($notification);

        $log = DB::table('notification_logs')
            ->where('user_id', $this->user->id)
            ->where('type', 'payment.confirmed')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
    }

    public function test_sms_skipped_when_user_has_no_phone(): void
    {
        $twilio = $this->mock(TwilioService::class);
        $twilio->shouldNotReceive('send');

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => null,
        ]);

        $notification = new PawPassNotification('payment.confirmed', $this->tenant->id, [], ['sms']);
        $user->notify($notification);
    }
}
