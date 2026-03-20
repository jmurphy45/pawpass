<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any remaining 'subscription' type packages to 'one_time' before removing the concept
        DB::table('packages')->where('type', 'subscription')->update(['type' => 'one_time']);

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_price_id_monthly',
                'is_recurring_enabled',
                'recurring_interval_days',
                'stripe_price_id_recurring',
            ]);
            $table->boolean('is_auto_replenish_eligible')->default(false)->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('is_auto_replenish_eligible');
            $table->text('stripe_price_id_monthly')->nullable();
            $table->boolean('is_recurring_enabled')->default(false);
            $table->integer('recurring_interval_days')->nullable();
            $table->text('stripe_price_id_recurring')->nullable();
        });
    }
};
