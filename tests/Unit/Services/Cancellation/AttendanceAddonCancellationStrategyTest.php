<?php

namespace Tests\Unit\Services\Cancellation;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cancellation\Strategies\AttendanceAddonCancellationStrategy;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class AttendanceAddonCancellationStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    private function makeAddonOrder(string $stripeAccountId = 'acct_addon'): array
    {
        $tenant   = Tenant::factory()->create(['stripe_account_id' => $stripeAccountId]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
        $dog   = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

        $attendance = Attendance::factory()->create([
            'tenant_id'      => $tenant->id,
            'dog_id'         => $dog->id,
            'checked_in_by'  => $staff->id,
        ]);

        $order = Order::factory()->create([
            'tenant_id'     => $tenant->id,
            'customer_id'   => $customer->id,
            'package_id'    => $package->id,
            'type'          => OrderType::Daycare,
            'status'        => 'pending',
            'attendance_id' => $attendance->id,
        ]);

        return compact('tenant', 'order');
    }

    public function test_supports_returns_true_when_attendance_id_is_set(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new AttendanceAddonCancellationStrategy($stripe);

        $order = Order::factory()->make([
            'type'          => OrderType::Daycare,
            'attendance_id' => (string) Str::ulid(),
        ]);

        $this->assertTrue($strategy->supports($order));
    }

    public function test_supports_returns_false_when_attendance_id_is_null(): void
    {
        $stripe   = $this->mock(StripeService::class)->shouldIgnoreMissing();
        $strategy = new AttendanceAddonCancellationStrategy($stripe);

        $order = Order::factory()->make(['type' => OrderType::Daycare, 'attendance_id' => null]);

        $this->assertFalse($strategy->supports($order));
    }

    public function test_cancel_cancels_stripe_charge_pi_and_marks_canceled(): void
    {
        ['order' => $order] = $this->makeAddonOrder();

        $payment = OrderPayment::factory()->forOrder($order)->create([
            'type'         => PaymentType::Charge,
            'status'       => 'pending',
            'stripe_pi_id' => 'pi_addon_charge',
            'paid_at'      => null,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelPaymentIntent')
                ->once()
                ->with('pi_addon_charge', 'acct_addon');
        });

        $strategy = new AttendanceAddonCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_marks_canceled_locally_when_no_pi(): void
    {
        ['order' => $order] = $this->makeAddonOrder();

        $payment = OrderPayment::factory()->forOrder($order)->pending()->create([
            'type' => PaymentType::Charge,
        ]);

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new AttendanceAddonCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(PaymentStatus::Canceled, $payment->fresh()->status);
        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_cancel_transitions_order_to_canceled_with_no_payments(): void
    {
        ['order' => $order] = $this->makeAddonOrder();

        $stripe = $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('cancelPaymentIntent');
        });

        $strategy = new AttendanceAddonCancellationStrategy($stripe);
        $order->load(['payments', 'tenant']);
        $strategy->cancel($order);

        $this->assertEquals(OrderStatus::Canceled, $order->fresh()->status);
    }
}
