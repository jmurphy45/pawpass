<?php

namespace Tests\Unit;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Feature::flushCache();
        parent::tearDown();
    }

    private function planFor(string $slug, array $features, int $staffLimit = 1): PlatformPlan
    {
        return PlatformPlan::factory()->create([
            'slug'        => $slug,
            'features'    => $features,
            'staff_limit' => $staffLimit,
        ]);
    }

    private function tenantWithPlan(string $planSlug): Tenant
    {
        return Tenant::factory()->create(['plan' => $planSlug, 'status' => 'active']);
    }

    public function test_free_plan_cannot_add_customers(): void
    {
        $this->planFor('free', []);
        $tenant = $this->tenantWithPlan('free');

        $this->assertFalse(Feature::for($tenant)->active('add_customers'));
    }

    public function test_starter_plan_can_add_customers(): void
    {
        $this->planFor('starter', ['add_customers', 'add_dogs', 'email_notifications', 'basic_reporting', 'customer_portal'], 5);
        $tenant = $this->tenantWithPlan('starter');

        $this->assertTrue(Feature::for($tenant)->active('add_customers'));
    }

    public function test_pro_plan_can_add_customers(): void
    {
        $this->planFor('pro', ['add_customers', 'sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertTrue(Feature::for($tenant)->active('add_customers'));
    }

    public function test_business_plan_can_add_customers(): void
    {
        $this->planFor('business', ['add_customers', 'white_label'], 999999);
        $tenant = $this->tenantWithPlan('business');

        $this->assertTrue(Feature::for($tenant)->active('add_customers'));
    }

    public function test_free_plan_cannot_use_sms(): void
    {
        $this->planFor('free', []);
        $tenant = $this->tenantWithPlan('free');

        $this->assertFalse(Feature::for($tenant)->active('sms_notifications'));
    }

    public function test_starter_plan_cannot_use_sms(): void
    {
        $this->planFor('starter', ['add_customers', 'email_notifications'], 5);
        $tenant = $this->tenantWithPlan('starter');

        $this->assertFalse(Feature::for($tenant)->active('sms_notifications'));
    }

    public function test_pro_plan_can_use_sms(): void
    {
        $this->planFor('pro', ['sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertTrue(Feature::for($tenant)->active('sms_notifications'));
    }

    public function test_free_plan_cannot_use_white_label(): void
    {
        $this->planFor('free', []);
        $tenant = $this->tenantWithPlan('free');

        $this->assertFalse(Feature::for($tenant)->active('white_label'));
    }

    public function test_pro_plan_cannot_use_white_label(): void
    {
        $this->planFor('pro', ['sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertFalse(Feature::for($tenant)->active('white_label'));
    }

    public function test_business_plan_can_use_white_label(): void
    {
        $this->planFor('business', ['white_label', 'unlimited_staff'], 999999);
        $tenant = $this->tenantWithPlan('business');

        $this->assertTrue(Feature::for($tenant)->active('white_label'));
    }

    public function test_free_plan_cannot_use_basic_reporting(): void
    {
        $this->planFor('free', []);
        $tenant = $this->tenantWithPlan('free');

        $this->assertFalse(Feature::for($tenant)->active('basic_reporting'));
    }

    public function test_starter_plan_can_use_basic_reporting(): void
    {
        $this->planFor('starter', ['add_customers', 'email_notifications', 'basic_reporting'], 5);
        $tenant = $this->tenantWithPlan('starter');

        $this->assertTrue(Feature::for($tenant)->active('basic_reporting'));
    }

    public function test_starter_plan_cannot_use_financial_reports(): void
    {
        $this->planFor('starter', ['add_customers', 'email_notifications', 'basic_reporting'], 5);
        $tenant = $this->tenantWithPlan('starter');

        $this->assertFalse(Feature::for($tenant)->active('financial_reports'));
    }

    public function test_pro_plan_can_use_basic_reporting(): void
    {
        $this->planFor('pro', ['basic_reporting', 'financial_reports', 'sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertTrue(Feature::for($tenant)->active('basic_reporting'));
    }

    public function test_pro_plan_can_use_financial_reports(): void
    {
        $this->planFor('pro', ['basic_reporting', 'financial_reports', 'sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertTrue(Feature::for($tenant)->active('financial_reports'));
    }

    public function test_business_plan_can_use_financial_reports(): void
    {
        $this->planFor('business', ['basic_reporting', 'financial_reports', 'white_label'], 999999);
        $tenant = $this->tenantWithPlan('business');

        $this->assertTrue(Feature::for($tenant)->active('financial_reports'));
    }

    public function test_null_tenant_has_no_features(): void
    {
        $this->planFor('free', []);

        $this->assertFalse(Feature::for(null)->active('add_customers'));
    }

    public function test_staff_limit_for_free_plan(): void
    {
        $this->planFor('free', [], 1);
        $tenant = $this->tenantWithPlan('free');

        $this->assertEquals(1, $tenant->staffLimit());
    }

    public function test_staff_limit_for_starter_plan(): void
    {
        $this->planFor('starter', ['add_customers'], 5);
        $tenant = $this->tenantWithPlan('starter');

        $this->assertEquals(5, $tenant->staffLimit());
    }

    public function test_staff_limit_for_pro_plan(): void
    {
        $this->planFor('pro', ['sms_notifications'], 15);
        $tenant = $this->tenantWithPlan('pro');

        $this->assertEquals(15, $tenant->staffLimit());
    }

    public function test_staff_limit_for_business_plan(): void
    {
        $this->planFor('business', ['white_label'], 999999);
        $tenant = $this->tenantWithPlan('business');

        $this->assertEquals(999999, $tenant->staffLimit());
    }
}
