<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE appointment_service_type ADD VALUE IF NOT EXISTS 'daycare_booking'");
    }

    public function down(): void
    {
        // PG enum values cannot be removed without recreating the type
    }
};
