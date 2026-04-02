<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class KennelUnitCheckAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'          => 'checkavailability',
            'status'        => 'active',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://checkavailability.pawpass.com');
    }

    public function test_returns_403_for_daycare_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'daycareonly',
            'status'        => 'active',
            'business_type' => 'daycare',
        ]);
        URL::forceRootUrl('http://daycareonly.pawpass.com');

        $this->getJson('/api/portal/v1/kennel-units/check-availability?starts_at='.now()->addDay()->toDateString().'&ends_at='.now()->addDays(3)->toDateString())
            ->assertStatus(403);
    }

    public function test_returns_422_when_ends_at_missing(): void
    {
        $this->getJson('/api/portal/v1/kennel-units/check-availability?starts_at='.now()->addDay()->toDateString())
            ->assertStatus(422);
    }

    public function test_returns_422_when_ends_at_before_starts_at(): void
    {
        $this->getJson('/api/portal/v1/kennel-units/check-availability?starts_at='.now()->addDays(5)->toDateString().'&ends_at='.now()->addDays(2)->toDateString())
            ->assertStatus(422);
    }

    public function test_returns_available_units_excluding_conflicting_reservations(): void
    {
        $available = KennelUnit::factory()->count(2)->create([
            'tenant_id'          => $this->tenant->id,
            'is_active'          => true,
            'nightly_rate_cents' => 5000,
        ]);
        $booked = KennelUnit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $customer->id,
            'role'        => 'customer',
        ]);

        Reservation::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'kennel_unit_id' => $booked->id,
            'customer_id'    => $customer->id,
            'starts_at'      => now()->addDay(),
            'ends_at'        => now()->addDays(5),
            'created_by'     => $user->id,
        ]);

        $response = $this->getJson(
            '/api/portal/v1/kennel-units/check-availability?starts_at='.now()->addDays(2)->toDateString().'&ends_at='.now()->addDays(4)->toDateString()
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($booked->id, $ids);
        $this->assertArrayHasKey('id', $response->json('data.0'));
        $this->assertArrayHasKey('name', $response->json('data.0'));
        $this->assertArrayHasKey('type', $response->json('data.0'));
        $this->assertArrayHasKey('description', $response->json('data.0'));
        $this->assertArrayHasKey('nightly_rate_cents', $response->json('data.0'));
    }

    public function test_returns_available_units_for_hybrid_tenant(): void
    {
        $hybridTenant = Tenant::factory()->create([
            'slug'          => 'hybridpaws',
            'status'        => 'active',
            'business_type' => 'hybrid',
        ]);
        URL::forceRootUrl('http://hybridpaws.pawpass.com');

        KennelUnit::factory()->count(2)->create([
            'tenant_id' => $hybridTenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/portal/v1/kennel-units/check-availability?starts_at='.now()->addDay()->toDateString().'&ends_at='.now()->addDays(3)->toDateString()
        );

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
