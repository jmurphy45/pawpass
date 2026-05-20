<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grooming_appointment_details', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->index();
            $table->char('appointment_id', 26)->unique();
            $table->char('groomer_user_id', 26)->nullable();
            $table->char('resource_id', 26)->nullable();
            $table->text('service_name');
            $table->integer('price_cents');
            $table->integer('duration_mins')->default(60);
            $table->timestampsTz();

            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('groomer_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resource_id')->references('id')->on('bookable_resources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grooming_appointment_details');
    }
};
