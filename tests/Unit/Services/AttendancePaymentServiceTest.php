<?php

namespace Tests\Unit\Services;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\AttendancePaymentService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AttendancePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_authorized_confirms_then_captures_payment_intent(): void
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_test']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->forCustomer($customer)->create(['credit_balance' => 3]);

        $staff = \App\Models\User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
        $attendance = Attendance::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_id' => $dog->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staff->id,
            'checked_out_at' => now(),
        ]);

        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'attendance_id' => $attendance->id,
            'status' => 'authorized',
        ]);

        $order->payments()->create([
            'tenant_id' => $tenant->id,
            'stripe_pi_id' => 'pi_hold_test',
            'amount_cents' => 2000,
            'type' => 'full',
            'status' => 'authorized',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_hold_test', 'acct_test')
            ->andReturn((object) ['id' => 'pi_hold_test', 'status' => 'requires_capture']);
        $stripe->shouldReceive('capturePaymentIntent')
            ->once()
            ->with('pi_hold_test', 'acct_test')
            ->andReturn((object) ['id' => 'pi_hold_test', 'status' => 'succeeded']);
        $this->app->instance(StripeService::class, $stripe);

        $service = app(AttendancePaymentService::class);
        $service->captureAuthorized($attendance);

        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_hold_test',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }
}
