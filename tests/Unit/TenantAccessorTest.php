<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_on_trial_true_when_trialing_and_future_end(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);

        $this->assertTrue($tenant->is_on_trial);
    }

    public function test_is_on_trial_false_when_active(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'active',
            'trial_ends_at' => now()->addDays(10),
        ]);

        $this->assertFalse($tenant->is_on_trial);
    }

    public function test_is_on_trial_false_when_trial_ended(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'trialing',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($tenant->is_on_trial);
    }

    public function test_trial_days_remaining_returns_correct_count(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'trialing',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->assertEquals(7, $tenant->trial_days_remaining);
    }

    public function test_trial_days_remaining_zero_when_no_trial_ends_at(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'active',
            'trial_ends_at' => null,
        ]);

        $this->assertEquals(0, $tenant->trial_days_remaining);
    }

    public function test_trial_days_remaining_zero_when_past(): void
    {
        $tenant = Tenant::factory()->create([
            'status'        => 'free_tier',
            'trial_ends_at' => now()->subDays(3),
        ]);

        $this->assertEquals(0, $tenant->trial_days_remaining);
    }

    public function test_is_overdue_true_when_past_due_with_date(): void
    {
        $tenant = Tenant::factory()->create([
            'status'              => 'past_due',
            'plan_past_due_since' => now()->subDays(5),
        ]);

        $this->assertTrue($tenant->is_overdue);
    }

    public function test_is_overdue_false_when_active(): void
    {
        $tenant = Tenant::factory()->create([
            'status'              => 'active',
            'plan_past_due_since' => null,
        ]);

        $this->assertFalse($tenant->is_overdue);
    }
}
