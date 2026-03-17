<?php

namespace Tests\Feature\Public;

use App\Models\PlatformPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_plans_ordered_by_sort_order(): void
    {
        PlatformPlan::factory()->create(['slug' => 'business', 'sort_order' => 3, 'is_active' => true]);
        PlatformPlan::factory()->create(['slug' => 'starter', 'sort_order' => 1, 'is_active' => true]);
        PlatformPlan::factory()->create(['slug' => 'pro', 'sort_order' => 2, 'is_active' => true]);

        $response = $this->getJson('/api/public/v1/plans');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals('starter', $response->json('data.0.slug'));
        $this->assertEquals('pro', $response->json('data.1.slug'));
        $this->assertEquals('business', $response->json('data.2.slug'));
    }

    public function test_does_not_return_inactive_plans(): void
    {
        PlatformPlan::factory()->create(['slug' => 'starter', 'is_active' => true]);
        PlatformPlan::factory()->create(['slug' => 'pro', 'is_active' => false]);

        $response = $this->getJson('/api/public/v1/plans');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('starter', $response->json('data.0.slug'));
    }

    public function test_plan_response_does_not_include_stripe_ids(): void
    {
        PlatformPlan::factory()->synced()->create(['slug' => 'starter']);

        $response = $this->getJson('/api/public/v1/plans');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('stripe_product_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('stripe_monthly_price_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('stripe_annual_price_id', $response->json('data.0'));
    }
}
