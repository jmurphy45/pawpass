<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlatformFeatureSeeder::class,
            PlatformPlanSeeder::class,
            PlatformConfigSeeder::class,
            PlatformAdminSeeder::class,
        ]);
    }
}
