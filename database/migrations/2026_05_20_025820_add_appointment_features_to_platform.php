<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $features = [
            ['slug' => 'grooming_appointments', 'name' => 'Grooming Appointments', 'description' => 'Schedule and manage grooming appointments.', 'sort_order' => 210],
            ['slug' => 'vet_appointments',       'name' => 'Vet Appointments',      'description' => 'Schedule and manage veterinary appointments.',  'sort_order' => 220],
            ['slug' => 'daycare_booking',        'name' => 'Daycare Booking',       'description' => 'Manage daycare capacity windows and bookings.',  'sort_order' => 230],
        ];

        foreach ($features as $feature) {
            $id = (string) Str::ulid();
            DB::table('platform_features')->insertOrIgnore([
                'id' => $id,
                'slug' => $feature['slug'],
                'name' => $feature['name'],
                'description' => $feature['description'],
                'is_marketing' => true,
                'sort_order' => $feature['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Link each feature to all plans
        $planIds = DB::table('platform_plans')
            ->whereIn('slug', ['starter', 'pro', 'founders'])
            ->pluck('id');

        foreach ($features as $feature) {
            $featureId = DB::table('platform_features')->where('slug', $feature['slug'])->value('id');
            if (! $featureId) {
                continue;
            }
            foreach ($planIds as $planId) {
                DB::table('platform_plan_features')->insertOrIgnore([
                    'plan_id' => $planId,
                    'feature_id' => $featureId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = ['grooming_appointments', 'vet_appointments', 'daycare_booking'];

        $featureIds = DB::table('platform_features')->whereIn('slug', $slugs)->pluck('id');

        DB::table('platform_plan_features')->whereIn('feature_id', $featureIds)->delete();
        DB::table('platform_features')->whereIn('slug', $slugs)->delete();
    }
};
