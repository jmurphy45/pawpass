<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateSitemapCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(public_path('sitemap.xml'));
        parent::tearDown();
    }

    public function test_sitemap_includes_leaderboard_and_boarding_static_pages(): void
    {
        $this->artisan('sitemap:generate')->assertSuccessful();

        $content = File::get(public_path('sitemap.xml'));
        $this->assertStringContainsString('/leaderboard', $content);
        $this->assertStringContainsString('/find-boarding', $content);
    }

    public function test_sitemap_includes_city_leaderboard_pages(): void
    {
        Tenant::factory()->create([
            'status'             => 'active',
            'is_publicly_listed' => true,
            'business_city'      => 'Memphis',
            'business_state'     => 'TN',
            'business_type'      => 'daycare',
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $content = File::get(public_path('sitemap.xml'));
        $this->assertStringContainsString('/leaderboard/tn/memphis', $content);
    }

    public function test_sitemap_includes_boarding_city_pages_only_for_kennel_hybrid(): void
    {
        Tenant::factory()->create([
            'status'             => 'active',
            'is_publicly_listed' => true,
            'business_city'      => 'Nashville',
            'business_state'     => 'TN',
            'business_type'      => 'kennel',
        ]);
        Tenant::factory()->create([
            'status'             => 'active',
            'is_publicly_listed' => true,
            'business_city'      => 'Memphis',
            'business_state'     => 'TN',
            'business_type'      => 'daycare',
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $content = File::get(public_path('sitemap.xml'));
        $this->assertStringContainsString('/find-boarding/tn/nashville', $content);
        $this->assertStringNotContainsString('/find-boarding/tn/memphis', $content);
    }
}
