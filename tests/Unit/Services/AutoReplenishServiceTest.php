<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\AutoReplenishService;
use App\Services\DogCreditService;
use App\Services\NotificationService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Mockery\MockInterface;
use Stripe\Exception\CardException;
use Tests\TestCase;

class AutoReplenishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // prevent SyncPackageToStripe from running during package creation
    }

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
                ->withArgs(function ($amount, $currency, $accountId, $fee, $metadata, $customerId, $confirm, $offSession, $pmId, $pmTypes) {
                    return $confirm === true && $offSession === true && $pmTypes === ['card'];
                })
                ->andReturn($fakeIntent);
        });

        $service = app(AutoReplenishService::class);
        $service->trigger($dog);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'package_id'  => $package->id,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'stripe_pi_id' => 'pi_auto_test',
        ]);
    }

    public function test_handles_stripe_card_exception_and_notifies(): void
    {
        $dog = $this->makeDog();
        $user = \App\Models\User::factory()->create(['tenant_id' => $dog->tenant_id]);
        $dog->customer->update(['user_id' => $user->id]);

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

    // ── triggerSync ───────────────────────────────────────────────────────────

    public function test_trigger_sync_returns_true_on_success(): void
    {
        $dog = $this->makeDog();
        $customer = $dog->customer;
        $package = Package::find($dog->auto_replenish_package_id);

        $fakeIntent = (object) ['id' => 'pi_sync_ok', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
            $mock->shouldNotReceive('calculateTax');
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertTrue($result);
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'status' => 'paid']);
    }

    public function test_trigger_sync_returns_false_when_pi_status_not_succeeded(): void
    {
        $dog = $this->makeDog();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_sync_fail', 'status' => 'requires_action']);
            $mock->shouldNotReceive('calculateTax');
        });

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertFalse($result);
        $this->assertDatabaseHas('orders', ['status' => 'failed']);
    }

    public function test_trigger_sync_returns_false_on_stripe_exception(): void
    {
        $dog = $this->makeDog();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andThrow(new \Exception('card declined'));
            $mock->shouldNotReceive('calculateTax');
        });

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertFalse($result);
        $this->assertDatabaseHas('orders', ['status' => 'failed']);
    }

    public function test_trigger_sync_applies_tax_when_flag_active_and_billing_address_set(): void
    {
        $dog = $this->makeDog();
        $tenant = $dog->tenant;
        $tenant->update(['billing_address' => ['postal_code' => '90210', 'country' => 'US']]);

        Feature::for($tenant)->activate('tax_daycare_orders');

        $fakeCalc   = (object) ['id' => 'txc_sync_test', 'tax_amount_exclusive' => 150];
        $fakeIntent = (object) ['id' => 'pi_sync_tax', 'status' => 'succeeded'];

        $capturedAmount = null;
        $capturedMeta   = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeCalc, $fakeIntent, &$capturedAmount, &$capturedMeta) {
            $mock->shouldReceive('calculateTax')->once()->andReturn($fakeCalc);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function ($amount, $currency, $accountId, $fee, $metadata) use ($fakeIntent, &$capturedAmount, &$capturedMeta) {
                    $capturedAmount = $amount;
                    $capturedMeta   = $metadata;
                    return $fakeIntent;
                });
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $package = Package::find($dog->auto_replenish_package_id);
        $subtotal = (int) round((float) $package->price * 100);

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertTrue($result);
        $this->assertEquals($subtotal + 150, $capturedAmount);
        $this->assertEquals('txc_sync_test', $capturedMeta['tax_calculation_id'] ?? null);
        $this->assertDatabaseHas('orders', ['tax_amount_cents' => 150, 'stripe_tax_calc_id' => 'txc_sync_test']);
    }

    public function test_trigger_sync_skips_tax_when_flag_inactive(): void
    {
        $dog = $this->makeDog();
        $dog->tenant->update(['billing_address' => ['postal_code' => '90210', 'country' => 'US']]);

        $fakeIntent = (object) ['id' => 'pi_sync_notax', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldNotReceive('calculateTax');
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertTrue($result);
        $this->assertDatabaseHas('orders', ['tax_amount_cents' => 0]);
    }

    public function test_trigger_sync_skips_tax_when_billing_address_missing(): void
    {
        $dog = $this->makeDog();
        $tenant = $dog->tenant;
        $tenant->update(['billing_address' => null]);

        Feature::for($tenant)->activate('tax_daycare_orders');

        $fakeIntent = (object) ['id' => 'pi_sync_noaddr', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldNotReceive('calculateTax');
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerSync($dog);

        $this->assertTrue($result);
    }

    // ── triggerForPackage ─────────────────────────────────────────────────────

    private function makeDogAndPackage(): array
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_test']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
        ]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'credit_balance' => 0,
        ]);

        return [$dog, $package];
    }

    public function test_trigger_for_package_returns_true_on_successful_charge(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();

        $fakeIntent = (object) ['id' => 'pi_tfp_ok', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
            $mock->shouldNotReceive('calculateTax');
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertTrue($result);
        $this->assertDatabaseHas('orders', ['customer_id' => $dog->customer->id, 'status' => 'paid']);
    }

    public function test_trigger_for_package_returns_false_when_pi_fails(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_tfp_fail', 'status' => 'requires_action']);
        });

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertFalse($result);
        $this->assertDatabaseHas('orders', ['status' => 'failed']);
    }

    public function test_trigger_for_package_returns_false_on_stripe_exception(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andThrow(new \Exception('card error'));
        });

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertFalse($result);
        $this->assertDatabaseHas('orders', ['status' => 'failed']);
    }

    public function test_trigger_for_package_returns_false_when_no_customer_payment_method(): void
    {
        $tenant = Tenant::factory()->create(['stripe_account_id' => 'acct_test']);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'stripe_payment_method_id' => null,
        ]);
        $package = Package::factory()->autoReplenish()->create(['tenant_id' => $tenant->id]);
        $dog = Dog::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

        $this->mock(StripeService::class)->shouldNotReceive('createPaymentIntent');

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertFalse($result);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_trigger_for_package_applies_tax_when_flag_active_and_billing_address_set(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();
        $tenant = $dog->tenant;
        $tenant->update(['billing_address' => ['postal_code' => '10001', 'country' => 'US']]);

        Feature::for($tenant)->activate('tax_daycare_orders');

        $fakeCalc   = (object) ['id' => 'txc_tfp_test', 'tax_amount_exclusive' => 200];
        $fakeIntent = (object) ['id' => 'pi_tfp_tax', 'status' => 'succeeded'];

        $capturedAmount = null;
        $capturedMeta   = null;

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeCalc, $fakeIntent, &$capturedAmount, &$capturedMeta) {
            $mock->shouldReceive('calculateTax')->once()->andReturn($fakeCalc);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function ($amount, $currency, $accountId, $fee, $metadata) use ($fakeIntent, &$capturedAmount, &$capturedMeta) {
                    $capturedAmount = $amount;
                    $capturedMeta   = $metadata;
                    return $fakeIntent;
                });
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $subtotal = (int) round((float) $package->price * 100);

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertTrue($result);
        $this->assertEquals($subtotal + 200, $capturedAmount);
        $this->assertEquals('txc_tfp_test', $capturedMeta['tax_calculation_id'] ?? null);
        $this->assertDatabaseHas('orders', ['tax_amount_cents' => 200, 'stripe_tax_calc_id' => 'txc_tfp_test']);
    }

    public function test_trigger_for_package_skips_tax_when_flag_inactive(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();
        $dog->tenant->update(['billing_address' => ['postal_code' => '10001', 'country' => 'US']]);

        $fakeIntent = (object) ['id' => 'pi_tfp_notax', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldNotReceive('calculateTax');
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertTrue($result);
        $this->assertDatabaseHas('orders', ['tax_amount_cents' => 0]);
    }

    public function test_trigger_for_package_skips_tax_when_billing_address_missing(): void
    {
        [$dog, $package] = $this->makeDogAndPackage();
        $tenant = $dog->tenant;
        $tenant->update(['billing_address' => null]);

        Feature::for($tenant)->activate('tax_daycare_orders');

        $fakeIntent = (object) ['id' => 'pi_tfp_noaddr', 'status' => 'succeeded'];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakeIntent) {
            $mock->shouldNotReceive('calculateTax');
            $mock->shouldReceive('createPaymentIntent')->once()->andReturn($fakeIntent);
        });

        $this->mock(DogCreditService::class)->shouldIgnoreMissing();

        $service = app(AutoReplenishService::class);
        $result = $service->triggerForPackage($dog, $package);

        $this->assertTrue($result);
    }
}
