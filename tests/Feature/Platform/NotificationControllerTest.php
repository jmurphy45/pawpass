<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class NotificationControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private User $admin;

    private Tenant $tenant;

    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        URL::forceRootUrl('http://platform.pawpass.com');

        $this->admin = User::factory()->platformAdmin()->create();

        $this->tenant = Tenant::factory()->create();
        $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->admin)];
    }

    private function insertLog(string $channel, string $status, string $type = 'payment.confirmed'): int
    {
        return DB::table('notification_logs')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantUser->id,
            'type' => $type,
            'channel' => $channel,
            'status' => $status,
            'notification_id' => null,
            'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Delivery stats
    // -------------------------------------------------------------------------

    public function test_delivery_returns_stats_grouped_by_channel_and_status(): void
    {
        $this->insertLog('email', 'sent');
        $this->insertLog('email', 'sent');
        $this->insertLog('email', 'failed');
        $this->insertLog('sms', 'sent');

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/notifications/delivery');

        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $emailSent = $data->firstWhere(fn ($r) => $r['channel'] === 'email' && $r['status'] === 'sent');
        $emailFailed = $data->firstWhere(fn ($r) => $r['channel'] === 'email' && $r['status'] === 'failed');
        $smsSent = $data->firstWhere(fn ($r) => $r['channel'] === 'sms' && $r['status'] === 'sent');

        $this->assertSame(2, (int) $emailSent['count']);
        $this->assertSame(1, (int) $emailFailed['count']);
        $this->assertSame(1, (int) $smsSent['count']);
    }

    public function test_delivery_returns_empty_when_no_logs(): void
    {
        $response = $this->withHeaders($this->headers())
            ->getJson('/api/platform/v1/notifications/delivery');

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    // -------------------------------------------------------------------------
    // Retry
    // -------------------------------------------------------------------------

    public function test_retry_dispatches_notification_for_failed_log(): void
    {
        $mock = $this->mock(NotificationService::class);
        $mock->shouldReceive('dispatch')
            ->once()
            ->with('payment.confirmed', $this->tenant->id, $this->tenantUser->id, []);

        $logId = $this->insertLog('email', 'failed', 'payment.confirmed');

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/notifications/failures/{$logId}/retry")
            ->assertStatus(200)
            ->assertJsonPath('data.message', 'Retry dispatched.');
    }

    public function test_retry_returns_404_for_unknown_log_id(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/platform/v1/notifications/failures/9999999/retry')
            ->assertStatus(404);
    }

    public function test_retry_returns_422_if_status_not_failed(): void
    {
        $logId = $this->insertLog('email', 'sent');

        $this->withHeaders($this->headers())
            ->postJson("/api/platform/v1/notifications/failures/{$logId}/retry")
            ->assertStatus(422);
    }
}
