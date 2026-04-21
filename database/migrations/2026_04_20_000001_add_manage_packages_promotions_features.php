<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $newFeatures = [
            [
                'id' => (string) Str::ulid(),
                'slug' => 'manage_packages',
                'name' => 'Manage Packages',
                'description' => 'Create, edit, and archive packages.',
                'is_marketing' => true,
                'sort_order' => 55,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'slug' => 'manage_promotions',
                'name' => 'Manage Promotions',
                'description' => 'Create and manage promotional discount codes.',
                'is_marketing' => true,
                'sort_order' => 57,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($newFeatures as $feature) {
            DB::table('platform_features')->insertOrIgnore($feature);
        }

        $paidPlanSlugs = ['starter', 'pro', 'founders', 'business'];
        $plans = DB::table('platform_plans')->whereIn('slug', $paidPlanSlugs)->pluck('id', 'slug');
        $featureIds = DB::table('platform_features')
            ->whereIn('slug', ['manage_packages', 'manage_promotions'])
            ->pluck('id');

        foreach ($plans as $planId) {
            foreach ($featureIds as $featureId) {
                DB::table('platform_plan_features')->insertOrIgnore([
                    'plan_id' => $planId,
                    'feature_id' => $featureId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $featureIds = DB::table('platform_features')
            ->whereIn('slug', ['manage_packages', 'manage_promotions'])
            ->pluck('id');

        DB::table('platform_plan_features')->whereIn('feature_id', $featureIds)->delete();
        DB::table('platform_features')->whereIn('slug', ['manage_packages', 'manage_promotions'])->delete();
    }
};
