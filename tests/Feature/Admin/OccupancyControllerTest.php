<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class OccupancyControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create(['slug' => 'occupancy-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://occupancy-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_returns_units_with_overlapping_reservations(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['name' => 'Rex']);

        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'created_by'  => $this->staff->id,
            'starts_at'   => '2026-05-01',
            'ends_at'     => '2026-05-05',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-05-01&to=2026-05-07');

        $response->assertStatus(200);
        $unitData = collect($response->json('data'))->firstWhere('id', $unit->id);
        $this->assertNotNull($unitData);
        $this->assertCount(1, $unitData['reservations']);
        $this->assertEquals('Rex', $unitData['reservations'][0]['dog_name']);
    }

    public function test_excludes_cancelled_reservations(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Reservation::factory()->withUnit($unit)->cancelled()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-05-01', 'ends_at' => '2026-05-05',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-05-01&to=2026-05-07');

        $unitData = collect($response->json('data'))->firstWhere('id', $unit->id);
        $this->assertEmpty($unitData['reservations']);
    }

    public function test_excludes_non_overlapping_reservations(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $dog->id,
            'customer_id' => $customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-06-01', 'ends_at' => '2026-06-05',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-05-01&to=2026-05-07');

        $unitData = collect($response->json('data'))->firstWhere('id', $unit->id);
        $this->assertEmpty($unitData['reservations']);
    }

    public function test_filters_to_current_tenant_units_only(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $other = Tenant::factory()->create(['slug' => 'other-occupancy', 'status' => 'active']);
        KennelUnit::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-05-01&to=2026-05-07');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_uses_defaults_when_no_params(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy');

        $response->assertStatus(200);
        $this->assertArrayHasKey('from', $response->json('meta'));
        $this->assertArrayHasKey('to', $response->json('meta'));
    }

    public function test_rejects_to_before_from(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-05-10&to=2026-05-01');

        $response->assertStatus(422);
    }

    public function test_rejects_range_over_90_days(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/occupancy?from=2026-01-01&to=2026-06-01');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DATE_RANGE_TOO_LARGE');
    }
}
