<?php

namespace Tests\Feature\Observers;

use App\Jobs\SyncPlatformPlanToStripe;
use App\Models\PlatformPlan;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformPlanObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_plan_without_stripe_id_dispatches_sync_job(): void
    {
        $billing = $this->mock(StripeBillingService::class);
        $billing->shouldReceive('createPlatformProduct')->once()->andReturn('prod_new');
        $billing->shouldReceive('createPlatformPrice')->twice()->andReturn('price_monthly', 'price_annual');

        PlatformPlan::factory()->create(['stripe_product_id' => null]);
    }

    public function test_creating_plan_with_existing_stripe_id_does_not_dispatch(): void
    {
        Queue::fake();

        PlatformPlan::factory()->create(['stripe_product_id' => 'prod_existing']);

        Queue::assertNotPushed(SyncPlatformPlanToStripe::class);
    }

    public function test_updating_monthly_price_dispatches_sync_job(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id'   => 'prod_abc',
            'monthly_price_cents' => 4900,
        ]);

        Queue::fake();

        $plan->update(['monthly_price_cents' => 5900]);

        Queue::assertPushed(SyncPlatformPlanToStripe::class);
    }

    public function test_updating_annual_price_dispatches_sync_job(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id'  => 'prod_abc',
            'annual_price_cents' => 47040,
        ]);

        Queue::fake();

        $plan->update(['annual_price_cents' => 57040]);

        Queue::assertPushed(SyncPlatformPlanToStripe::class);
    }

    public function test_updating_name_dispatches_sync_job(): void
    {
        $plan = PlatformPlan::factory()->create(['stripe_product_id' => 'prod_abc']);

        Queue::fake();

        $plan->update(['name' => 'Starter Plus']);

        Queue::assertPushed(SyncPlatformPlanToStripe::class);
    }

    public function test_updating_unrelated_field_does_not_dispatch(): void
    {
        $plan = PlatformPlan::factory()->create([
            'stripe_product_id' => 'prod_abc',
            'is_active'         => true,
        ]);

        Queue::fake();

        $plan->update(['is_active' => false]);

        Queue::assertNotPushed(SyncPlatformPlanToStripe::class);
    }

    public function test_update_with_null_stripe_id_dispatches_sync_job(): void
    {
        $plan = PlatformPlan::factory()->create(['stripe_product_id' => null]);

        Queue::fake();

        $plan->update(['description' => 'Updated description']);

        Queue::assertPushed(SyncPlatformPlanToStripe::class);
    }
}
