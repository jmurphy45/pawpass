<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_spots', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->string('spot_number', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->unique(['tenant_id', 'spot_number']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active'], 'parking_spots_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_spots');
    }
};
