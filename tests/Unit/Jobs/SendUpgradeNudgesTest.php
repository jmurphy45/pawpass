<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendUpgradeNudges;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SendUpgradeNudgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_nudge_for_free_tier_tenants_7_days_after_trial(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'free_tier',
            'trial_ends_at' => now()->subDays(7)->midDay(),
            'owner_user_id' => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with('trial.upgrade_nudge', \Mockery::any(), \Mockery::any(), ['days_since_trial_ended' => 7]);
        });

        (new SendUpgradeNudges)->handle($notifications);
    }

    public function test_does_not_nudge_active_tenants(): void
    {
        Tenant::factory()->create([
            'status'        => 'active',
            'trial_ends_at' => now()->subDays(7)->midDay(),
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        (new SendUpgradeNudges)->handle($notifications);
    }
}
