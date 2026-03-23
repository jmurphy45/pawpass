<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_business_type_defaults_to_daycare(): void
    {
        $tenant = Tenant::factory()->create()->fresh();

        $this->assertEquals('daycare', $tenant->business_type);
        $this->assertTrue($tenant->isDaycare());
    }

    public function test_is_kennel_helper_returns_true_for_kennel_type(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'kennel']);

        $this->assertTrue($tenant->isKennel());
        $this->assertFalse($tenant->isDaycare());
        $this->assertFalse($tenant->isHybrid());
    }

    public function test_is_hybrid_helper_returns_true_for_hybrid_type(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'hybrid']);

        $this->assertTrue($tenant->isHybrid());
        $this->assertFalse($tenant->isDaycare());
        $this->assertFalse($tenant->isKennel());
    }
}
