<?php

namespace Tests\Feature\Portal;

use App\Models\Customer;
use App\Models\Dog;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\InteractsWithJwt;

class PromotionControllerTest extends TestCase
{
    use InteractsWithJwt, RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Dog $dog;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpJwt();

        $this->tenant = Tenant::factory()->create([
            'slug'               => 'promotest',
            'status'             => 'active',
            'stripe_account_id'  => 'acct_promo',
            'stripe_onboarded_at' => now(),
        ]);
        URL::forceRootUrl('http://promotest.pawpass.com');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->user = User::factory()->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'role'        => 'customer',
        ]);

        $this->dog = Dog::factory()->forCustomer($this->customer)->withCredits(0)->create();

        $this->package = Package::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'type'         => 'one_time',
            'price'        => '50.00',
            'credit_count' => 10,
            'is_active'    => true,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->jwtFor($this->user)];
    }

    // -------------------------------------------------------------------------
    // POST /api/portal/v1/promotions/check
    // -------------------------------------------------------------------------

    public function test_promo_check_returns_valid_discount(): void
    {
        Promotion::factory()->percentage(20)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'SAVE20',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/promotions/check', [
                'code'       => 'SAVE20',
                'package_id' => $this->package->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.discount_cents', 1000)
            ->assertJsonPath('data.final_cents', 4000);
    }

    public function test_promo_check_returns_invalid_for_bad_code(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/promotions/check', [
                'code'       => 'BADCODE',
                'package_id' => $this->package->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.discount_cents', 0);
    }

    public function test_promo_check_is_case_insensitive(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'UPPER10',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/portal/v1/promotions/check', [
                'code'       => 'upper10',
                'package_id' => $this->package->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true);
    }

    // -------------------------------------------------------------------------
    // POST /api/portal/v1/orders with promo_code
    // -------------------------------------------------------------------------

    public function test_order_with_valid_promo_applies_discount(): void
    {
        Promotion::factory()->percentage(10)->create([
            'tenant_id' => $this->tenant->id,
            'code'      => 'TENOFF',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->zeroOrMoreTimes()->andReturn((object) ['id' => 'cus_promo']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_promo', 'client_secret' => 'secret_promo']);
        });

        $response = $this->withHeaders(array_merge($this->authHeaders(), ['Idempotency-Key' => 'promo-order-1']))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids'    => [$this->dog->id],
                'promo_code' => 'TENOFF',
            ]);

        $response->assertStatus(201);

        // Redemption recorded
        $this->assertDatabaseHas('promotion_redemptions', [
            'customer_id'           => $this->customer->id,
            'discount_amount_cents' => 500, // 10% of 5000
            'original_amount_cents' => 5000,
        ]);

        // Order total reflects discount
        $this->assertDatabaseHas('orders', [
            'customer_id'  => $this->customer->id,
            'total_amount' => '45.00', // 50.00 - 5.00
        ]);
    }

    public function test_order_with_invalid_promo_returns_422(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->zeroOrMoreTimes()->andReturn((object) ['id' => 'cus_test']);
        });

        $response = $this->withHeaders(array_merge($this->authHeaders(), ['Idempotency-Key' => 'promo-bad-1']))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids'    => [$this->dog->id],
                'promo_code' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_PROMO_CODE');
    }

    public function test_order_without_promo_code_proceeds_normally(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCustomer')->zeroOrMoreTimes()->andReturn((object) ['id' => 'cus_nopromo']);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn((object) ['id' => 'pi_nopromo', 'client_secret' => 'secret_nopromo']);
        });

        $response = $this->withHeaders(array_merge($this->authHeaders(), ['Idempotency-Key' => 'no-promo-1']))
            ->postJson('/api/portal/v1/orders', [
                'package_id' => $this->package->id,
                'dog_ids'    => [$this->dog->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }
}
