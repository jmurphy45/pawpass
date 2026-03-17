<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->text('slug')->unique();
            $table->text('name');
            $table->text('description')->nullable();
            $table->integer('monthly_price_cents');
            $table->integer('annual_price_cents');
            $table->text('stripe_product_id')->nullable();
            $table->text('stripe_monthly_price_id')->nullable();
            $table->text('stripe_annual_price_id')->nullable();
            $table->jsonb('features')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
    }
};
