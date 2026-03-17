<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExpireTrials;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ExpireTrialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_trialing_tenants_whose_trial_has_ended(): void
    {
        $expired = Tenant::factory()->create([
            'status'         => 'trialing',
            'trial_ends_at'  => now()->subHour(),
            'plan'           => 'starter',
            'owner_user_id'  => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notExpired = Tenant::factory()->create([
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDay(),
            'plan'          => 'starter',
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with('trial.expired', \Mockery::any(), \Mockery::any(), []);
        });

        (new ExpireTrials)->handle($notifications);

        $expired->refresh();
        $this->assertEquals('free_tier', $expired->status);
        $this->assertEquals('free', $expired->plan);

        $notExpired->refresh();
        $this->assertEquals('trialing', $notExpired->status);
    }

    public function test_logs_platform_subscription_event(): void
    {
        Tenant::factory()->create([
            'status'        => 'trialing',
            'trial_ends_at' => now()->subHour(),
            'plan'          => 'starter',
            'owner_user_id' => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')->once();
        });

        (new ExpireTrials)->handle($notifications);

        $this->assertDatabaseHas('platform_subscription_events', ['event_type' => 'trial_expired']);
    }
}
