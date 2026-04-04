<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate the public sitemap.xml for platform.pawpass.com';

    public function handle(): int
    {
        $sitemap = Sitemap::create();

        // Static platform pages
        $sitemap->add(
            Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
        );
        $sitemap->add(
            Url::create('/register')->setPriority(0.8)->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
        );
        $sitemap->add(
            Url::create('/find-a-daycare')->setPriority(0.9)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
        );

        // Per-city SEO pages (one per unique state+city combination)
        $cities = Tenant::query()
            ->where('is_publicly_listed', true)
            ->whereIn('status', ['active', 'trialing', 'free_tier', 'past_due'])
            ->whereNotNull('business_city')
            ->whereNotNull('business_state')
            ->select('business_state', 'business_city')
            ->distinct()
            ->get();

        foreach ($cities as $location) {
            $stateSlug = strtolower($location->business_state);
            $citySlug  = str_replace(' ', '-', strtolower($location->business_city));

            $sitemap->add(
                Url::create("/find-a-daycare/{$stateSlug}/{$citySlug}")
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        }

        // Individual tenant landing pages (subdomain URLs)
        $tenants = Tenant::query()
            ->where('is_publicly_listed', true)
            ->whereIn('status', ['active', 'trialing', 'free_tier', 'past_due'])
            ->select('slug')
            ->get();

        $appDomain = config('app.domain', 'pawpass.com');

        foreach ($tenants as $tenant) {
            $sitemap->add(
                Url::create("https://{$tenant->slug}.{$appDomain}/")
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info("Sitemap generated: {$cities->count()} city pages, {$tenants->count()} tenant pages.");

        return self::SUCCESS;
    }
}
