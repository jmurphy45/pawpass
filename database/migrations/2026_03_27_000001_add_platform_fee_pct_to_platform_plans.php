<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->decimal('platform_fee_pct', 5, 2)->default(5.00)->after('sms_cost_per_segment_cents');
        });
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn('platform_fee_pct');
        });
    }
};
