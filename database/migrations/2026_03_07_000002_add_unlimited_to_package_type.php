<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE package_type ADD VALUE IF NOT EXISTS 'unlimited'");
    }

    public function down(): void
    {
        // Enum values cannot be removed in PostgreSQL
    }
};
