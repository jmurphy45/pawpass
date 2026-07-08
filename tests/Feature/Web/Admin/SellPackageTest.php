<?php

namespace Tests\Feature\Web\Admin;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Stripe\Exception\CardException;
use Tests\TestCase;

class SellPackageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Customer $customer;

    private Dog $dog;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformPlan::factory()->create(['slug' => 'starter', 'features' => ['add_customers']]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'testco',
            'status' => 'active',
            'plan' => 'starter',
            'stripe_account_id' => 'acct_test',
        ]);
        URL::forceRootUrl('http://testco.pawpass.com');

        $this->staff = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_test',
            'stripe_payment_method_id' => 'pm_test',
        ]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->create(['credit_balance' => 0]);

        $this->package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'one_time',
            'price' => '50.00',
            'credit_count' => 10,
            'dog_limit' => 1,
        ]);
    }

    public function test_sell_package_charges_saved_card_and_issues_credits(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_sell_test', 'status' => 'succeeded']);
        });

        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $response->assertRedirect(route('admin.customers.show', $this->customer));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'status' => OrderStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('order_line_items', [
            'description' => $this->package->name,
        ]);
        $this->dog->refresh();
        $this->assertSame(10, $this->dog->credit_balance);
    }

    public function test_sell_package_issues_unlimited_pass(): void
    {
        $unlimitedPackage = Package::factory()->unlimited(30)->create(['tenant_id' => $this->tenant->id]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_sell_unlimited', 'status' => 'succeeded']);
        });

        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $unlimitedPackage->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $response->assertRedirect(route('admin.customers.show', $this->customer));
        $this->dog->refresh();
        $this->assertNotNull($this->dog->unlimited_pass_expires_at);
    }

    public function test_sell_package_fails_with_no_saved_card(): void
    {
        $this->customer->update(['stripe_payment_method_id' => null]);

        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('orders', ['customer_id' => $this->customer->id]);
        $this->dog->refresh();
        $this->assertSame(0, $this->dog->credit_balance);
    }

    public function test_sell_package_fails_when_stripe_declines(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andThrow(new CardException('Your card was declined.'));
        });

        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'status' => OrderStatus::Canceled->value,
        ]);
        $this->dog->refresh();
        $this->assertSame(0, $this->dog->credit_balance);
    }

    public function test_sell_package_requires_at_least_one_dog(): void
    {
        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [],
        ]);

        $response->assertSessionHasErrors('dog_ids');
    }

    public function test_sell_package_rejects_dog_not_belonging_to_customer(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherDog = Dog::factory()->forCustomer($otherCustomer)->create();

        $this->actingAs($this->staff);

        $response = $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [$otherDog->id],
        ]);

        $response->assertStatus(404);
    }

    public function test_sell_package_webhook_does_not_double_issue_credits(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_sell_idempotent', 'status' => 'succeeded']);
        });

        $this->actingAs($this->staff);

        $this->post("/admin/customers/{$this->customer->id}/sell-package", [
            'package_id' => $this->package->id,
            'dog_ids' => [$this->dog->id],
        ]);

        $this->dog->refresh();
        $this->assertSame(10, $this->dog->credit_balance);

        $order = \App\Models\Order::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertSame(OrderStatus::Paid, $order->status);

        // Simulate the same PaymentIntent's webhook firing after the synchronous flow
        // already marked the order Paid — this should be a pure no-op.
        app()->instance('current.tenant.id', $this->tenant->id);
        $webhookController = app(\App\Http\Controllers\Webhooks\StripeWebhookController::class);
        $pi = (object) [
            'id' => 'pi_sell_idempotent',
            'metadata' => (object) ['order_id' => $order->id],
            'application_fee_amount' => 0,
            'automatic_tax' => null,
        ];
        $reflection = new \ReflectionMethod($webhookController, 'handlePaymentIntentSucceeded');
        $reflection->setAccessible(true);
        $reflection->invoke($webhookController, $pi);

        $this->dog->refresh();
        $this->assertSame(10, $this->dog->credit_balance, 'Credits must not be issued twice.');
    }
}
