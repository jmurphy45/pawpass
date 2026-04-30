<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pims_sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26)->index();
            $table->text('provider');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->text('status')->default('running');
            $table->integer('clients_processed')->default(0);
            $table->integer('patients_processed')->default(0);
            $table->integer('vaccinations_processed')->default(0);
            $table->text('error_detail')->nullable();
            // No updated_at — append-only log
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE pims_sync_logs ADD CONSTRAINT pims_sync_logs_status_check CHECK (status IN ('running', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pims_sync_logs');
    }
};
