<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $plans = DB::table('platform_plans')
            ->whereIn('slug', ['starter', 'pro', 'business'])
            ->get();

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true);
            if (!in_array('recurring_checkout', $features)) {
                $features[] = 'recurring_checkout';
                DB::table('platform_plans')
                    ->where('id', $plan->id)
                    ->update(['features' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        $plans = DB::table('platform_plans')
            ->whereIn('slug', ['starter', 'pro', 'business'])
            ->get();

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true);
            $features = array_values(array_filter($features, fn ($f) => $f !== 'recurring_checkout'));
            DB::table('platform_plans')
                ->where('id', $plan->id)
                ->update(['features' => json_encode($features)]);
        }
    }
};
