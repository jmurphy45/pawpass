<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\AutoReplenishService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Stripe\Exception\CardException;
use Tests\TestCase;

class AutoReplenishServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeDog(array $dogOverrides = [], array $customerOverrides = []): Dog
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_test']);
        $customer = Customer::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
            'stripe_pm_last4' => '4242',
            'stripe_pm_brand' => 'visa',
        ], $customerOverrides));
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);

        return Dog::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'credit_balance' => 0,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ], $dogOverrides));
    }

    public function test_skips_when_auto_replenish_disabled(): void
    {
        $stripe = $this->mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');

        $dog = $this->makeDog(['auto_replenish_enabled' => false]);

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_skips_when_no_saved_payment_method(): void
    {
        $stripe = $this->mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');

        $dog = $this->makeDog([], ['stripe_payment_method_id' => null]);

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_skips_when_no_auto_replenish_package(): void
    {
        $stripe = $this->mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');

        $dog = $this->makeDog(['auto_replenish_package_id' => null]);

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_creates_order_and_off_session_payment_intent(): void
    {
        $dog = $this->makeDog();
        $customer = $dog->customer;
        $package = Package::find($dog->auto_replenish_package_id);
        $tenant = $dog->tenant;

        $fakeIntent = (object) ['id' => 'pi_auto_test', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->withArgs(function ($amount, $currency, $accountId, $fee, $metadata, $customerId, $confirm, $offSession, $pmId) {
                    return $confirm === true && $offSession === true;
                })
                ->andReturn($fakeIntent);
        });

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
            'stripe_pi_id' => 'pi_auto_test',
        ]);
    }

    public function test_handles_stripe_card_exception_and_notifies(): void
    {
        $dog = $this->makeDog();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andThrow(new \Exception('Card declined'));
        });

        $notif = $this->mock(NotificationService::class);
        $notif->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn ($type) => $type === 'auto_replenish.failed');

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);
    }

    public function test_skips_when_tenant_has_no_stripe_account(): void
    {
        $stripe = $this->mock(StripeService::class);
        $stripe->shouldNotReceive('createPaymentIntent');

        $tenant = Tenant::factory()->create(['stripe_account_id' => null]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'stripe_payment_method_id' => 'pm_test',
        ]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'auto_replenish_enabled' => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseCount('orders', 0);
    }
}
