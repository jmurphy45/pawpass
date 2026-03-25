<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestampTz('actual_checkout_at')->nullable()->after('deposit_refunded_at');
            $table->text('checkout_pi_id')->nullable()->after('actual_checkout_at');
            $table->integer('checkout_charge_cents')->nullable()->after('checkout_pi_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['actual_checkout_at', 'checkout_pi_id', 'checkout_charge_cents']);
        });
    }
};
