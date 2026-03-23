<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->text('feeding_schedule')->nullable()->after('notes');
            $table->text('medication_notes')->nullable()->after('feeding_schedule');
            $table->text('behavioral_notes')->nullable()->after('medication_notes');
            $table->text('emergency_contact')->nullable()->after('behavioral_notes');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['feeding_schedule', 'medication_notes', 'behavioral_notes', 'emergency_contact']);
        });
    }
};
