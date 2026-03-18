<?php

namespace Tests\Unit\Services;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSmsUsage;
use App\Services\PlanFeatureCache;
use App\Services\SmsUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmsUsageService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
        $this->service = app(SmsUsageService::class);
        $this->tenant  = Tenant::factory()->create();
    }

    public function test_current_period_returns_year_month_format(): void
    {
        $period = $this->service->currentPeriod();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $period);
    }

    public function test_track_creates_usage_record(): void
    {
        $this->service->track($this->tenant->id, 3);

        $this->assertDatabaseHas('tenant_sms_usage', [
            'tenant_id'     => $this->tenant->id,
            'period'        => $this->service->currentPeriod(),
            'segments_used' => 3,
        ]);
    }

    public function test_track_increments_existing_record(): void
    {
        $this->service->track($this->tenant->id, 2);
        $this->service->track($this->tenant->id, 5);

        $this->assertDatabaseHas('tenant_sms_usage', [
            'tenant_id'     => $this->tenant->id,
            'period'        => $this->service->currentPeriod(),
            'segments_used' => 7,
        ]);
    }

    public function test_get_usage_returns_zero_when_no_record(): void
    {
        $usage = $this->service->getUsage($this->tenant->id, '2025-01');
        $this->assertSame(0, $usage);
    }

    public function test_get_usage_returns_correct_value(): void
    {
        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 42,
        ]);

        $usage = $this->service->getUsage($this->tenant->id, '2026-01');
        $this->assertSame(42, $usage);
    }

    public function test_get_overage_segments_returns_zero_within_quota(): void
    {
        $plan = PlatformPlan::factory()->create(['slug' => 'pro-test', 'sms_segment_quota' => 500]);

        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 300,
        ]);

        $overage = $this->service->getOverageSegments($this->tenant->id, 'pro-test', '2026-01');
        $this->assertSame(0, $overage);
    }

    public function test_get_overage_segments_returns_correct_overage(): void
    {
        $plan = PlatformPlan::factory()->create(['slug' => 'pro-test2', 'sms_segment_quota' => 500]);

        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 600,
        ]);

        $overage = $this->service->getOverageSegments($this->tenant->id, 'pro-test2', '2026-01');
        $this->assertSame(100, $overage);
    }

    public function test_is_already_billed_returns_false_when_no_record(): void
    {
        $this->assertFalse($this->service->isAlreadyBilled($this->tenant->id, '2026-01'));
    }

    public function test_is_already_billed_returns_false_when_not_billed(): void
    {
        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 100,
            'billed_at'     => null,
        ]);

        $this->assertFalse($this->service->isAlreadyBilled($this->tenant->id, '2026-01'));
    }

    public function test_is_already_billed_returns_true_when_billed(): void
    {
        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 100,
            'billed_at'     => now(),
        ]);

        $this->assertTrue($this->service->isAlreadyBilled($this->tenant->id, '2026-01'));
    }

    public function test_mark_billed_sets_billed_at(): void
    {
        TenantSmsUsage::create([
            'tenant_id'     => $this->tenant->id,
            'period'        => '2026-01',
            'segments_used' => 100,
            'billed_at'     => null,
        ]);

        $this->service->markBilled($this->tenant->id, '2026-01');

        $record = TenantSmsUsage::where('tenant_id', $this->tenant->id)
            ->where('period', '2026-01')
            ->first();

        $this->assertNotNull($record->billed_at);
    }
}
