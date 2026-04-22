<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendTrialExpirationWarnings;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SendTrialExpirationWarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_warning_for_tenants_expiring_in_7_days(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7)->midDay(),
            'owner_user_id' => \App\Models\User::factory()->create(['tenant_id' => null, 'role' => 'business_owner'])->id,
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with('trial.expiring_soon', \Mockery::any(), \Mockery::any(), \Mockery::subset(['days_remaining' => 7]));
        });

        (new SendTrialExpirationWarnings)->handle($notifications);
    }

    public function test_does_not_send_warning_for_non_trialing_tenants(): void
    {
        Tenant::factory()->create([
            'status' => 'active',
            'trial_ends_at' => now()->addDays(7)->midDay(),
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        (new SendTrialExpirationWarnings)->handle($notifications);
    }

    public function test_does_not_send_warning_for_tenant_not_expiring_soon(): void
    {
        Tenant::factory()->create([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(15),
        ]);

        $notifications = $this->mock(NotificationService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('dispatch');
        });

        (new SendTrialExpirationWarnings)->handle($notifications);
    }
}
