<?php

namespace Tests\Unit\Models;

use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current.tenant.id');
    }

    public function test_dog_limit_and_duration_days_are_fillable(): void
    {
        $tenant = Tenant::factory()->create();
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'dog_limit' => 3,
            'duration_days' => null,
        ]);

        $this->assertSame(3, $package->dog_limit);
        $this->assertNull($package->duration_days);
    }

    public function test_multi_dog_factory_state_sets_dog_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $package = Package::factory()->multiDog(2)->create(['tenant_id' => $tenant->id]);

        $this->assertSame(2, $package->dog_limit);
    }

    public function test_unlimited_factory_state_sets_type_and_duration(): void
    {
        $tenant = Tenant::factory()->create();
        $package = Package::factory()->unlimited(30)->create(['tenant_id' => $tenant->id]);

        $this->assertSame('unlimited', $package->type);
        $this->assertSame(30, $package->duration_days);
        $this->assertNull($package->credit_count);
    }

    public function test_dog_limit_defaults_to_one(): void
    {
        $tenant = Tenant::factory()->create();
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertSame(1, $package->dog_limit);
    }
}
