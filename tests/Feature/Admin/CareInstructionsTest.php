<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Reservation;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class CareInstructionsTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        PlatformPlan::factory()->create(['slug' => 'pro', 'features' => ['boarding']]);

        $this->tenant = Tenant::factory()->create(['slug' => 'care-test', 'status' => 'active', 'plan' => 'pro']);
        URL::forceRootUrl('http://care-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create();

        $this->reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'created_by'  => $this->staff->id,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_update_reservation_accepts_care_instruction_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/reservations/{$this->reservation->id}",
            [
                'feeding_schedule'  => 'Twice daily, 1 cup each',
                'medication_notes'  => 'Arthritis medication at 8am',
                'behavioral_notes'  => 'Anxious around other dogs',
                'emergency_contact' => 'Jane: 555-1234',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.feeding_schedule', 'Twice daily, 1 cup each');
        $response->assertJsonPath('data.medication_notes', 'Arthritis medication at 8am');
        $response->assertJsonPath('data.behavioral_notes', 'Anxious around other dogs');
        $response->assertJsonPath('data.emergency_contact', 'Jane: 555-1234');
    }

    public function test_care_instruction_fields_are_optional(): void
    {
        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/reservations/{$this->reservation->id}",
            ['status' => 'confirmed']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.feeding_schedule', null);
    }

    public function test_care_instruction_fields_appear_in_show(): void
    {
        $this->reservation->update(['feeding_schedule' => 'Morning and evening']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$this->reservation->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.feeding_schedule', 'Morning and evening');
        $this->assertArrayHasKey('medication_notes', $response->json('data'));
        $this->assertArrayHasKey('behavioral_notes', $response->json('data'));
        $this->assertArrayHasKey('emergency_contact', $response->json('data'));
    }

    public function test_care_instruction_max_length_validated(): void
    {
        $response = $this->withHeaders($this->authHeaders())->patchJson(
            "/api/admin/v1/reservations/{$this->reservation->id}",
            ['feeding_schedule' => str_repeat('a', 2001)]
        );

        $response->assertStatus(422);
    }
}
