<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('platform_fee_amount_cents')->nullable()->after('platform_fee_pct');
            $table->integer('processing_fee_amount_cents')->nullable()->after('platform_fee_amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_amount_cents', 'processing_fee_amount_cents']);
        });
    }
};
