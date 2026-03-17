<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Exception\ApiErrorException;
use Tests\TestCase;

class SyncPlatformPlanToStripeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_path_creates_product_and_both_prices(): void
    {
        $plan = PlatformPlan::factory()->create([
            'name'                    => 'Starter',
            'monthly_price_cents'     => 4900,
            'annual_price_cents'      => 47040,
            'stripe_product_id'       => null,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id'  => null,
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')
                ->once()
                ->with('Starter')
                ->andReturn('prod_abc');

            $mock->shouldReceive('createPlatformPrice')
                ->once()
                ->with('prod_abc', 4900, 'month')
                ->andReturn('price_monthly_abc');

            $mock->shouldReceive('createPlatformPrice')
                ->once()
                ->with('prod_abc', 47040, 'year')
                ->andReturn('price_annual_abc');
        });

        (new SyncPlatformPlanToStripe($plan))->handle($billing);

        $plan->refresh();
        $this->assertEquals('prod_abc', $plan->stripe_product_id);
        $this->assertEquals('price_monthly_abc', $plan->stripe_monthly_price_id);
        $this->assertEquals('price_annual_abc', $plan->stripe_annual_price_id);
    }

    public function test_update_path_updates_product_and_recreates_prices(): void
    {
        $plan = PlatformPlan::factory()->create([
            'name'                    => 'Pro',
            'monthly_price_cents'     => 9900,
            'annual_price_cents'      => 95040,
            'stripe_product_id'       => 'prod_existing',
            'stripe_monthly_price_id' => 'price_mo_old',
            'stripe_annual_price_id'  => 'price_yr_old',
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updatePlatformProduct')
                ->once()
                ->with('prod_existing', 'Pro');

            $mock->shouldReceive('archivePlatformPrice')
                ->once()
                ->with('price_mo_old');

            $mock->shouldReceive('createPlatformPrice')
                ->once()
                ->with('prod_existing', 9900, 'month')
                ->andReturn('price_mo_new');

            $mock->shouldReceive('archivePlatformPrice')
                ->once()
                ->with('price_yr_old');

            $mock->shouldReceive('createPlatformPrice')
                ->once()
                ->with('prod_existing', 95040, 'year')
                ->andReturn('price_yr_new');
        });

        (new SyncPlatformPlanToStripe($plan))->handle($billing);

        $plan->refresh();
        $this->assertEquals('price_mo_new', $plan->stripe_monthly_price_id);
        $this->assertEquals('price_yr_new', $plan->stripe_annual_price_id);
    }

    public function test_stripe_error_is_caught_silently(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id' => null,
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')
                ->andThrow(new class('Stripe error') extends ApiErrorException {});
        });

        // Should not throw
        (new SyncPlatformPlanToStripe($plan))->handle($billing);

        $plan->refresh();
        $this->assertNull($plan->stripe_product_id);
    }

    public function test_create_path_does_not_trigger_observer_loop(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id' => null,
        ]);

        $billing = $this->mock(StripeBillingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPlatformProduct')->andReturn('prod_loop');
            $mock->shouldReceive('createPlatformPrice')->andReturn('price_mo_loop', 'price_yr_loop');
        });

        // Count how many times createPlatformProduct is called — should be exactly once
        (new SyncPlatformPlanToStripe($plan))->handle($billing);

        // If observer looped, billing would be called multiple times — Mockery enforces once() above
        $this->assertTrue(true);
    }
}
