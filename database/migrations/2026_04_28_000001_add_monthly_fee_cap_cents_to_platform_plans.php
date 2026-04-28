<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('monthly_fee_cap_cents')->nullable()->after('monthly_gmv_cap_cents');
        });
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn('monthly_fee_cap_cents');
        });
    }
};
