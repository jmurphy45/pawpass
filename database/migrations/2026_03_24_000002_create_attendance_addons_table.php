<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_addons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('attendance_id', 26);
            $table->char('addon_type_id', 26);
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign('attendance_id')->references('id')->on('attendances')->onDelete('cascade');
            $table->foreign('addon_type_id')->references('id')->on('addon_types');

            $table->index('attendance_id', 'attendance_addons_attendance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_addons');
    }
};
