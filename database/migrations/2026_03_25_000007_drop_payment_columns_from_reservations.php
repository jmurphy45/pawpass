<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_pi_id',
                'deposit_amount_cents',
                'deposit_captured_at',
                'deposit_refunded_at',
                'checkout_pi_id',
                'checkout_charge_cents',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('stripe_pi_id')->nullable();
            $table->integer('deposit_amount_cents')->nullable();
            $table->timestampTz('deposit_captured_at')->nullable();
            $table->timestampTz('deposit_refunded_at')->nullable();
            $table->text('checkout_pi_id')->nullable();
            $table->integer('checkout_charge_cents')->nullable();
        });
    }
};
