<?php

namespace Tests\Unit\Services;

use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Tenant;
use App\Services\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaderboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeaderboardService;
    }

    public function test_currently_in_counts_only_todays_unchecked_out(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $dog1 = Dog::factory()->create(['tenant_id' => $tenant->id]);
        $dog2 = Dog::factory()->create(['tenant_id' => $tenant->id]);
        $dog3 = Dog::factory()->create(['tenant_id' => $tenant->id]);

        // Today, still checked in
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog1->id,
            'checked_in_at'  => now(),
            'checked_out_at' => null,
        ]);

        // Today, already checked out — should NOT count toward "currently in"
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog2->id,
            'checked_in_at'  => now()->subHours(8),
            'checked_out_at' => now()->subHour(),
        ]);

        // Yesterday, still "open" — should NOT count (different date)
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog3->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $stats = $this->service->leaderboardStats(collect([$tenant]));

        $this->assertCount(1, $stats);
        $this->assertEquals(1, $stats[0]['currently_in']);
    }

    public function test_today_total_includes_checked_out_dogs(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $dog1 = Dog::factory()->create(['tenant_id' => $tenant->id]);
        $dog2 = Dog::factory()->create(['tenant_id' => $tenant->id]);
        $dog3 = Dog::factory()->create(['tenant_id' => $tenant->id]);

        // Today, still in
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog1->id,
            'checked_in_at'  => now(),
            'checked_out_at' => null,
        ]);

        // Today, checked out
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog2->id,
            'checked_in_at'  => now()->subHours(8),
            'checked_out_at' => now()->subHour(),
        ]);

        // Yesterday — should NOT count
        Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id'    => $dog3->id,
            'checked_in_at'  => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $stats = $this->service->leaderboardStats(collect([$tenant]));

        $this->assertEquals(2, $stats[0]['today_total']);
    }

    public function test_leaderboard_stats_bulk_loads_multiple_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $dogA = Dog::factory()->create(['tenant_id' => $tenantA->id]);
        $dogB1 = Dog::factory()->create(['tenant_id' => $tenantB->id]);
        $dogB2 = Dog::factory()->create(['tenant_id' => $tenantB->id]);

        Attendance::factory()->create([
            'tenant_id' => $tenantA->id, 'dog_id' => $dogA->id,
            'checked_in_at' => now(), 'checked_out_at' => null,
        ]);
        Attendance::factory()->create([
            'tenant_id' => $tenantB->id, 'dog_id' => $dogB1->id,
            'checked_in_at' => now(), 'checked_out_at' => null,
        ]);
        Attendance::factory()->create([
            'tenant_id' => $tenantB->id, 'dog_id' => $dogB2->id,
            'checked_in_at' => now(), 'checked_out_at' => null,
        ]);

        $stats = $this->service->leaderboardStats(collect([$tenantA, $tenantB]));

        $this->assertCount(2, $stats);
        $byTenant = $stats->keyBy('id');
        $this->assertEquals(1, $byTenant[$tenantA->id]['today_total']);
        $this->assertEquals(2, $byTenant[$tenantB->id]['today_total']);
    }

    public function test_leaderboard_stats_sorted_by_today_total_descending(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active', 'name' => 'Low Activity']);
        $tenantB = Tenant::factory()->create(['status' => 'active', 'name' => 'High Activity']);

        $dogA = Dog::factory()->create(['tenant_id' => $tenantA->id]);
        $dogB1 = Dog::factory()->create(['tenant_id' => $tenantB->id]);
        $dogB2 = Dog::factory()->create(['tenant_id' => $tenantB->id]);
        $dogB3 = Dog::factory()->create(['tenant_id' => $tenantB->id]);

        Attendance::factory()->create([
            'tenant_id' => $tenantA->id, 'dog_id' => $dogA->id,
            'checked_in_at' => now(), 'checked_out_at' => null,
        ]);
        foreach ([$dogB1, $dogB2, $dogB3] as $dog) {
            Attendance::factory()->create([
                'tenant_id' => $tenantB->id, 'dog_id' => $dog->id,
                'checked_in_at' => now(), 'checked_out_at' => null,
            ]);
        }

        $stats = $this->service->leaderboardStats(collect([$tenantA, $tenantB]));

        $this->assertEquals('High Activity', $stats[0]['name']);
        $this->assertEquals('Low Activity', $stats[1]['name']);
    }

    public function test_tenant_with_no_checkins_today_returns_zero_counts(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $stats = $this->service->leaderboardStats(collect([$tenant]));

        $this->assertCount(1, $stats);
        $this->assertEquals(0, $stats[0]['today_total']);
        $this->assertEquals(0, $stats[0]['currently_in']);
    }
}
