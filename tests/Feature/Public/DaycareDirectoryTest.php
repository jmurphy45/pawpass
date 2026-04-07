<?php

namespace Tests\Feature\Public;

use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaycareDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function listedTenant(array $attrs = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'status' => 'active',
            'is_publicly_listed' => true,
            'business_city' => 'Austin',
            'business_state' => 'TX',
            'business_zip' => '78701',
            'business_type' => 'daycare',
        ], $attrs));
    }

    // ── Visibility rules ───────────────────────────────────────────────────────

    public function test_only_publicly_listed_tenants_are_returned(): void
    {
        $listed = $this->listedTenant(['name' => 'Listed Daycare']);
        Tenant::factory()->create(['status' => 'active', 'is_publicly_listed' => false, 'business_city' => 'Austin', 'business_state' => 'TX']);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($listed->slug, $response->json('data.0.slug'));
    }

    public function test_suspended_tenants_are_excluded(): void
    {
        $this->listedTenant(['status' => 'suspended']);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_active_trialing_free_tier_past_due_tenants_are_included(): void
    {
        $this->listedTenant(['status' => 'active', 'name' => 'A']);
        $this->listedTenant(['status' => 'trialing', 'name' => 'B']);
        $this->listedTenant(['status' => 'free_tier', 'name' => 'C']);
        $this->listedTenant(['status' => 'past_due', 'name' => 'D']);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $this->assertCount(4, $response->json('data'));
    }

    // ── Search filters ─────────────────────────────────────────────────────────

    public function test_search_by_city_and_state_is_case_insensitive(): void
    {
        $this->listedTenant(['business_city' => 'Austin', 'business_state' => 'TX']);

        $response = $this->getJson('/api/public/v1/daycares?city=austin&state=tx');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_search_by_zip(): void
    {
        $this->listedTenant(['business_zip' => '78701', 'name' => 'Match']);
        $this->listedTenant(['business_zip' => '90210', 'name' => 'No Match']);

        $response = $this->getJson('/api/public/v1/daycares?zip=78701');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Match', $response->json('data.0.name'));
    }

    public function test_returns_empty_when_no_params(): void
    {
        $this->listedTenant();

        $response = $this->getJson('/api/public/v1/daycares');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ── Response shape ─────────────────────────────────────────────────────────

    public function test_response_includes_required_fields(): void
    {
        $tenant = $this->listedTenant([
            'name' => 'Happy Paws',
            'business_type' => 'hybrid',
            'business_phone' => '512-555-0100',
            'business_description' => 'A great place for dogs.',
        ]);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertEquals('Happy Paws', $item['name']);
        $this->assertEquals($tenant->slug, $item['slug']);
        $this->assertEquals('Austin', $item['city']);
        $this->assertEquals('TX', $item['state']);
        $this->assertEquals('hybrid', $item['business_type']);
        $this->assertEquals('512-555-0100', $item['phone']);
        $this->assertEquals('A great place for dogs.', $item['description']);
        $this->assertArrayHasKey('logo_url', $item);
        $this->assertArrayHasKey('has_boarding', $item);
    }

    public function test_has_boarding_is_true_for_kennel_and_hybrid(): void
    {
        $this->listedTenant(['business_type' => 'daycare', 'name' => 'D']);
        $this->listedTenant(['business_type' => 'kennel', 'name' => 'K']);
        $this->listedTenant(['business_type' => 'hybrid', 'name' => 'H']);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('name');
        $this->assertFalse($data['D']['has_boarding']);
        $this->assertTrue($data['K']['has_boarding']);
        $this->assertTrue($data['H']['has_boarding']);
    }

    // ── Boarding availability filter ───────────────────────────────────────────

    public function test_date_range_filter_includes_tenants_with_available_unit(): void
    {
        $tenant = $this->listedTenant(['business_type' => 'kennel']);
        KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX&date_from=2026-05-01&date_to=2026-05-03');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.boarding_available'));
    }

    public function test_date_range_filter_excludes_tenants_with_all_units_booked(): void
    {
        $tenant = $this->listedTenant(['business_type' => 'kennel']);
        $unit = KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        // Book the unit for the requested range
        Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'kennel_unit_id' => $unit->id,
            'status' => 'confirmed',
            'starts_at' => '2026-05-01 12:00:00',
            'ends_at' => '2026-05-03 12:00:00',
        ]);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX&date_from=2026-05-01&date_to=2026-05-03');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.boarding_available'));
    }

    public function test_date_range_filter_ignores_cancelled_reservations(): void
    {
        $tenant = $this->listedTenant(['business_type' => 'kennel']);
        $unit = KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        Reservation::factory()->create([
            'tenant_id' => $tenant->id,
            'kennel_unit_id' => $unit->id,
            'status' => 'cancelled',
            'starts_at' => '2026-05-01 12:00:00',
            'ends_at' => '2026-05-03 12:00:00',
        ]);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX&date_from=2026-05-01&date_to=2026-05-03');

        $response->assertOk();
        $this->assertTrue($response->json('data.0.boarding_available'));
    }

    public function test_boarding_available_key_absent_when_no_dates_given(): void
    {
        $tenant = $this->listedTenant(['business_type' => 'kennel']);
        KennelUnit::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $response = $this->getJson('/api/public/v1/daycares?city=Austin&state=TX');

        $response->assertOk();
        $this->assertArrayNotHasKey('boarding_available', $response->json('data.0'));
    }
}
