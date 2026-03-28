<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE tenant_plan ADD VALUE IF NOT EXISTS 'founders'");
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values; no-op on rollback
    }
};
