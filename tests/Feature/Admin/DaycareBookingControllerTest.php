<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\DaycareBookingDetail;
use App\Models\Dog;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class DaycareBookingControllerTest extends TestCase
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

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['daycare_booking']]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'dcb-test',
            'status' => 'active',
            'plan' => 'starter',
            'daily_dog_limit' => 10,
        ]);
        URL::forceRootUrl('http://dcb-test.pawpass.com');
        app()->instance('current.tenant.id', $this->tenant->id);

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create(['credit_balance' => 5]);
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
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_index_returns_daycare_bookings(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/daycare-bookings');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_excludes_other_service_types(): void
    {
        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/daycare-bookings');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_enforces_tenant_isolation(): void
    {
        $other = Tenant::factory()->create(['status' => 'active', 'daily_dog_limit' => 5]);
        $otherCustomer = Customer::factory()->create(['tenant_id' => $other->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        Appointment::factory()->pending()->create([
            'tenant_id' => $other->id,
            'dog_id' => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'service_type' => 'daycare_booking',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/v1/daycare-bookings');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_store_creates_appointment_and_holds_credit(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/daycare-bookings', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.service_type', 'daycare_booking');
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'dog_id' => $this->dog->id,
            'service_type' => 'daycare_booking',
            'status' => 'pending',
        ]);

        $this->assertSame(4, $this->dog->fresh()->credit_balance);
    }

    public function test_store_creates_daycare_booking_detail_with_hold_ledger_id(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/daycare-bookings', $this->validPayload())
            ->assertStatus(201);

        $appointment = Appointment::where('service_type', 'daycare_booking')->first();
        $detail = DaycareBookingDetail::where('appointment_id', $appointment->id)->first();

        $this->assertNotNull($detail);
        $this->assertNotNull($detail->credit_hold_ledger_id);
    }

    public function test_store_returns_409_when_capacity_full(): void
    {
        $this->tenant->update(['daily_dog_limit' => 1]);

        $other = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($other)->create(['credit_balance' => 5]);

        Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $otherDog->id,
            'customer_id' => $other->id,
            'service_type' => 'daycare_booking',
            'starts_at' => now()->addDay()->startOfDay()->addHours(8),
            'ends_at' => now()->addDay()->startOfDay()->addHours(17),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/daycare-bookings', $this->validPayload());

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'CAPACITY_FULL');
    }

    public function test_store_returns_422_when_no_credits(): void
    {
        $this->dog->update(['credit_balance' => 0]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/daycare-bookings', $this->validPayload());

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'INSUFFICIENT_CREDITS');
    }

    public function test_show_returns_daycare_booking(): void
    {
        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/daycare-bookings/{$appointment->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $appointment->id);
    }

    public function test_show_returns_404_for_non_daycare_booking(): void
    {
        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'vet',
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/daycare-bookings/{$appointment->id}")
            ->assertStatus(404);
    }

    public function test_confirm_transitions_to_confirmed(): void
    {
        $appointment = Appointment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'daycare_booking',
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/daycare-bookings/{$appointment->id}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_cancel_releases_credit_hold_and_transitions_to_cancelled(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/admin/v1/daycare-bookings', $this->validPayload());

        $response->assertStatus(201);
        $appointmentId = $response->json('data.id');
        $this->assertSame(4, $this->dog->fresh()->credit_balance);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/v1/daycare-bookings/{$appointmentId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $this->dog->fresh()->credit_balance);
    }
}
