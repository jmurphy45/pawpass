<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\KennelUnit;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VaccinationRequirement;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class ReservationControllerTest extends TestCase
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

        $this->tenant = Tenant::factory()->create(['slug' => 'reservation-test', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://reservation-test.pawpass.com');

        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'staff']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->staff)];
    }

    public function test_index_returns_reservations_for_tenant(): void
    {
        Reservation::factory()->count(2)->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->staff->id,
        ]);

        $other = Tenant::factory()->create(['slug' => 'other-res-test', 'status' => 'active']);
        Reservation::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/v1/reservations');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_can_filter_by_status(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/v1/reservations?status=confirmed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
    }

    public function test_index_can_filter_by_date(): void
    {
        // Overlaps 2026-04-05
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-04-03', 'ends_at' => '2026-04-06',
        ]);
        // Does NOT overlap 2026-04-05
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-04-10', 'ends_at' => '2026-04-12',
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/v1/reservations?date=2026-04-05');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_store_creates_reservation_without_unit(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'    => $this->dog->id,
            'starts_at' => '2026-05-01',
            'ends_at'   => '2026-05-04',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.dog_id', $this->dog->id);
        $response->assertJsonPath('data.kennel_unit_id', null);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_store_creates_reservation_and_assigns_unit(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'         => $this->dog->id,
            'kennel_unit_id' => $unit->id,
            'starts_at'      => '2026-05-01',
            'ends_at'        => '2026-05-04',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.kennel_unit_id', $unit->id);
    }

    public function test_store_rejects_overlapping_reservation(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-05-02', 'ends_at' => '2026-05-05',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'         => $this->dog->id,
            'kennel_unit_id' => $unit->id,
            'starts_at'      => '2026-05-01',
            'ends_at'        => '2026-05-04',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'UNIT_NOT_AVAILABLE');
    }

    public function test_store_denormalizes_customer_id_from_dog(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'    => $this->dog->id,
            'starts_at' => '2026-05-01',
            'ends_at'   => '2026-05-04',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->customer->id, $response->json('data.customer_id'));
    }

    public function test_store_rejects_dog_not_belonging_to_tenant(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other-dog-tenant', 'status' => 'active']);
        $otherDog = Dog::factory()->create(['tenant_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'    => $otherDog->id,
            'starts_at' => '2026-05-01',
            'ends_at'   => '2026-05-04',
        ]);

        $response->assertStatus(404);
    }

    public function test_show_returns_reservation_with_relationships(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        $reservation = Reservation::factory()->withUnit($unit)->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson("/api/admin/v1/reservations/{$reservation->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $reservation->id);
        $response->assertJsonPath('data.kennel_unit_id', $unit->id);
    }

    public function test_update_can_change_status_to_confirmed(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson("/api/admin/v1/reservations/{$reservation->id}", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'confirmed');
    }

    public function test_update_cancels_reservation_and_sets_cancelled_fields(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson("/api/admin/v1/reservations/{$reservation->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertNotNull($response->json('data.cancelled_at'));
        $this->assertEquals($this->staff->id, $response->json('data.cancelled_by'));
    }

    public function test_update_rejects_unit_change_if_new_dates_conflict(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        // Block the unit
        Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-06-01', 'ends_at' => '2026-06-05',
        ]);

        // A separate reservation we want to move to that unit
        $other = Dog::factory()->forCustomer($this->customer)->create();
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $other->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-06-02', 'ends_at' => '2026-06-04',
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson("/api/admin/v1/reservations/{$reservation->id}", [
            'kennel_unit_id' => $unit->id,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'UNIT_NOT_AVAILABLE');
    }

    public function test_update_allows_rescheduling_same_unit_same_reservation(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);
        $reservation = Reservation::factory()->withUnit($unit)->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'starts_at' => '2026-06-01', 'ends_at' => '2026-06-05',
        ]);

        $response = $this->withHeaders($this->authHeaders())->patchJson("/api/admin/v1/reservations/{$reservation->id}", [
            'ends_at' => '2026-06-06',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('2026-06-06', $response->json('data.ends_at'));
    }

    public function test_destroy_succeeds_for_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeaders())->deleteJson("/api/admin/v1/reservations/{$reservation->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
    }

    public function test_destroy_blocked_for_checked_in_reservation(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())->deleteJson("/api/admin/v1/reservations/{$reservation->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'CANNOT_DELETE_ACTIVE_RESERVATION');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_store_blocked_when_dog_missing_required_vaccine(): void
    {
        VaccinationRequirement::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vaccine_name' => 'Rabies',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'starts_at'   => '2026-07-01',
            'ends_at'     => '2026-07-05',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'DOG_VACCINATION_INCOMPLETE');
        $this->assertNotEmpty($response->json('violations'));
    }

    public function test_store_succeeds_when_dog_has_valid_vaccine(): void
    {
        VaccinationRequirement::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vaccine_name' => 'Rabies',
        ]);
        DogVaccination::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'dog_id'          => $this->dog->id,
            'vaccine_name'    => 'Rabies',
            'administered_at' => '2026-01-01',
            'expires_at'      => '2027-01-01',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'starts_at'   => '2026-07-01',
            'ends_at'     => '2026-07-05',
        ]);

        $response->assertStatus(201);
    }

    public function test_store_allows_override_of_vaccination_check(): void
    {
        VaccinationRequirement::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vaccine_name' => 'Rabies',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/admin/v1/reservations', [
            'dog_id'                    => $this->dog->id,
            'customer_id'               => $this->customer->id,
            'starts_at'                 => '2026-07-01',
            'ends_at'                   => '2026-07-05',
            'ignore_vaccination_check'  => true,
        ]);

        $response->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // ReservationResource includes checkout fields (Step 1 schema test)
    // -------------------------------------------------------------------------

    public function test_show_includes_checkout_fields(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'             => $this->tenant->id,
            'dog_id'                => $this->dog->id,
            'customer_id'           => $this->customer->id,
            'created_by'            => $this->staff->id,
            'status'                => 'checked_out',
            'checkout_charge_cents' => 27000,
            'checkout_pi_id'        => 'pi_checkout_test',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.checkout_charge_cents', 27000)
            ->assertJsonPath('data.checkout_pi_id', 'pi_checkout_test');

        $this->assertArrayHasKey('actual_checkout_at', $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // ReservationResource includes deposit fields
    // -------------------------------------------------------------------------

    public function test_show_includes_deposit_fields(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->staff->id,
            'deposit_amount_cents'  => 7500,
            'stripe_pi_id'         => 'pi_resource_test',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/admin/v1/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.deposit_amount_cents', 7500)
            ->assertJsonPath('data.stripe_pi_id', 'pi_resource_test');

        $this->assertArrayHasKey('deposit_captured_at', $response->json('data'));
        $this->assertArrayHasKey('deposit_refunded_at', $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Deposit capture / release (Step 5)
    // -------------------------------------------------------------------------

    public function test_update_to_checked_in_captures_hold(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test']);

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->staff->id,
            'stripe_pi_id'         => 'pi_hold3',
            'deposit_amount_cents'  => 5000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('capturePaymentIntent')
            ->once()
            ->with('pi_hold3', 'acct_test')
            ->andReturn((object) ['id' => 'pi_hold3', 'status' => 'succeeded']);
        $this->app->instance(StripeService::class, $stripe);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/admin/v1/reservations/{$reservation->id}", ['status' => 'checked_in']);

        $response->assertStatus(200);
        $this->assertNotNull(Reservation::find($reservation->id)->deposit_captured_at);
    }

    public function test_update_to_cancelled_releases_uncaptured_hold(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test']);

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->staff->id,
            'stripe_pi_id'         => 'pi_hold4',
            'deposit_amount_cents'  => 5000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with('pi_hold4', 'acct_test')
            ->andReturn((object) ['id' => 'pi_hold4', 'status' => 'canceled']);
        $this->app->instance(StripeService::class, $stripe);

        $response = $this->withHeaders($this->authHeaders())
            ->patchJson("/api/admin/v1/reservations/{$reservation->id}", ['status' => 'cancelled']);

        $response->assertStatus(200);
        $this->assertNotNull(Reservation::find($reservation->id)->deposit_refunded_at);
    }
}
