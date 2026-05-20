<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_appointment_details', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('appointment_id', 26)->unique();
            $table->char('tenant_id', 26)->index();
            $table->char('vet_user_id', 26)->nullable();
            $table->char('resource_id', 26)->nullable();
            $table->text('reason');
            $table->text('diagnosis')->nullable();
            $table->integer('price_cents');
            $table->integer('duration_mins')->default(30);
            $table->text('pims_appt_id')->nullable();
            $table->timestampsTz();

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('vet_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resource_id')->references('id')->on('bookable_resources')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_appointment_details');
    }
};
