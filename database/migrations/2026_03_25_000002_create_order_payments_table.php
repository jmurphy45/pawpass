<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('order_id', 26);
            $table->text('stripe_pi_id')->nullable();
            $table->text('stripe_payment_method')->nullable();
            $table->integer('amount_cents');
            $table->text('type');
            $table->text('status');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });

        DB::statement('CREATE UNIQUE INDEX order_payments_stripe_pi_id_key ON order_payments(stripe_pi_id) WHERE stripe_pi_id IS NOT NULL');
        DB::statement('CREATE INDEX order_payments_order_id_idx ON order_payments(order_id)');
        DB::statement('CREATE INDEX order_payments_tenant_id_idx ON order_payments(tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
