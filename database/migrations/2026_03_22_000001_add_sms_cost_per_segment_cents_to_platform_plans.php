<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->integer('sms_cost_per_segment_cents')->default(4)->after('sms_segment_quota');
        });
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn('sms_cost_per_segment_cents');
        });
    }
};
