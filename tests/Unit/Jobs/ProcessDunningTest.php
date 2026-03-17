<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessDunning;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessDunningTest extends TestCase
{
    use RefreshDatabase;

    public function test_downgrades_past_due_tenants_overdue_21_days(): void
    {
        $overdue = Tenant::factory()->create([
            'status'              => 'past_due',
            'plan'                => 'pro',
            'plan_past_due_since' => now()->subDays(22),
            'owner_user_id'       => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notYetOverdue = Tenant::factory()->create([
            'status'              => 'past_due',
            'plan'                => 'starter',
            'plan_past_due_since' => now()->subDays(10),
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with('subscription.payment_failed_platform', \Mockery::any(), \Mockery::any(), \Mockery::any());
        });

        (new ProcessDunning)->handle($notifications);

        $overdue->refresh();
        $this->assertEquals('free_tier', $overdue->status);
        $this->assertEquals('free', $overdue->plan);
        $this->assertNull($overdue->plan_past_due_since);

        $notYetOverdue->refresh();
        $this->assertEquals('past_due', $notYetOverdue->status);
    }

    public function test_logs_platform_subscription_event(): void
    {
        Tenant::factory()->create([
            'status'              => 'past_due',
            'plan'                => 'starter',
            'plan_past_due_since' => now()->subDays(25),
            'owner_user_id'       => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')->once();
        });

        (new ProcessDunning)->handle($notifications);

        $this->assertDatabaseHas('platform_subscription_events', [
            'event_type' => 'downgraded',
        ]);
    }
}
