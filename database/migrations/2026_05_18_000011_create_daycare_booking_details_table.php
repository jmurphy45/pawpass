<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daycare_booking_details', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('appointment_id', 26)->unique();
            $table->char('attendance_id', 26)->nullable();
            $table->char('credit_hold_ledger_id', 26)->nullable();
            $table->timestampTz('credit_deducted_at')->nullable();
            $table->time('drop_off_window_start')->nullable();
            $table->time('drop_off_window_end')->nullable();
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
            $table->foreign('credit_hold_ledger_id')->references('id')->on('credit_ledger')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daycare_booking_details');
    }
};
