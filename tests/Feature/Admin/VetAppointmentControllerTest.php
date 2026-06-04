<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\BookableResource;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VetAppointmentDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class VetAppointmentControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['vet_appointments']]);

        $this->tenant = Tenant::factory()->create(['slug' => 'vet-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://vet-test.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current.tenant.id');
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'reason' => 'Annual check-up',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
            'price_cents' => 7500,
            'duration_mins' => 30,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_vet_appointments_for_tenant(): void
    {
        Appointment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/vet-appointments');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_does_not_return_other_tenants_appointments(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        app()->forgetInstance('current.tenant.id');
        Appointment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'dog_id' => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'service_type' => 'vet',
        ]);
        app()->instance('current.tenant.id', $this->tenant->id);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/vet-appointments');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        Appointment::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'vet', 'status' => 'pending',
        ]);
        Appointment::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'vet', 'status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/vet-appointments?status=confirmed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_appointment_and_detail(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/vet-appointments', $this->validPayload());

        $response->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'service_type' => 'vet',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('vet_appointment_details', [
            'tenant_id' => $this->tenant->id,
            'reason' => 'Annual check-up',
            'price_cents' => 7500,
        ]);
    }

    public function test_store_returns_409_when_resource_already_booked(): void
    {
        $resource = BookableResource::factory()->examRoom()->create(['tenant_id' => $this->tenant->id]);

        // First booking
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/vet-appointments', $this->validPayload(['resource_id' => $resource->id]));

        // Overlapping booking
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/vet-appointments', $this->validPayload(['resource_id' => $resource->id]));

        $response->assertStatus(409)
            ->assertJsonPath('error', 'RESOURCE_NOT_AVAILABLE');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/vet-appointments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dog_id', 'customer_id', 'reason', 'starts_at', 'price_cents']);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_vet_appointment_with_detail(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'vet', 'status' => 'pending',
        ]);
        VetAppointmentDetail::factory()->create([
            'tenant_id' => $this->tenant->id, 'appointment_id' => $appointment->id,
            'reason' => 'Blood work', 'price_cents' => 12000,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/vet-appointments/{$appointment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.detail.reason', 'Blood work');
    }

    public function test_show_returns_404_for_non_vet_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'grooming',
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/vet-appointments/{$appointment->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // confirm / cancel
    // -------------------------------------------------------------------------

    public function test_confirm_transitions_pending_to_confirmed(): void
    {
        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'vet',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/vet-appointments/{$appointment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals('confirmed', $appointment->fresh()->status);
    }

    public function test_cancel_transitions_appointment_to_cancelled(): void
    {
        $appointment = Appointment::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'service_type' => 'vet',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/vet-appointments/{$appointment->id}/cancel", [
                'cancellation_reason' => 'Owner request',
            ])
            ->assertOk();

        $fresh = $appointment->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertEquals('Owner request', $fresh->cancellation_reason);
        $this->assertNotNull($fresh->cancelled_at);
    }
}
