<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessAutoReplenishJob;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\AutoReplenishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessAutoReplenishJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_calls_auto_replenish_service_for_dog(): void
    {
        $tenant   = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $package  = Package::factory()->create(['tenant_id' => $tenant->id]);
        $dog      = Dog::factory()->create([
            'tenant_id'                 => $tenant->id,
            'customer_id'               => $customer->id,
            'auto_replenish_enabled'    => true,
            'auto_replenish_package_id' => $package->id,
        ]);

        $this->mock(AutoReplenishService::class, function (MockInterface $mock) use ($dog) {
            $mock->shouldReceive('trigger')
                ->once()
                ->withArgs(fn ($d) => $d->id === $dog->id);
        });

        (new ProcessAutoReplenishJob($dog->id))->handle(app(AutoReplenishService::class));
    }

    public function test_job_skips_silently_when_dog_not_found(): void
    {
        $this->mock(AutoReplenishService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('trigger');
        });

        (new ProcessAutoReplenishJob('nonexistent_id'))->handle(app(AutoReplenishService::class));
    }
}
