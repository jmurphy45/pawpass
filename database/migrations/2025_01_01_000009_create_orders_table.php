<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('customer_id', 26);
            $table->char('package_id', 26);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('platform_fee_pct', 5, 2)->default(5.0);
            $table->text('stripe_pi_id')->nullable();
            $table->text('stripe_payment_method')->nullable();
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('package_id')->references('id')->on('packages');
        });

        DB::statement("ALTER TABLE orders ADD COLUMN status order_status NOT NULL DEFAULT 'paid'");
        DB::statement('CREATE UNIQUE INDEX orders_stripe_pi_id_key ON orders(stripe_pi_id) WHERE stripe_pi_id IS NOT NULL');
        DB::statement('CREATE INDEX orders_customer_id_idx ON orders(customer_id)');
        DB::statement('CREATE INDEX orders_tenant_id_idx ON orders(tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
