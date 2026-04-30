<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pims_integrations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->text('provider');
            $table->text('api_base_url')->nullable();
            $table->text('credentials'); // encrypted JSON
            $table->text('status')->default('active');
            $table->timestampTz('last_full_sync_at')->nullable();
            $table->timestampTz('last_delta_sync_at')->nullable();
            $table->text('sync_cursor')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'provider']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE pims_integrations ADD CONSTRAINT pims_integrations_status_check CHECK (status IN ('active', 'error', 'disabled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pims_integrations');
    }
};
