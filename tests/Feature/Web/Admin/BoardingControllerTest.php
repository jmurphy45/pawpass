<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AddonType;
use App\Models\BoardingReportCard;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\KennelUnit;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Models\ReservationAddon;
use App\Models\PlatformPlan;
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

        PlatformPlan::factory()->create(['slug' => 'pro', 'features' => ['boarding', 'addon_services']]);
        $this->tenant = Tenant::factory()->create(['slug' => 'boarding-web', 'status' => 'active', 'plan' => 'pro']);
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
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->staff->id,
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
            'status'         => 'authorized',
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_web_hold',
            'type'         => 'deposit',
            'status'       => 'authorized',
            'amount_cents' => 4000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('capturePaymentIntent')
            ->once()
            ->with('pi_web_hold', 'acct_web_test');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'checked_in']);

        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_cancel_releases_stripe_hold(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_web_test']);

        $reservation = Reservation::factory()->confirmed()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by'  => $this->staff->id,
        ]);

        $order = Order::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
            'status'         => 'authorized',
        ]);

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'stripe_pi_id' => 'pi_web_cancel',
            'type'         => 'deposit',
            'status'       => 'authorized',
            'amount_cents' => 4000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with('pi_web_cancel', 'acct_web_test');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->patch("/admin/boarding/reservations/{$reservation->id}", ['status' => 'cancelled']);

        $this->assertEquals(PaymentStatus::Refunded, $payment->fresh()->status);
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

    // -------------------------------------------------------------------------
    // processCheckout (Step 2)
    // -------------------------------------------------------------------------

    public function test_checkout_charges_balance_to_saved_card(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_co_test', 'platform_fee_pct' => 5.0]);

        // Customer with saved PM
        $this->customer->update([
            'stripe_customer_id'      => 'cus_test',
            'stripe_payment_method_id' => 'pm_test_card',
            'stripe_pm_last4'          => '4242',
            'stripe_pm_brand'          => 'Visa',
        ]);

        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->staff->id,
            'starts_at'          => now()->subDays(3)->startOfDay(),
            'ends_at'            => now()->startOfDay(),
            'nightly_rate_cents' => 8000,
        ]);

        // Pre-existing deposit order + payment
        $order = Order::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
            'status'         => 'pending',
        ]);

        OrderPayment::factory()->forOrder($order)->deposit()->create([
            'stripe_pi_id' => 'pi_dep',
            'amount_cents' => 5000,
        ]);

        // 4 nights actual: balance = (4 × 8000) + 0 addons - 5000 = 27000
        $actualCheckout = now()->addDay()->toDateString(); // 1 extra night

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->with(
                27000, 'usd', 'acct_co_test',
                Mockery::type('int'), // fee
                Mockery::type('array'),
                'cus_test', true, true, 'pm_test_card',
                Mockery::any(), Mockery::any()
            )
            ->andReturn((object) ['id' => 'pi_checkout1', 'client_secret' => 'pi_checkout1_secret']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $response = $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => $actualCheckout,
        ]);

        $response->assertRedirect();
        $fresh = $reservation->fresh();
        $this->assertEquals('checked_out', $fresh->status);
        $this->assertNotNull($fresh->actual_checkout_at);

        $this->assertDatabaseHas('order_payments', [
            'order_id'     => $order->id,
            'type'         => 'balance',
            'amount_cents' => 27000,
            'stripe_pi_id' => 'pi_checkout1',
        ]);
    }

    public function test_checkout_without_saved_card_transitions_without_stripe_call(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_co_test']);

        // Customer with NO saved PM
        $this->customer->update(['stripe_payment_method_id' => null]);

        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->staff->id,
            'nightly_rate_cents' => 8000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $response = $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $fresh = $reservation->fresh();
        $this->assertEquals('checked_out', $fresh->status);
        $this->assertDatabaseMissing('order_payments', ['type' => 'balance', 'order_id' => $fresh->order?->id]);
    }

    public function test_checkout_with_zero_balance_skips_stripe(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_co_test']);
        $this->customer->update(['stripe_payment_method_id' => 'pm_test']);

        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->staff->id,
            'starts_at'          => now()->subDay()->startOfDay(),
            'ends_at'            => now()->startOfDay(),
            'nightly_rate_cents' => 5000,
        ]);

        // Deposit > 1 night — zero balance
        $order = Order::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'package_id'     => null,
            'reservation_id' => $reservation->id,
            'type'           => 'boarding',
            'status'         => 'pending',
        ]);

        OrderPayment::factory()->forOrder($order)->deposit()->create([
            'amount_cents' => 10000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => now()->toDateString(),
        ]);

        $fresh = $reservation->fresh();
        $this->assertEquals('checked_out', $fresh->status);
        $this->assertDatabaseMissing('order_payments', ['type' => 'balance', 'order_id' => $order->id]);
    }

    public function test_checkout_with_date_before_starts_at_returns_error(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'  => $this->tenant->id,
            'dog_id'     => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->staff->id,
            'starts_at'  => now()->addDays(2)->startOfDay(),
            'ends_at'    => now()->addDays(5)->startOfDay(),
        ]);

        $this->actingAs($this->staff);
        $response = $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => now()->toDateString(), // before starts_at
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('actual_checkout_date');
        $this->assertEquals('checked_in', $reservation->fresh()->status);
    }

    public function test_checkout_with_missing_date_returns_error(): void
    {
        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'  => $this->tenant->id,
            'dog_id'     => $this->dog->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff);
        $response = $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", []);

        $response->assertRedirect();
        $response->assertSessionHasErrors('actual_checkout_date');
    }

    public function test_checkout_extended_stay_uses_actual_date_for_calculation(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_co_test', 'platform_fee_pct' => 5.0]);
        $this->customer->update([
            'stripe_customer_id'      => 'cus_test2',
            'stripe_payment_method_id' => 'pm_test2',
        ]);

        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->staff->id,
            'starts_at'          => now()->subDays(3)->startOfDay(),
            'ends_at'            => now()->startOfDay(),
            'nightly_rate_cents' => 10000,
        ]);

        // No deposit order needed — zero deposit means no pre-existing order payment

        // Staying 2 extra nights → 5 nights total
        $extendedDate = now()->addDays(2)->toDateString();

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->with(50000, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(),
                   Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(),
                   Mockery::any(), Mockery::any())
            ->andReturn((object) ['id' => 'pi_ext', 'client_secret' => 'secret']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => $extendedDate,
        ]);

        $this->assertDatabaseHas('order_payments', [
            'type'         => 'balance',
            'amount_cents' => 50000,
            'stripe_pi_id' => 'pi_ext',
        ]);
    }

    public function test_destroy_addon_removes_reservation_addon(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status'      => 'confirmed',
            'created_by'  => $this->staff->id,
        ]);
        $addonType = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);
        $addon     = ReservationAddon::create([
            'reservation_id'   => $reservation->id,
            'addon_type_id'    => $addonType->id,
            'quantity'         => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/boarding/reservations/{$reservation->id}/addons/{$addon->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('reservation_addons', ['id' => $addon->id]);
    }

    public function test_destroy_addon_409_when_reservation_checked_out(): void
    {
        $reservation = Reservation::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'dog_id'      => $this->dog->id,
            'customer_id' => $this->customer->id,
            'status'      => 'checked_out',
            'created_by'  => $this->staff->id,
        ]);
        $addonType = AddonType::factory()->create(['tenant_id' => $this->tenant->id]);
        $addon     = ReservationAddon::create([
            'reservation_id'   => $reservation->id,
            'addon_type_id'    => $addonType->id,
            'quantity'         => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/boarding/reservations/{$reservation->id}/addons/{$addon->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('reservation_addons', ['id' => $addon->id]);
    }

    public function test_checkout_stripe_failure_leaves_reservation_in_checked_in_state(): void
    {
        $this->tenant->update(['stripe_account_id' => 'acct_stripe_fail', 'platform_fee_pct' => 5.0]);

        $this->customer->update([
            'stripe_customer_id'       => 'cus_fail',
            'stripe_payment_method_id' => 'pm_fail_card',
        ]);

        $reservation = Reservation::factory()->checkedIn()->create([
            'tenant_id'          => $this->tenant->id,
            'dog_id'             => $this->dog->id,
            'customer_id'        => $this->customer->id,
            'created_by'         => $this->staff->id,
            'starts_at'          => now()->subDays(2)->startOfDay(),
            'ends_at'            => now()->startOfDay(),
            'nightly_rate_cents' => 5000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->andThrow(new \Stripe\Exception\ApiConnectionException('Stripe unavailable'));
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);
        $response = $this->post("/admin/boarding/reservations/{$reservation->id}/checkout", [
            'actual_checkout_date' => now()->toDateString(),
        ]);

        // Must redirect back with an error, not succeed
        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Reservation must still be checked_in — not checked_out
        $this->assertEquals('checked_in', $reservation->fresh()->status);
    }
}
