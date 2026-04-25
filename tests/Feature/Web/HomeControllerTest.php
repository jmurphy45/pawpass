<?php

namespace Tests\Feature\Web;

use App\Models\KennelUnit;
use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_kennel_units_for_kennel_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'kennelco',
            'status' => 'active',
            'business_type' => 'kennel',
        ]);
        URL::forceRootUrl('http://kennelco.pawpass.com');

        KennelUnit::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
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
            'slug' => 'hybridco',
            'status' => 'active',
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

    public function test_passes_kennel_units_for_daycare_tenant_when_units_exist(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'daycareco',
            'status' => 'active',
            'business_type' => 'daycare',
        ]);
        URL::forceRootUrl('http://daycareco.pawpass.com');

        KennelUnit::factory()->count(2)->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        KennelUnit::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('kennel_units', 2)
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

    public function test_home_page_prop_defaults_when_no_settings_saved(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'freshco', 'status' => 'active']);
        URL::forceRootUrl('http://freshco.pawpass.com');

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('home_page')
                ->where('home_page.hero_headline', "Your dog's home away from home.")
                ->has('home_page.trust_badges', 6)
                ->has('home_page.why_cards', 3)
                ->where('home_page.footer_cta_headline', 'Ready to join the pack?')
            );
    }

    public function test_home_page_prop_reflects_saved_settings(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'savedco', 'status' => 'active']);
        URL::forceRootUrl('http://savedco.pawpass.com');

        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'meta' => ['home_page' => ['hero_headline' => 'Custom headline']],
        ]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('home_page.hero_headline', 'Custom headline')
                ->has('home_page.trust_badges', 6)
            );
    }

    public function test_platform_page_does_not_receive_home_page_prop(): void
    {
        URL::forceRootUrl('http://platform.pawpass.com');

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->missing('home_page')
            );
    }
}
