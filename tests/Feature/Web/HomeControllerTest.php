<?php

namespace Tests\Feature\Web;

use App\Models\KennelUnit;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_kennel_units_for_kennel_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'kennelco',
            'status'        => 'active',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://kennelco.pawpass.com');

        KennelUnit::factory()->count(2)->create([
            'tenant_id'          => $tenant->id,
            'is_active'          => true,
            'nightly_rate_cents' => 7500,
        ]);
        KennelUnit::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('kennel_units', 2)
                ->where('kennel_units.0.id', fn ($v) => is_string($v))
                ->where('kennel_units.0.name', fn ($v) => is_string($v))
                ->where('kennel_units.0.type', fn ($v) => is_string($v))
                ->where('kennel_units.0.nightly_rate_cents', 7500)
                ->has('kennel_units.0.description')
                ->has('kennel_units.0.capacity')
            );
    }

    public function test_passes_kennel_units_for_hybrid_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'hybridco',
            'status'        => 'active',
            'business_type' => 'hybrid',
        ]);
        URL::forceRootUrl('http://hybridco.pawpass.com');

        KennelUnit::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('kennel_units', 3)
            );
    }

    public function test_kennel_units_empty_for_daycare_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'          => 'daycareco',
            'status'        => 'active',
            'business_type' => 'daycare',
        ]);
        URL::forceRootUrl('http://daycareco.pawpass.com');

        KennelUnit::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('kennel_units', 0)
            );
    }

    public function test_kennel_units_not_present_on_platform_page(): void
    {
        URL::forceRootUrl('http://platform.pawpass.com');

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->missing('kennel_units')
            );
    }
}
