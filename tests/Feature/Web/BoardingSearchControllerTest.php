<?php

namespace Tests\Feature\Web;

use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardingSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_boarding_index_returns_inertia_page(): void
    {
        $this->get('/find-boarding')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FindBoarding')
                ->has('headTitle')
                ->has('headDescription')
            );
    }

    public function test_find_boarding_only_shows_kennel_and_hybrid_tenants(): void
    {
        Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'kennel',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);
        Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'hybrid',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);
        // Daycare only — should be excluded
        Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'daycare',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);

        $this->get('/find-boarding?city=Memphis&state=TN')
            ->assertInertia(fn ($page) => $page
                ->has('results', 2)
            );
    }

    public function test_find_boarding_city_route_filters_by_location(): void
    {
        Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'kennel',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);
        Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'kennel',
            'business_city' => 'Nashville',
            'business_state' => 'TN',
        ]);

        $this->get('/find-boarding/tn/memphis')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FindBoarding')
                ->has('results', 1)
                ->where('results.0.city', 'Memphis')
            );
    }

    public function test_find_boarding_city_sets_seo_head_title(): void
    {
        $this->get('/find-boarding/tn/memphis')
            ->assertInertia(fn ($page) => $page
                ->where('headTitle', fn ($v) => str_contains($v, 'Memphis'))
            );
    }

    public function test_find_boarding_with_dates_marks_availability(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'kennel',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);

        KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $checkin = now()->addDay()->format('Y-m-d');
        $checkout = now()->addDays(3)->format('Y-m-d');

        $this->get("/find-boarding/tn/memphis?checkin={$checkin}&checkout={$checkout}")
            ->assertInertia(fn ($page) => $page
                ->has('results', 1)
                ->where('results.0.available_units', 1)
            );
    }

    public function test_find_boarding_with_dates_excludes_fully_booked_tenants(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_type' => 'kennel',
            'business_city' => 'Memphis',
            'business_state' => 'TN',
        ]);

        $unit = KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $checkin = now()->addDay()->format('Y-m-d');
        $checkout = now()->addDays(3)->format('Y-m-d');

        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $tenant->id,
            'starts_at' => $checkin,
            'ends_at' => $checkout,
        ]);

        $this->get("/find-boarding/tn/memphis?checkin={$checkin}&checkout={$checkout}")
            ->assertInertia(fn ($page) => $page
                ->where('results.0.available_units', 0)
            );
    }
}
