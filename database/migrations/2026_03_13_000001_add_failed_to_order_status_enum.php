<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE order_status ADD VALUE IF NOT EXISTS 'failed'");
    }

    public function down(): void
    {
        // PostgreSQL does not support removing enum values
    }
};
