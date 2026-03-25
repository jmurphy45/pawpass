<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AddonType;
use App\Models\BoardingReportCard;
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

class BoardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'boarding-web', 'status' => 'active', 'plan' => 'starter']);
        URL::forceRootUrl('http://boarding-web.pawpass.com');

        $this->staff    = User::factory()->staff()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->dog      = Dog::factory()->forCustomer($this->customer)->create();
    }

    public function test_reservations_index_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/reservations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/Reservations'));
    }

    public function test_reservations_index_contains_reservations_prop(): void
    {
        Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/reservations');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/Reservations')
            ->has('reservations')
        );
    }

    public function test_reservation_show_renders_inertia_page(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/boarding/reservations/{$reservation->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/ReservationShow'));
    }

    public function test_reservation_show_contains_required_props(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        DogVaccination::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'vaccine_name' => 'Rabies', 'administered_at' => '2026-01-01', 'expires_at' => '2027-01-01',
        ]);

        $this->actingAs($this->staff);

        $response = $this->get("/admin/boarding/reservations/{$reservation->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/ReservationShow')
            ->has('reservation')
            ->has('reportCards')
            ->has('addons')
            ->has('addonTypes')
            ->has('vaccinationCompliance')
        );
    }

    public function test_occupancy_renders_inertia_page(): void
    {
        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/occupancy');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Boarding/Occupancy'));
    }

    public function test_occupancy_contains_units_and_range_props(): void
    {
        KennelUnit::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/boarding/occupancy?from=2026-05-01&to=2026-05-07');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Boarding/Occupancy')
            ->has('units')
            ->has('from')
            ->has('to')
        );
    }

    // -------------------------------------------------------------------------
    // updateReservation (Step 3)
    // -------------------------------------------------------------------------

    public function test_staff_can_confirm_a_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'confirmed']);

        $response->assertRedirect();
        $this->assertEquals('confirmed', $reservation->fresh()->status);
    }

    public function test_staff_can_check_in_a_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'checked_in']);

        $response->assertRedirect();
        $this->assertEquals('checked_in', $reservation->fresh()->status);
    }

    public function test_staff_can_check_out_a_checked_in_reservation(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'checked_out']);

        $response->assertRedirect();
        $this->assertEquals('checked_out', $reservation->fresh()->status);
    }

    public function test_staff_can_cancel_a_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'cancelled']);

        $response->assertRedirect();
        $fresh = $reservation->fresh();
        $this->assertEquals('cancelled', $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals($this->staff->id, $fresh->cancelled_by);
    }

    public function test_check_in_captures_stripe_hold(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_web_test']);

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->staff->id,
            'stripe_pi_id'         => 'pi_web_hold',
            'deposit_amount_cents'  => 4000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('capturePaymentIntent')
            ->once()
            ->with('pi_web_hold', 'acct_web_test');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'checked_in']);

        $this->assertNotNull($reservation->fresh()->deposit_captured_at);
    }

    public function test_cancel_releases_stripe_hold(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_web_test']);

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'            => $this->tenant->id,
            'dog_id'               => $this->dog->id,
            'customer_id'          => $this->customer->id,
            'created_by'           => $this->staff->id,
            'stripe_pi_id'         => 'pi_web_cancel',
            'deposit_amount_cents'  => 4000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with('pi_web_cancel', 'acct_web_test');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'cancelled']);

        $this->assertNotNull($reservation->fresh()->deposit_refunded_at);
    }

    public function test_invalid_transition_returns_redirect_with_error(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id,
            'customer_id' => $this->customer->id, 'created_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->staff);

        $response = $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'checked_in']);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertEquals('pending', $reservation->fresh()->status);
    }
}
