<?php

namespace Tests\Feature\Web\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\PlatformPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanFeatureCache;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Pennant\Feature;
use Mockery\MockInterface;
use Tests\TestCase;

class PurchaseRecurringCardOnFileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'              => 'cardtest',
            'status'            => 'active',
            'plan'              => 'starter',
            'stripe_account_id' => 'acct_cardtest',
            'platform_fee_pct'  => '5.00',
        ]);
        URL::forceRootUrl('http://cardtest.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Card Customer',
            'email'     => 'card@example.com',
        ]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();
    }

    // --- Feature flag tests ---

    public function test_index_returns_recurring_checkout_enabled_false_when_flag_inactive(): void
    {
        // Ensure no plan has recurring_checkout feature
        Feature::flushCache();

        $this->actingAs($this->user);
        $response = $this->get('/my/purchase');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Purchase')
            ->where('recurring_checkout_enabled', false)
        );
    }

    public function test_index_returns_recurring_checkout_enabled_true_when_flag_active(): void
    {
        // Create a starter plan with recurring_checkout feature
        PlatformPlan::factory()->create([
            'slug'     => 'starter',
            'features' => ['recurring_checkout'],
        ]);

        // Flush both Pennant and PlanFeatureCache
        Feature::flushCache();
        app()->forgetInstance(PlanFeatureCache::class);

        $this->actingAs($this->user);
        $response = $this->get('/my/purchase');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Purchase')
            ->where('recurring_checkout_enabled', true)
        );
    }

    public function test_index_returns_saved_card_null_when_no_pm_on_customer(): void
    {
        $this->actingAs($this->user);
        $response = $this->get('/my/purchase');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Purchase')
            ->where('saved_card', null)
        );
    }

    public function test_index_returns_saved_card_when_customer_has_pm(): void
    {
        $this->customer->update([
            'stripe_payment_method_id' => 'pm_saved',
            'stripe_pm_last4'          => '4242',
            'stripe_pm_brand'          => 'visa',
        ]);

        $this->actingAs($this->user);
        $response = $this->get('/my/purchase');

        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Purchase')
            ->where('saved_card', ['last4' => '4242', 'brand' => 'visa'])
        );
    }

    // --- store() fast path (card on file + recurring) ---

    public function test_store_recurring_with_card_on_file_creates_subscription_directly(): void
    {
        $this->customer->update([
            'stripe_customer_id'       => 'cus_onfile',
            'stripe_payment_method_id' => 'pm_saved123',
        ]);

        $package = Package::factory()->create([
            'tenant_id'               => $this->tenant->id,
            'type'                    => 'one_time',
            'price'                   => '30.00',
            'is_active'               => true,
            'is_recurring_enabled'    => true,
            'stripe_price_id_recurring' => 'price_rec123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSubscription')
                ->once()
                ->with(
                    'cus_onfile',
                    'price_rec123',
                    'pm_saved123',
                    'acct_cardtest',
                    \Mockery::type('float'),
                    \Mockery::type('array'),
                )
                ->andReturn((object) [
                    'id'                   => 'sub_fast1',
                    'current_period_start' => now()->timestamp,
                    'current_period_end'   => now()->addDays(30)->timestamp,
                ]);

            $mock->shouldNotReceive('createSetupIntent');
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
            'save_card'    => false,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['fast' => true]);
        $response->assertJsonStructure(['subscription_id', 'fast']);

        $this->assertDatabaseHas('subscriptions', ['stripe_sub_id' => 'sub_fast1']);
    }

    public function test_store_recurring_without_card_on_file_creates_setup_intent(): void
    {
        $this->customer->update([
            'stripe_customer_id'       => 'cus_nopm',
            'stripe_payment_method_id' => null,
        ]);

        $package = Package::factory()->create([
            'tenant_id'               => $this->tenant->id,
            'type'                    => 'one_time',
            'price'                   => '30.00',
            'is_active'               => true,
            'is_recurring_enabled'    => true,
            'stripe_price_id_recurring' => 'price_rec456',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createSubscription');

            $mock->shouldReceive('createSetupIntent')
                ->once()
                ->andReturn((object) ['client_secret' => 'seti_secret']);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase', [
            'package_id'   => $package->id,
            'dog_ids'      => [$this->dog->id],
            'billing_mode' => 'recurring',
            'save_card'    => false,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['client_secret', 'subscription_id']);
    }

    // --- confirm() save card ---

    public function test_confirm_saves_pm_when_save_card_true(): void
    {
        $this->customer->update(['stripe_customer_id' => 'cus_confirm1']);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $order = \App\Models\Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $package->id,
            'status'       => 'pending',
            'total_amount' => '50.00',
            'stripe_pi_id' => 'pi_save1',
        ]);

        $order->orderDogs()->create([
            'dog_id'         => $this->dog->id,
            'credits_issued' => 0,
        ]);

        $fakePm = (object) [
            'id'   => 'pm_saved_new',
            'card' => (object) ['last4' => '4242', 'brand' => 'visa'],
        ];

        $this->mock(StripeService::class, function (MockInterface $mock) use ($fakePm) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn((object) [
                    'id'             => 'pi_save1',
                    'status'         => 'succeeded',
                    'payment_method' => 'pm_saved_new',
                ]);

            $mock->shouldReceive('retrievePaymentMethod')
                ->once()
                ->with('pm_saved_new', \Mockery::any())
                ->andReturn($fakePm);
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase/confirm', [
            'payment_intent_id' => 'pi_save1',
            'save_card'         => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'paid']);

        $this->assertDatabaseHas('customers', [
            'id'                       => $this->customer->id,
            'stripe_payment_method_id' => 'pm_saved_new',
            'stripe_pm_last4'          => '4242',
            'stripe_pm_brand'          => 'visa',
        ]);
    }

    public function test_confirm_does_not_save_pm_when_save_card_false(): void
    {
        $this->customer->update(['stripe_customer_id' => 'cus_confirm2']);

        $package = Package::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type'      => 'one_time',
            'price'     => '50.00',
            'is_active' => true,
        ]);

        $order = \App\Models\Order::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $package->id,
            'status'       => 'pending',
            'total_amount' => '50.00',
            'stripe_pi_id' => 'pi_nosave',
        ]);

        $order->orderDogs()->create([
            'dog_id'         => $this->dog->id,
            'credits_issued' => 0,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn((object) [
                    'id'             => 'pi_nosave',
                    'status'         => 'succeeded',
                    'payment_method' => 'pm_ignored',
                ]);

            $mock->shouldNotReceive('retrievePaymentMethod');
        });

        $this->actingAs($this->user);

        $response = $this->postJson('/my/purchase/confirm', [
            'payment_intent_id' => 'pi_nosave',
            'save_card'         => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id'                       => $this->customer->id,
            'stripe_payment_method_id' => null,
        ]);
    }
}
