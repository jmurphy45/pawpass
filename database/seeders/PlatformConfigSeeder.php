<?php

namespace Database\Seeders;

use App\Models\PlatformConfig;
use Illuminate\Database\Seeder;

class PlatformConfigSeeder extends Seeder
{
    public function run(): void
    {
        PlatformConfig::updateOrCreate(
            ['key' => 'trial_days'],
            ['value' => '21', 'updated_at' => now()]
        );
    }
}
