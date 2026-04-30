<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_features')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'slug' => 'pims_integration',
            'name' => 'PIMS Integration',
            'description' => 'Connect a Practice Information Management System to auto-sync clients, patients, and vaccination records.',
            'is_marketing' => true,
            'sort_order' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plans = DB::table('platform_plans')
            ->whereIn('slug', ['pro', 'founders', 'business'])
            ->pluck('id');

        $featureId = DB::table('platform_features')
            ->where('slug', 'pims_integration')
            ->value('id');

        foreach ($plans as $planId) {
            DB::table('platform_plan_features')->insertOrIgnore([
                'plan_id' => $planId,
                'feature_id' => $featureId,
            ]);
        }
    }

    public function down(): void
    {
        $featureId = DB::table('platform_features')
            ->where('slug', 'pims_integration')
            ->value('id');

        DB::table('platform_plan_features')->where('feature_id', $featureId)->delete();
        DB::table('platform_features')->where('slug', 'pims_integration')->delete();
    }
};
