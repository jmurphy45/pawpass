<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\ParkingSpot;
use App\Models\PlatformPlan;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AcknowledgeArrivalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    private ParkingSpot $spot;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['parking_management', 'boarding']]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'pawco',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        URL::forceRootUrl('http://pawco.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'business_owner',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();

        $this->spot = ParkingSpot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'spot_number' => 'A1',
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    public function test_staff_can_acknowledge_curbside_arrival(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/boarding/reservations/{$reservation->id}/acknowledge-arrival")
            ->assertRedirect();

        $this->assertNotNull($reservation->fresh()->arrival_acknowledged_at);
    }

    public function test_owner_can_acknowledge_curbside_arrival(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->owner)
            ->post("/admin/boarding/reservations/{$reservation->id}/acknowledge-arrival")
            ->assertRedirect();

        $this->assertNotNull($reservation->fresh()->arrival_acknowledged_at);
    }

    public function test_acknowledge_returns_403_when_parking_management_inactive(): void
    {
        // boarding-only plan: plan:boarding middleware passes, but parking_management check inside method fails
        PlatformPlan::factory()->create(['slug' => 'pro', 'features' => ['boarding']]);
        $this->tenant->update(['plan' => 'pro']);

        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/boarding/reservations/{$reservation->id}/acknowledge-arrival")
            ->assertForbidden();
    }

    public function test_acknowledge_returns_422_when_reservation_has_not_arrived(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed',
            'arrived_at' => null,
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/boarding/reservations/{$reservation->id}/acknowledge-arrival")
            ->assertStatus(422);
    }
}
