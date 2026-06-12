<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $newFeature = [
            'id' => (string) Str::ulid(),
            'slug' => 'parking_management',
            'name' => 'Parking Management',
            'description' => 'Create and manage parking spots with QR codes.',
            'is_marketing' => true,
            'sort_order' => 240,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('platform_features')->insertOrIgnore($newFeature);

        $paidPlanSlugs = ['starter', 'pro', 'founders', 'business'];
        $plans = DB::table('platform_plans')->whereIn('slug', $paidPlanSlugs)->pluck('id');
        $featureId = DB::table('platform_features')
            ->where('slug', 'parking_management')
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
            ->where('slug', 'parking_management')
            ->value('id');

        if ($featureId) {
            DB::table('platform_plan_features')->where('feature_id', $featureId)->delete();
            DB::table('platform_features')->where('slug', 'parking_management')->delete();
        }
    }
};
