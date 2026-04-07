<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class WebSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug'              => 'websub',
            'status'            => 'active',
            'plan'              => 'starter',
            'stripe_account_id' => 'acct_websub',
            'platform_fee_pct'  => '5.00',
        ]);
        URL::forceRootUrl('http://websub.pawpass.com');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Sub Canceller',
            'email'     => 'canceller@example.com',
        ]);
        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);
        $this->customer->update(['user_id' => $this->user->id]);
        $this->dog = Dog::factory()->forCustomer($this->customer)->create();

        $this->package = Package::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'type'           => 'subscription',
            'is_active'      => true,
            'stripe_price_id' => 'price_websub',
        ]);
    }

    public function test_cancel_active_subscription_via_web_sets_cancelled_at(): void
    {
        $subscription = Subscription::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'customer_id'  => $this->customer->id,
            'package_id'   => $this->package->id,
            'dog_id'       => $this->dog->id,
            'status'       => 'active',
            'stripe_sub_id' => 'sub_web_cancel',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelSubscriptionAtPeriodEnd')
                ->once()
                ->with('sub_web_cancel', 'acct_websub')
                ->andReturn((object) ['id' => 'sub_web_cancel', 'cancel_at_period_end' => true]);
        });

        $response = $this->actingAs($this->user)
            ->post("/my/dogs/{$this->dog->id}/subscriptions/{$subscription->id}/cancel");

        $response->assertRedirect(route('portal.dogs.show', $this->dog->id));
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_cancel_redirects_non_owner_with_403(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'role'        => 'customer',
        ]);

        $subscription = Subscription::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id'  => $this->package->id,
            'dog_id'      => $this->dog->id,
            'status'      => 'active',
        ]);

        $response = $this->actingAs($otherUser)
            ->post("/my/dogs/{$this->dog->id}/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(403);
        $this->assertNull($subscription->fresh()->cancelled_at);
    }

    public function test_cancel_non_active_subscription_redirects_back_with_error(): void
    {
        $subscription = Subscription::factory()->cancelled()->create([
            'tenant_id'  => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'package_id' => $this->package->id,
            'dog_id'     => $this->dog->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/my/dogs/{$this->dog->id}/subscriptions/{$subscription->id}/cancel");

        $response->assertRedirect(route('portal.dogs.show', $this->dog->id));
        $response->assertSessionHas('error');
    }
}
