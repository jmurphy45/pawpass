<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
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

    private User $user;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug'          => 'kenneltest',
            'status'        => 'active',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://kenneltest.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_customer_can_list_their_reservations(): void
    {
        Reservation::factory()->count(2)->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        // Reservation for a different customer on the same tenant — should not appear
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/reservations');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
            'status'      => 'pending',
        ]);
        Reservation::factory()->confirmed()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/portal/v1/reservations?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_unauthenticated_cannot_list_reservations(): void
    {
        $this->getJson('/api/portal/v1/reservations')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_customer_can_view_their_own_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reservation->id);
    }

    public function test_customer_cannot_view_another_customers_reservation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'created_by'  => $this->user->id,
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/portal/v1/reservations/{$reservation->id}")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_customer_can_create_a_reservation_for_own_dog(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'nightly_rate_cents' => 6000]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'         => $this->dog->id,
                'kennel_unit_id' => $unit->id,
                'starts_at'      => now()->addDay()->toDateString(),
                'ends_at'        => now()->addDays(3)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.dog_id', $this->dog->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.nightly_rate_cents', 6000);

        $this->assertDatabaseHas('reservations', [
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status'      => 'pending',
        ]);
    }

    public function test_customer_cannot_create_reservation_for_another_customers_dog(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'    => $otherDog->id,
                'starts_at' => now()->addDay()->toDateString(),
                'ends_at'   => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_create_returns_409_when_unit_not_available(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        // Existing conflicting reservation
        Reservation::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'kennel_unit_id' => $unit->id,
            'starts_at'      => now()->addDay(),
            'ends_at'        => now()->addDays(4),
            'created_by'     => $this->user->id,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'         => $this->dog->id,
                'kennel_unit_id' => $unit->id,
                'starts_at'      => now()->addDays(2)->toDateString(),
                'ends_at'        => now()->addDays(5)->toDateString(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'UNIT_NOT_AVAILABLE');
    }

    public function test_create_returns_422_when_vaccination_incomplete(): void
    {
        VaccinationRequirement::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'vaccine_name' => 'Rabies',
        ]);
        // dog has no vaccinations

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'    => $this->dog->id,
                'starts_at' => now()->addDay()->toDateString(),
                'ends_at'   => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'DOG_VACCINATION_INCOMPLETE');
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function test_customer_can_cancel_a_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
            'status'      => 'pending',
        ]);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/reservations/{$reservation->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_customer_cannot_cancel_a_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->user->id,
        ]);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/reservations/{$reservation->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error', 'CANNOT_CANCEL_RESERVATION');
    }

    public function test_customer_cannot_cancel_another_customers_reservation(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $otherDog->id,
            'customer_id' => $otherCustomer->id,
            'created_by'  => $this->user->id,
        ]);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/reservations/{$reservation->id}/cancel")
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Deposit / hold payment (Steps 3 & 4)
    // -------------------------------------------------------------------------

    public function test_store_with_deposit_creates_hold_and_returns_client_secret(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'platform_fee_pct' => 5.0]);

        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id, 'nightly_rate_cents' => 8000]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createHoldPaymentIntent')
            ->once()
            ->with(5000, 'usd', 'acct_test', 250, Mockery::type('array'))
            ->andReturn((object) ['id' => 'pi_hold1', 'client_secret' => 'pi_hold1_secret']);
        $this->app->instance(StripeService::class, $stripe);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'               => $this->dog->id,
                'kennel_unit_id'       => $unit->id,
                'starts_at'            => now()->addDay()->toDateString(),
                'ends_at'              => now()->addDays(3)->toDateString(),
                'deposit_amount_cents' => 5000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('client_secret', 'pi_hold1_secret');

        $this->assertDatabaseHas('reservations', [
            'dog_id'               => $this->dog->id,
            'stripe_pi_id'         => 'pi_hold1',
            'deposit_amount_cents' => 5000,
        ]);
    }

    public function test_store_without_deposit_returns_null_client_secret(): void
    {
        $unit = KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createHoldPaymentIntent');
        $this->app->instance(StripeService::class, $stripe);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/reservations', [
                'dog_id'    => $this->dog->id,
                'starts_at' => now()->addDay()->toDateString(),
                'ends_at'   => now()->addDays(3)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('client_secret', null);
    }

    public function test_cancel_with_stripe_pi_releases_hold(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->user->id,
            'status'               => 'pending',
            'stripe_pi_id'         => 'pi_hold2',
            'deposit_amount_cents' => 5000,
        ]);

        $this->tenant->update(['stripe_account_id' => 'acct_test']);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with('pi_hold2', 'acct_test');
        $this->app->instance(StripeService::class, $stripe);

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/portal/v1/reservations/{$reservation->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull(Reservation::find($reservation->id)->deposit_refunded_at);
    }

    // -------------------------------------------------------------------------
    // Deposit columns (Step 1 schema test)
    // -------------------------------------------------------------------------

    public function test_reservation_stores_deposit_columns(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->user->id,
            'deposit_amount_cents' => 5000,
            'stripe_pi_id'       => 'pi_test_hold123',
        ]);

        $this->assertDatabaseHas('reservations', [
            'id'                 => $reservation->id,
            'deposit_amount_cents' => 5000,
            'stripe_pi_id'       => 'pi_test_hold123',
        ]);
        $this->assertNull($reservation->deposit_captured_at);
        $this->assertNull($reservation->deposit_refunded_at);
    }
}
