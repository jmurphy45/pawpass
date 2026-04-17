<?php

namespace Tests\Feature\Web\Admin;

use App\Models\AddonType;
use App\Models\Attendance;
use App\Models\AttendanceAddon;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendancePaymentService;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class RosterControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'testco',
            'status' => 'active',
            'plan' => 'starter',
            'checkin_block_at_zero' => false,
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();
    }

    public function test_index_shows_roster(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['name' => 'Buddy', 'credit_balance' => 5]);

        $this->actingAs($this->staff);

        $response = $this->get('/admin/roster');

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->has('roster', 1)
            ->where('roster.0.name', 'Buddy')
            ->where('roster.0.attendance_state', 'not_in')
        );
    }

    public function test_checkin_creates_attendance_record(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
    }

    public function test_checkout_sets_checked_out_at(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNotNull($attendance->fresh()->checked_out_at);
    }

    public function test_checkout_does_not_close_previous_day_open_attendance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull(Attendance::where('dog_id', $dog->id)->first()->checked_out_at);
    }

    public function test_checkout_closes_todays_record_not_previous_day(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $oldAttendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => null,
        ]);

        $todayAttendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNull($oldAttendance->fresh()->checked_out_at);
        $this->assertNotNull($todayAttendance->fresh()->checked_out_at);
    }

    public function test_cannot_checkin_dog_with_zero_credits_when_blocked(): void
    {
        $this->tenant->update(['checkin_block_at_zero' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
    }

    public function test_auto_replenish_charges_and_checks_in_when_blocking_disabled(): void
    {
        // tenant already has checkin_block_at_zero = false (setUp)
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturn(true);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
    }

    public function test_auto_replenish_failure_blocks_web_checkin(): void
    {
        // tenant already has checkin_block_at_zero = false (setUp)
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->andReturn(false);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
    }

    public function test_auto_replenish_checkin_passes_attendance_to_trigger_sync(): void
    {
        $package = Package::factory()->autoReplenish()->create([
            'tenant_id' => $this->tenant->id,
            'credit_count' => 5,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_payment_method_id' => 'pm_test_123',
        ]);

        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class)
            ->shouldReceive('triggerSync')
            ->once()
            ->withArgs(function ($argDog, $argAttendance) use ($dog) {
                return $argDog->id === $dog->id
                    && $argAttendance instanceof Attendance
                    && $argAttendance->dog_id === $dog->id;
            })
            ->andReturn(true);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $this->assertDatabaseHas('attendances', ['dog_id' => $dog->id]);
    }

    public function test_checkout_calls_capture_authorized_for_authorized_order(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->mock(AttendancePaymentService::class)
            ->shouldReceive('captureAuthorized')
            ->once()
            ->withArgs(fn ($a) => $a->id === $attendance->id);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);
    }

    // -------------------------------------------------------------------------
    // Attendance addon tests
    // -------------------------------------------------------------------------

    public function test_store_attendance_addon_saves_addon_when_checked_in(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/roster/attendances/{$attendance->id}/addons", [
            'addon_type_id' => $addonType->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_addons', [
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'unit_price_cents' => 1500,
        ]);
        // No order yet — dog is still checked in
        $this->assertDatabaseMissing('orders', ['attendance_id' => $attendance->id]);
    }

    public function test_store_attendance_addon_rejects_boarding_only_addon(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $boardingOnly = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'boarding',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/roster/attendances/{$attendance->id}/addons", [
            'addon_type_id' => $boardingOnly->id,
        ]);

        $response->assertNotFound();
    }

    public function test_store_attendance_addon_charges_immediately_when_already_checked_out(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'platform_fee_pct' => 5.0]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subHours(3),
            'checked_out_at' => now()->subHour(),
        ]);
        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'both',
            'price_cents' => 2000,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn((object) ['id' => 'pi_addon', 'client_secret' => 'secret']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/roster/attendances/{$attendance->id}/addons", [
            'addon_type_id' => $addonType->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'attendance_id' => $attendance->id,
            'type' => 'daycare',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_addon',
            'amount_cents' => 2000,
            'status' => 'paid',
        ]);
    }

    public function test_checkout_charges_addons_on_dog_checkout(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_checkout',
            'stripe_payment_method_id' => 'pm_checkout',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'platform_fee_pct' => 5.0]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);
        AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => 1500,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn((object) ['id' => 'pi_checkout', 'client_secret' => 'secret']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNotNull($attendance->fresh()->checked_out_at);
        $this->assertDatabaseHas('orders', [
            'attendance_id' => $attendance->id,
            'type' => 'daycare',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_checkout',
            'amount_cents' => 1500,
            'status' => 'paid',
        ]);
    }

    public function test_checkout_combines_replenish_and_addons_into_single_order(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_combine',
            'stripe_payment_method_id' => 'pm_combine',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0]);
        $this->tenant->update([
            'stripe_account_id' => 'acct_test',
            'platform_fee_pct' => 5.0,
            'tax_collection_enabled' => true,
            'billing_address' => ['postal_code' => '10001', 'country' => 'US'],
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        // Simulate the auto-replenish order created at check-in
        $authorizedOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'attendance_id' => $attendance->id,
            'status' => 'authorized',
            'total_amount' => '21.80',
            'subtotal_cents' => 2000,
            'tax_amount_cents' => 180,
            'stripe_tax_calc_id' => 'txrc_orig',
            'platform_fee_pct' => 5.0,
        ]);
        $authorizedOrder->lineItems()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Day Pack',
            'quantity' => 1,
            'unit_price_cents' => 2000,
            'sort_order' => 0,
        ]);
        $authorizedOrder->payments()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_pi_id' => 'pi_auth',
            'amount_cents' => 2180,
            'type' => 'full',
            'status' => 'authorized',
        ]);

        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);
        AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => 1500,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('calculateTax')
            ->once()
            ->andReturn((object) ['tax_amount_exclusive' => 162, 'id' => 'txrc_test']);
        $stripe->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_auth', 'acct_test')
            ->andReturn((object) ['id' => 'pi_auth', 'status' => 'requires_confirmation']);
        $stripe->shouldReceive('updatePaymentIntentAmount')
            ->once()
            ->with('pi_auth', 3662, 'acct_test', Mockery::any())
            ->andReturn((object) ['id' => 'pi_auth']);
        $stripe->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_auth', 'acct_test')
            ->andReturn((object) ['id' => 'pi_auth', 'status' => 'requires_capture']);
        $stripe->shouldReceive('capturePaymentIntent')
            ->once()
            ->with('pi_auth', 'acct_test')
            ->andReturn((object) ['id' => 'pi_auth', 'status' => 'succeeded']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        // Only ONE order for this attendance
        $this->assertSame(1, Order::where('attendance_id', $attendance->id)->count());

        $authorizedOrder->refresh();
        $this->assertSame(3500, $authorizedOrder->subtotal_cents);
        $this->assertSame(162, $authorizedOrder->tax_amount_cents);
        $this->assertSame('txrc_test', $authorizedOrder->stripe_tax_calc_id);
        $this->assertEquals('36.62', $authorizedOrder->total_amount);
        $this->assertSame(2, $authorizedOrder->lineItems()->count());
        $this->assertSame(\App\Enums\OrderStatus::Paid, $authorizedOrder->status);

        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_auth',
            'amount_cents' => 3662,
            'status' => 'paid',
        ]);
    }

    public function test_checkout_charges_addons_with_tax_when_dog_has_credits(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_tax',
            'stripe_payment_method_id' => 'pm_tax',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);
        $this->tenant->update([
            'stripe_account_id' => 'acct_test',
            'platform_fee_pct' => 5.0,
            'tax_collection_enabled' => true,
            'billing_address' => ['postal_code' => '10001', 'country' => 'US'],
        ]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);
        AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => 1500,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('calculateTax')
            ->once()
            ->andReturn((object) ['tax_amount_exclusive' => 135, 'id' => 'txrc_addon']);
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->with(1635, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn((object) ['id' => 'pi_tax', 'client_secret' => 'secret']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $this->assertDatabaseHas('orders', [
            'attendance_id' => $attendance->id,
            'tax_amount_cents' => 135,
            'stripe_tax_calc_id' => 'txrc_addon',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_tax',
            'amount_cents' => 1635,
            'status' => 'paid',
        ]);
    }

    public function test_checkout_creates_pending_order_when_no_card(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => null,
            'stripe_payment_method_id' => null,
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);
        AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => 1500,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $this->assertDatabaseHas('orders', [
            'attendance_id' => $attendance->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('order_payments', ['stripe_pi_id' => 'pi_checkout']);
    }

    public function test_destroy_attendance_addon_removes_when_not_billed(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);
        $addonType = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'context' => 'daycare']);
        $addon = AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/roster/attendances/{$attendance->id}/addons/{$addon->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('attendance_addons', ['id' => $addon->id]);
    }

    public function test_destroy_attendance_addon_409_when_already_billed(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now()->subHours(4),
            'checked_out_at' => now()->subHour(),
        ]);
        $addonType = AddonType::factory()->create(['tenant_id' => $this->tenant->id, 'context' => 'daycare']);
        $addon = AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => $addonType->price_cents,
        ]);

        // Simulate an add-on charge order already created for this attendance
        $addonOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'attendance_id' => $attendance->id,
            'type' => 'daycare',
            'status' => 'paid',
            'total_amount' => $addonType->price_cents / 100,
        ]);
        $addonOrder->payments()->create([
            'tenant_id' => $this->tenant->id,
            'amount_cents' => $addonType->price_cents,
            'type' => 'charge',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->staff);

        $response = $this->delete("/admin/roster/attendances/{$attendance->id}/addons/{$addon->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('attendance_addons', ['id' => $addon->id]);
    }

    public function test_inactive_dog_cannot_be_checked_in(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 5,
            'status' => 'inactive',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('dog_id');
        $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
    }

    public function test_suspended_dog_cannot_be_checked_in(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 5,
            'status' => 'suspended',
        ]);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkin', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('dog_id');
        $this->assertDatabaseMissing('attendances', ['dog_id' => $dog->id]);
    }

    public function test_checkin_records_first_checkin_event_once(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dogA = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);
        $dogB = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 5]);

        $this->actingAs($this->staff);

        $this->post('/admin/roster/checkin', ['dog_id' => $dogA->id]);
        $this->post('/admin/roster/checkin', ['dog_id' => $dogB->id]);

        $this->assertDatabaseHas('tenant_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'first_checkin',
        ]);
        $this->assertDatabaseCount('tenant_events', 1);
    }

    public function test_index_includes_checked_in_at_for_checked_in_dog(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $this->actingAs($this->staff);

        $this->get('/admin/roster')->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->has('roster', 1)
            ->where('roster.0.attendance_state', 'checked_in')
            ->whereNot('roster.0.checked_in_at', null)
        );
    }

    public function test_index_checked_in_at_is_null_for_dog_not_yet_checked_in(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        $this->actingAs($this->staff);

        $this->get('/admin/roster')->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->has('roster', 1)
            ->where('roster.0.attendance_state', 'not_in')
            ->where('roster.0.checked_in_at', null)
        );
    }

    public function test_index_unlimited_pass_active_true_when_pass_not_expired(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 0,
            'unlimited_pass_expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($this->staff);

        $this->get('/admin/roster')->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->where('roster.0.unlimited_pass_active', true)
        );
    }

    public function test_checkout_cancels_and_recreates_pi_when_authorized_pi_already_requires_capture(): void
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_recap',
            'stripe_payment_method_id' => 'pm_recap',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 0]);
        $this->tenant->update(['stripe_account_id' => 'acct_test', 'platform_fee_pct' => 5.0]);

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'dog_id' => $dog->id,
            'checked_in_by' => $this->staff->id,
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ]);

        $authorizedOrder = Order::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'attendance_id' => $attendance->id,
            'status' => 'authorized',
            'total_amount' => '20.00',
            'subtotal_cents' => 2000,
            'tax_amount_cents' => 0,
            'platform_fee_pct' => 5.0,
        ]);
        $authorizedOrder->lineItems()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Day Pack',
            'quantity' => 1,
            'unit_price_cents' => 2000,
            'sort_order' => 0,
        ]);
        $authorizedOrder->payments()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_pi_id' => 'pi_stale',
            'amount_cents' => 2000,
            'type' => 'full',
            'status' => 'authorized',
        ]);

        $addonType = AddonType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'daycare',
            'price_cents' => 1500,
        ]);
        AttendanceAddon::create([
            'attendance_id' => $attendance->id,
            'addon_type_id' => $addonType->id,
            'quantity' => 1,
            'unit_price_cents' => 1500,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        // Retrieval returns the PI is already confirmed and awaiting capture
        $stripe->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_stale', 'acct_test')
            ->andReturn((object) ['id' => 'pi_stale', 'status' => 'requires_capture']);
        // Cancel the stale PI
        $stripe->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with('pi_stale', 'acct_test')
            ->andReturn((object) ['id' => 'pi_stale', 'status' => 'canceled']);
        // Create a fresh combined PI
        $stripe->shouldReceive('createPaymentIntent')
            ->once()
            ->withArgs(fn ($amount) => $amount === 3500)
            ->andReturn((object) ['id' => 'pi_combined', 'status' => 'succeeded']);
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($this->staff);

        $response = $this->post('/admin/roster/checkout', ['dog_id' => $dog->id]);

        $response->assertRedirect();
        $this->assertNotNull($attendance->fresh()->checked_out_at);

        $authorizedOrder->refresh();
        $this->assertSame(\App\Enums\OrderStatus::Paid, $authorizedOrder->status);
        $this->assertSame(3500, $authorizedOrder->subtotal_cents);

        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_combined',
            'amount_cents' => 3500,
            'status' => 'paid',
        ]);
    }

    public function test_index_unlimited_pass_active_false_when_no_pass(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Dog::factory()->forCustomer($customer)->create([
            'credit_balance' => 5,
            'unlimited_pass_expires_at' => null,
        ]);

        $this->actingAs($this->staff);

        $this->get('/admin/roster')->assertInertia(fn ($page) => $page
            ->component('Admin/Roster/Index')
            ->where('roster.0.unlimited_pass_active', false)
        );
    }
}
