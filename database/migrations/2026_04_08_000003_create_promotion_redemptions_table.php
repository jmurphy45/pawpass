<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->char('promotion_id', 26);
            $table->foreign('promotion_id')->references('id')->on('promotions');
            $table->char('order_id', 26);
            $table->foreign('order_id')->references('id')->on('orders');
            $table->char('customer_id', 26);
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->integer('discount_amount_cents')->default(0);
            $table->integer('original_amount_cents')->default(0);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        DB::statement('CREATE INDEX promotion_redemptions_promo_idx ON promotion_redemptions(promotion_id, created_at DESC)');
        DB::statement('CREATE INDEX promotion_redemptions_customer_idx ON promotion_redemptions(customer_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
    }
};
