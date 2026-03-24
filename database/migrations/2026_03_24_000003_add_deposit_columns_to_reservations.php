<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('deposit_amount_cents')->nullable()->after('nightly_rate_cents');
            $table->text('stripe_pi_id')->nullable()->after('deposit_amount_cents');
            $table->timestampTz('deposit_captured_at')->nullable()->after('stripe_pi_id');
            $table->timestampTz('deposit_refunded_at')->nullable()->after('deposit_captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount_cents', 'stripe_pi_id', 'deposit_captured_at', 'deposit_refunded_at']);
        });
    }
};
