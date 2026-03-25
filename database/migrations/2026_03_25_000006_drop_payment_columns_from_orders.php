<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_stripe_pi_id_key');
            $table->dropColumn(['stripe_pi_id', 'stripe_payment_method', 'paid_at', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('stripe_pi_id')->nullable();
            $table->text('stripe_payment_method')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
        });
    }
};
