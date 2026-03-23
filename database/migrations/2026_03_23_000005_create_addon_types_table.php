<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_types', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->text('name');
            $table->integer('price_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        DB::statement('CREATE INDEX addon_types_tenant_active_idx ON addon_types(tenant_id, is_active, sort_order)');
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_types');
    }
};
