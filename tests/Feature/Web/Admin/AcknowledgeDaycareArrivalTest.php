<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\ParkingSpot;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AcknowledgeDaycareArrivalTest extends TestCase
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

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['parking_management', 'boarding', 'daycare']]);

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

    public function test_staff_can_acknowledge_daycare_curbside_arrival(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'starts_at' => now()->setTime(7, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/roster/daycare/{$appointment->id}/acknowledge-arrival")
            ->assertRedirect();

        $this->assertNotNull($appointment->fresh()->arrival_acknowledged_at);
    }

    public function test_owner_can_acknowledge_daycare_curbside_arrival(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'starts_at' => now()->setTime(7, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        $this->actingAs($this->owner)
            ->post("/admin/roster/daycare/{$appointment->id}/acknowledge-arrival")
            ->assertRedirect();

        $this->assertNotNull($appointment->fresh()->arrival_acknowledged_at);
    }

    public function test_acknowledge_returns_403_when_parking_management_inactive(): void
    {
        PlatformPlan::factory()->create(['slug' => 'pro', 'features' => ['daycare']]);
        $this->tenant->update(['plan' => 'pro']);

        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
            'status' => 'confirmed',
            'parking_spot_id' => $this->spot->id,
            'arrived_at' => now()->subMinutes(3),
            'starts_at' => now()->setTime(7, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/roster/daycare/{$appointment->id}/acknowledge-arrival")
            ->assertForbidden();
    }

    public function test_acknowledge_returns_422_when_appointment_has_not_arrived(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
            'status' => 'confirmed',
            'arrived_at' => null,
            'starts_at' => now()->setTime(7, 0),
            'ends_at' => now()->setTime(18, 0),
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/roster/daycare/{$appointment->id}/acknowledge-arrival")
            ->assertStatus(422);
    }
}
