<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26);
            $table->char('attendance_id', 26);
            $table->char('created_by', 26)->nullable();
            $table->text('body');
            $table->boolean('is_public')->default(false);
            $table->timestampsTz();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('attendance_id')->references('id')->on('attendances')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('attendance_id');
            $table->index(['tenant_id', 'attendance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_comments');
    }
};
