<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_recurring_enabled')->default(false)->after('stripe_price_id_monthly');
            $table->integer('recurring_interval_days')->nullable()->after('is_recurring_enabled');
            $table->text('stripe_price_id_recurring')->nullable()->after('recurring_interval_days');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['is_recurring_enabled', 'recurring_interval_days', 'stripe_price_id_recurring']);
        });
    }
};
