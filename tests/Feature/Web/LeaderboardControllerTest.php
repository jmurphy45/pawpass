<?php

namespace Tests\Feature\Web;

use App\Models\Attendance;
use App\Models\Dog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_index_returns_inertia_page(): void
    {
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        $this->get('/leaderboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leaderboard')
                ->has('tenants')
                ->has('headTitle')
                ->has('headDescription')
            );
    }

    public function test_leaderboard_only_includes_publicly_listed_active_tenants(): void
    {
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        // Suspended — should be excluded
        Tenant::factory()->create([
            'status'            => 'suspended',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        // Not publicly listed — should be excluded
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => false,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        $this->get('/leaderboard')
            ->assertInertia(fn ($page) => $page
                ->component('Leaderboard')
                ->has('tenants', 1)
            );
    }

    public function test_leaderboard_city_filters_by_state_and_city(): void
    {
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Nashville',
            'business_state'    => 'TN',
        ]);

        $this->get('/leaderboard/tn/memphis')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leaderboard')
                ->has('tenants', 1)
                ->where('tenants.0.city', 'Memphis')
            );
    }

    public function test_leaderboard_city_route_sets_city_specific_head_title(): void
    {
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);

        $this->get('/leaderboard/tn/memphis')
            ->assertInertia(fn ($page) => $page
                ->where('headTitle', fn ($v) => str_contains($v, 'Memphis'))
            );
    }

    public function test_leaderboard_city_slug_with_hyphens_matches_spaced_city(): void
    {
        Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'New Orleans',
            'business_state'    => 'LA',
        ]);

        $this->get('/leaderboard/la/new-orleans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tenants', 1)
            );
    }

    public function test_leaderboard_includes_today_stats(): void
    {
        $tenant = Tenant::factory()->create([
            'status'            => 'active',
            'is_publicly_listed' => true,
            'business_city'     => 'Memphis',
            'business_state'    => 'TN',
        ]);
        $dog = Dog::factory()->create(['tenant_id' => $tenant->id]);

        Attendance::factory()->create([
            'tenant_id'      => $tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_at'  => now(),
            'checked_out_at' => null,
        ]);

        $this->get('/leaderboard')
            ->assertInertia(fn ($page) => $page
                ->where('tenants.0.today_total', 1)
                ->where('tenants.0.currently_in', 1)
            );
    }
}
