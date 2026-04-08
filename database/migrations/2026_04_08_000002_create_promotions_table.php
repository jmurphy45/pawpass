<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->text('name');
            $table->text('code');
            $table->text('type'); // 'percentage' | 'fixed_cents'
            $table->integer('discount_value'); // pct (0-100) or cents
            // Polymorphic applicability: null = applies to everything
            // applicable_type examples: 'App\Models\Package', 'boarding', 'daycare'
            $table->text('applicable_type')->nullable();
            $table->char('applicable_id', 26)->nullable();
            $table->integer('min_purchase_cents')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Unique code per tenant (only among non-deleted promos)
        DB::statement('CREATE UNIQUE INDEX promotions_tenant_code_unique ON promotions(tenant_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
